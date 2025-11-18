<?php

namespace Orryv;

use InvalidArgumentException;
use Orryv\Config;
use Orryv\XString;
use Orryv\XStringType;
use RuntimeException;

class DockerManagerOld
{
    private ?string $docker_workdir = null;
    private ?string $docker_compose_relative_path = null;
    private array $parsed_docker_compose;
    private array $inject_variables = [];
    private array $docker_output = [];
    private $progress_callback = null;
    private string $yaml_parser;

    public function __construct(string $docker_workdir, string $docker_compose_relative_path, string $yaml_parser = 'ext')
    {
        if (!in_array($yaml_parser, ['ext', 'symfony'], true)) {
            throw new InvalidArgumentException("Invalid YAML parser '{$yaml_parser}'. Use 'ext' or 'symfony'.");
        }
        $this->yaml_parser = $yaml_parser;

        if (!is_dir($docker_workdir)) {
            throw new InvalidArgumentException("Docker workdir '{$docker_workdir}' is not a valid directory.");
        }
        $docker_workdir = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $docker_workdir);
        $docker_compose_relative_path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $docker_compose_relative_path);

        $this->docker_workdir = rtrim($docker_workdir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->docker_compose_relative_path = ltrim($docker_compose_relative_path, DIRECTORY_SEPARATOR);

        if (!file_exists($this->docker_workdir . $this->docker_compose_relative_path)) {
            throw new InvalidArgumentException("Docker compose file '{$this->docker_compose_relative_path}' does not exist in workdir '{$this->docker_workdir}'.");
        }

        $this->docker_workdir = realpath($this->docker_workdir) . DIRECTORY_SEPARATOR;

        $compose_path = $this->docker_workdir . $this->docker_compose_relative_path;
        $this->parsed_docker_compose = $this->parseDockerCompose($compose_path);
    }

    public function injectVariable(string $key, string $value): self
    {
        $this->inject_variables[$key] = $value;

        return $this;
    }

    /**
     * Currently only works when docker compose is in the workdir root.
     */
    public function run(bool $rebuild = false, bool $save_logs = false): bool
    {
        // 1) Write temp compose file
        $tmp = rtrim($this->docker_workdir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'docker-compose-' . uniqid() . '.yml';
        $this->writeDockerCompose($tmp);

        // 2) Detect OS + choose quoting for the -f path
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $composeFileArg = $isWin ? '"' . $tmp . '"' : escapeshellarg($tmp);
        $composeBin = $this->detectComposeBin(); // docker compose OR docker-compose

        // 3) Build the command (with optional build step)
        $composeCmd = $rebuild
            ? $composeBin . ' -f ' . $composeFileArg . ' build --no-cache'
                . ' && ' . $composeBin . ' -f ' . $composeFileArg . ' up -d --force-recreate --renew-anon-volumes'
            : $composeBin . ' -f ' . $composeFileArg . ' up -d';

        // 4) Prepare the log
        $output_dir = rtrim(sys_get_temp_dir())
            . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }
        $output_file = $output_dir . 'docker-compose-' . time() . '-' . uniqid() . '.log';

        // 5) Start non-blocking via proc_open (cross-platform)
        $cmd = $isWin
            ? 'cmd.exe /C ' . $composeCmd
            : '/bin/sh -lc ' . escapeshellarg($composeCmd);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $output_file, 'a'],
            2 => ['file', $output_file, 'a'],
        ];
        $options = $isWin
            ? ['detached' => true, 'suppress_errors' => true]
            : [];

        // Build environment that includes injected variables
        $env = $this->buildProcessEnv();
        $cwd = $this->docker_workdir;

        $proc = proc_open($cmd, $descriptors, $pipes, $cwd, $env, $options);
        if (!is_resource($proc)) {
            throw new \RuntimeException('Failed to start compose process.');
        }
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        // 6) Stream the log while the process runs
        $last_cycle = false;
        for ($i = 0; $i < 3000; $i++) { // ~5 minutes
            clearstatcache(false, $output_file);
            if (is_file($output_file)) {
                $this->parseDockerOutput(file_get_contents($output_file));
            }
            $st = proc_get_status($proc);
            if (!$st['running']) {
                $last_cycle = true;
            }
            if ($last_cycle) {
                break;
            }
            usleep(250000); // 0.25s
        }

        // cleanup temp compose
        @unlink($tmp);
        if (!$save_logs) {
            @unlink($output_file);
        }

        // 7) if docker reported errors in the build/up stage, return false
        if (!empty($this->docker_output['errors'])) {
            return false;
        }

        // 8) NEW: wait for healthy containers (only those that have a healthcheck)
        $this->waitForHealthOfServices(120); // 60s total should be plenty

        return true;
    }

    public static function isDockerRunning(): bool
    {
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $null = $isWin ? 'NUL' : '/dev/null';

        $cmd = "docker info > {$null} 2>&1";

        $exitCode = 1;
        @exec($cmd, $out, $exitCode);

        if ($exitCode === 0) {
            return true;
        }

        $cmd = "docker ps -q > {$null} 2>&1";
        @exec($cmd, $out, $exitCode);

        return $exitCode === 0;
    }

    private function buildProcessEnv(): array
    {
        $env = getenv();
        if (!is_array($env)) {
            $env = [];
        }

        foreach ($this->inject_variables as $key => $value) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                throw new InvalidArgumentException("Invalid environment variable name: '{$key}'.");
            }
            $env[$key] = (string) $value;
        }

        return $env;
    }

    private function detectComposeBin(): string
    {
        foreach (['docker compose', 'docker-compose'] as $bin) {
            $code = 1;
            @exec($bin . ' version', $out, $code);
            if ($code === 0) return $bin;
        }
        return 'docker-compose';
    }

    private function parseDockerOutput(string $output): void
    {
        $lines = explode("\n", $output);
        $builds = [
            'containers' => [],
            'networks' => [],
            'build_status' => '',
            'errors' => [],
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $line = XString::new($line);

            if ($line->trim()->startsWith('Network ')) {
                $name = (string) $line->between(' ', ' ')->trim();
                $status = (string) $line->trim()->after($name, true)->trim();
                $builds['networks'][$name] = $status;
                continue;
            } elseif ($line->trim()->startsWith('Container ')) {
                $name = (string) $line->between(' ', ' ')->trim();
                $status = (string) $line->trim()->after($name, true)->trim();
                $builds['containers'][$name] = $status;
                continue;
            } elseif ($line->trim()->startsWith(XStringType::regex('/^#[0-9]+[ ]/'))) {
                $status = (string) $line->trim();
                $builds['build_status'] = $status;
                continue;
            } elseif ($line->trim()->startsWith('Error ')) {
                $error = (string) $line->trim();
                $builds['errors'][] = $error;
                continue;
            }
        }

        if (is_callable($this->progress_callback)) {
            call_user_func($this->progress_callback, $builds);
        }

        $this->docker_output = $builds;
    }

    private function parseDockerCompose(string $compose_path): array
    {
        switch ($this->yaml_parser) {
            case 'ext':
                if (!function_exists('yaml_parse_file')) {
                    throw new RuntimeException('ext-yaml is required when using the "ext" YAML parser option.');
                }
                /** @disregard P1010 yaml_emit_file comes from optional ext-yaml */
                $parsed = yaml_parse_file($compose_path);
                break;
            case 'symfony':
                /** @disregard P1009 Symfony\\Component\\Yaml\\Yaml is an optional dependency */
                if (!class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                    throw new RuntimeException('symfony/yaml must be installed to use the "symfony" YAML parser option.');
                }
                /** @disregard P1009 Symfony\\Component\\Yaml\\Yaml is an optional dependency */
                $parsed = \Symfony\Component\Yaml\Yaml::parseFile($compose_path);
                break;
            default:
                throw new RuntimeException('Unsupported YAML parser.');
        }

        if (!is_array($parsed)) {
            throw new RuntimeException('Docker compose file could not be parsed into an array.');
        }

        return $parsed;
    }

    private function writeDockerCompose(string $target_path): void
    {
        switch ($this->yaml_parser) {
            case 'ext':
                if (!function_exists('yaml_emit_file')) {
                    throw new RuntimeException('ext-yaml is required when using the "ext" YAML parser option.');
                }
                /** @disregard P1010 yaml_emit_file comes from optional ext-yaml */
                $result = yaml_emit_file($target_path, $this->parsed_docker_compose);
                if ($result === false) {
                    throw new RuntimeException('Failed to write temporary docker compose file.');
                }
                return;
            case 'symfony':
                /** @disregard P1009 Symfony\\Component\\Yaml\\Yaml is an optional dependency */
                if (!class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                    throw new RuntimeException('symfony/yaml must be installed to use the "symfony" YAML parser option.');
                }
                /** @disregard P1009 Symfony\\Component\\Yaml\\Yaml is an optional dependency */
                $yaml = \Symfony\Component\Yaml\Yaml::dump($this->parsed_docker_compose, 10, 2);
                if (file_put_contents($target_path, $yaml) === false) {
                    throw new RuntimeException('Failed to write temporary docker compose file.');
                }
                return;
        }

        throw new RuntimeException('Unsupported YAML parser.');
    }

    public function getErrors(): array
    {
        return $this->docker_output['errors'] ?? [];
    }

    public function onProgress(?callable $callback = null): self
    {
        $this->progress_callback = $callback;
        return $this;
    }

    public function hasPortInUseError(): bool
    {
        foreach ($this->docker_output['errors'] ?? [] as $error) {
            $x = XString::new($error);
            if ($x->trim()->endsWith('failed: port is already allocated')) {
                return true;
            } elseif ($x->contains('address already in use')) {
                return true;
            }
        }
        return false;
    }

    public function setName(string $name): self
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Container name cannot be empty.');
        }

        $this->parsed_docker_compose['name'] = $name;

        if (isset($this->parsed_docker_compose['services']) && is_array($this->parsed_docker_compose['services'])) {
            foreach ($this->parsed_docker_compose['services'] as $service => &$config) {
                if (is_array($config)) {
                    // e.g. dev-index3-mysql-mysql
                    $config['container_name'] = $name . '-' . $service;
                }
            }
            unset($config);
        }

        return $this;
    }

    /**
     * Waits until all services that define a healthcheck are healthy.
     */
    private function waitForHealthOfServices(int $timeoutSeconds): void
    {
        if (!isset($this->parsed_docker_compose['services']) || !is_array($this->parsed_docker_compose['services'])) {
            return;
        }

        $containersToWatch = [];
        foreach ($this->parsed_docker_compose['services'] as $serviceName => $serviceConf) {
            if (!is_array($serviceConf)) {
                continue;
            }
            if (!isset($serviceConf['healthcheck'])) {
                continue;
            }
            // container_name is guaranteed in setName()
            $containerName = $serviceConf['container_name'] ?? null;
            if ($containerName) {
                $containersToWatch[] = $containerName;
            }
        }

        if (!$containersToWatch) {
            return; // nothing to wait for
        }

        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $start = time();

        foreach ($containersToWatch as $container) {
            while (true) {
                $cmd = $isWin
                    ? 'docker inspect -f "{{.State.Health.Status}}" ' . $container
                    : 'docker inspect -f "{{.State.Health.Status}}" ' . escapeshellarg($container);

                $output = [];
                $exitCode = 0;
                exec($cmd, $output, $exitCode);

                if ($exitCode === 0 && isset($output[0])) {
                    $status = trim($output[0], " \t\n\r\"");
                    if ($status === 'healthy') {
                        break;
                    }
                }

                if ((time() - $start) >= $timeoutSeconds) {
                    throw new \RuntimeException("Container '{$container}' did not become healthy in {$timeoutSeconds} seconds.");
                }

                usleep(500_000); // 0.5s
            }
        }
    }
}
