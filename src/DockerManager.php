<?php

namespace Orryv;

use InvalidArgumentException;
use Orryv\DockerManager\Compose\ComposeDefinition;
use Orryv\DockerManager\Parser\DockerOutputParser;
use Orryv\DockerManager\Process\ProcessContext;
use Orryv\DockerManager\Process\ProcessResult;
use Orryv\DockerManager\Process\ProcessRunnerInterface;
use Orryv\DockerManager\Process\ProcOpenProcessRunner;
use Orryv\XString;
use RuntimeException;

class DockerManager
{
    private ?XString $docker_compose_path = null;
    private ?XString $docker_compose_dir = null;
    private ?XString $name = null;
    private string $yaml_parser_raw;
    private ?ComposeDefinition $composeDefinition = null;
    private array $env_variables = [];
    private ?XString $debug_path = null;
    private ?XString $dockerfile_path = null;
    private bool $from_is_already_called = false;
    private array $docker_output = [];
    /** @var callable|null */
    private $progress_callback = null;
    private DockerOutputParser $output_parser;
    private ProcessRunnerInterface $process_runner;
    private ?int $last_exit_code = null;
    private ?string $last_output = null;
    private ?string $last_parsed_chunk = null;

    public function __construct(string $yaml_parser = 'ext', ?ProcessRunnerInterface $runner = null)
    {
        $this->yaml_parser_raw = $this->getYamlParser($yaml_parser);
        $this->output_parser = new DockerOutputParser();
        $this->process_runner = $runner ?? new ProcOpenProcessRunner();
    }

    public function fromDockerCompose(string $docker_compose_full_path): DockerManager
    {
        if ($this->from_is_already_called) {
            throw new RuntimeException("a 'from' method has already been called.");
        }

        $this->from_is_already_called = true;

        $this->docker_compose_path = $this->parseDockerComposePath($docker_compose_full_path);
        $this->composeDefinition = $this->parseDockerCompose($this->docker_compose_path->toString());
        $this->name = $this->composeDefinition->getProjectName() !== null
            ? XString::new($this->composeDefinition->getProjectName())
            : null;

        return $this;
    }

    public function fromDockerContainerName(string $name): DockerManager
    {
        if ($this->from_is_already_called) {
            throw new RuntimeException("a 'from' method has already been called.");
        }

        $this->from_is_already_called = true;
        $this->setName($name);
        return $this;
    }

    public function fromDockerfile(string $dockerfile_path, string $serviceName = 'app'): DockerManager
    {
        if ($this->from_is_already_called) {
            throw new RuntimeException("a 'from' method has already been called.");
        }

        $this->from_is_already_called = true;

        $xpath = XString::trim($dockerfile_path);

        if ($xpath->isEmpty()) {
            throw new InvalidArgumentException("Dockerfile path cannot be empty.");
        }

        $xpath = $xpath->replace(['\\', '/'], DIRECTORY_SEPARATOR);

        if (!is_file($xpath->toString())) {
            throw new InvalidArgumentException("Dockerfile not found at path: {$xpath}");
        }

        $this->dockerfile_path = $xpath;
        $directory = $xpath->before(DIRECTORY_SEPARATOR, true)->append(DIRECTORY_SEPARATOR);
        $this->docker_compose_dir = $directory;
        $dockerfile = $xpath->after($directory->toString(), false)->toString();
        $this->composeDefinition = ComposeDefinition::fromDockerfile($serviceName, $directory->toString(), $dockerfile);

        return $this;
    }

    public function setEnvVariable(string $key, string $value): DockerManager
    {
        $this->env_variables[$key] = $value;
        return $this;
    }

    public function injectVariable(string $key, string $value): DockerManager
    {
        return $this->setEnvVariable($key, $value);
    }

    public function setEnvVariables(array $vars): DockerManager
    {
        foreach ($vars as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new InvalidArgumentException("Environment variable keys and values must be strings.");
            }

            $this->setEnvVariable($key, $value);
        }
        return $this;
    }

    public function setDockerComposeValue(array $values): DockerManager
    {
        if ($this->composeDefinition === null) {
            $this->composeDefinition = new ComposeDefinition();
        }

        $this->composeDefinition->merge($values);
        return $this;
    }

    public function setDockerComposeDir(string $path): DockerManager
    {
        $dir = XString::new($path)->trim();
        if ($dir->isEmpty()) {
            throw new InvalidArgumentException('Docker compose directory cannot be empty.');
        }

        $dir = $dir->replace(['\\', '/'], DIRECTORY_SEPARATOR);
        if (!is_dir($dir->toString())) {
            throw new InvalidArgumentException("Docker compose directory does not exist: {$dir}");
        }

        $dir = $dir->ensureEndsWith(DIRECTORY_SEPARATOR);
        $this->docker_compose_dir = $dir;
        return $this;
    }

    public function setDebugPath(?string $path): DockerManager
    {
        $this->debug_path = $this->parseDebugPath($path);
        return $this;
    }

    public function onProgress(?callable $callback = null): DockerManager
    {
        $this->progress_callback = $callback;
        return $this;
    }

    public function setProcessRunner(ProcessRunnerInterface $runner): DockerManager
    {
        $this->process_runner = $runner;
        return $this;
    }

    public function getProcessRunner(): ProcessRunnerInterface
    {
        return $this->process_runner;
    }

    public function start(bool $rebuild_containers = false, bool $save_logs = false): bool
    {
        if ($this->composeDefinition === null) {
            throw new RuntimeException('Cannot start containers without a docker compose definition.');
        }

        $this->docker_output = [];
        $this->last_parsed_chunk = null;

        $composePath = $this->writeTemporaryComposeFile();

        try {
            $command = $this->buildComposeUpCommand($composePath, $rebuild_containers);
            $context = $this->createProcessContext($command, $save_logs);
            $result = $this->process_runner->run($context, function (string $output): void {
                $this->handleDockerOutput($output);
            });

            $this->last_exit_code = $result->getExitCode();
            $this->last_output = $this->readOutputFile($result);

            if ($result->hasTimedOut()) {
                $this->docker_output['errors'][] = 'Docker compose command timed out.';
            }

            $this->handleDebugArtifacts($composePath, $result->getLogFilePath());
            $this->cleanupLogFile($result->getLogFilePath(), $save_logs);

            if ($this->last_exit_code !== 0 && empty($this->docker_output['errors'])) {
                $this->docker_output['errors'][] = $this->formatFallbackError($command, $context, $result, $composePath);
            }

            if (!empty($this->docker_output['errors'])) {
                return false;
            }

            if ($this->last_exit_code !== 0) {
                return false;
            }

            $this->waitForHealthOfServices(120);
            return true;
        } finally {
            @unlink($composePath);
        }
    }

    public function stop(): bool
    {
        if ($this->composeDefinition !== null) {
            $composePath = $this->writeTemporaryComposeFile();
            try {
                $command = $this->buildComposeDownCommand($composePath);
                $context = $this->createProcessContext($command, false);
                $result = $this->process_runner->run($context, function (string $output): void {
                    $this->handleDockerOutput($output);
                });

                $this->last_exit_code = $result->getExitCode();
                $this->last_output = $this->readOutputFile($result);
                $this->handleDebugArtifacts($composePath, $result->getLogFilePath());
                $this->cleanupLogFile($result->getLogFilePath(), false);

                return $this->last_exit_code === 0;
            } finally {
                @unlink($composePath);
            }
        }

        if ($this->name === null) {
            throw new RuntimeException('Cannot stop containers without a compose definition or container name.');
        }

        $command = $this->buildDirectDockerCommand('docker stop ' . escapeshellarg($this->name->toString()));
        $context = $this->createProcessContext($command, false);
        $result = $this->process_runner->run($context, function (string $output): void {
            $this->handleDockerOutput($output);
        });

        $this->last_exit_code = $result->getExitCode();
        $this->last_output = $this->readOutputFile($result);
        $this->cleanupLogFile($result->getLogFilePath(), false);

        return $this->last_exit_code === 0;
    }

    public function getErrors(): array
    {
        return $this->docker_output['errors'] ?? [];
    }

    public function hasPortInUseError(): bool
    {
        foreach ($this->docker_output['errors'] ?? [] as $error) {
            $x = XString::new($error);
            if ($x->trim()->endsWith('failed: port is already allocated')) {
                return true;
            }
            if ($x->contains('address already in use')) {
                return true;
            }
        }
        return false;
    }

    public function getDockerCompose(): ?array
    {
        return $this->composeDefinition?->toArray();
    }

    public function getDockerComposePath(): ?string
    {
        return $this->docker_compose_path?->toString();
    }

    public function getDockerComposeDir(): ?string
    {
        return $this->docker_compose_dir?->toString();
    }

    public function getEnvVariables(): array
    {
        return $this->env_variables;
    }

    public function getName(): ?string
    {
        return $this->name?->toString();
    }

    public function getLastOutput(): ?string
    {
        return $this->last_output;
    }

    public function getLastExitCode(): ?int
    {
        return $this->last_exit_code;
    }

    public function getDockerfilePath(): ?string
    {
        return $this->dockerfile_path?->toString();
    }

    public function getServices(): array
    {
        if ($this->composeDefinition === null) {
            return [];
        }

        return $this->composeDefinition->getServices();
    }

    public function hasService(string $service): bool
    {
        return $this->composeDefinition?->hasService($service) ?? false;
    }

    public function ensureService(string $service): DockerManager
    {
        if ($this->composeDefinition === null) {
            $this->composeDefinition = new ComposeDefinition();
        }

        $this->composeDefinition->ensureService($service);
        return $this;
    }

    public function setService(string $service, array $config): DockerManager
    {
        $this->ensureDefinition()->setService($service, $config);
        return $this;
    }

    public function updateService(string $service, array $config): DockerManager
    {
        $this->ensureDefinition()->updateService($service, $config);
        return $this;
    }

    public function removeService(string $service): DockerManager
    {
        $this->composeDefinition?->removeService($service);
        return $this;
    }

    public function renameService(string $oldName, string $newName): DockerManager
    {
        $this->ensureDefinition()->renameService($oldName, $newName);
        return $this;
    }

    public function getServiceConfig(string $service): ?array
    {
        return $this->composeDefinition?->getService($service);
    }

    public function setContainerName(string $service, string $containerName): DockerManager
    {
        $this->ensureDefinition()->setContainerName($service, $containerName);
        return $this;
    }

    public function getContainerName(string $service): ?string
    {
        return $this->composeDefinition?->getContainerName($service);
    }

    public function setBuildContext(string $service, string $context): DockerManager
    {
        $this->ensureDefinition()->setBuildContext($service, $context);
        return $this;
    }

    public function getBuildContext(string $service): ?string
    {
        return $this->composeDefinition?->getBuildContext($service);
    }

    public function setDockerfileForService(string $service, string $dockerfile): DockerManager
    {
        $this->ensureDefinition()->setDockerfile($service, $dockerfile);
        return $this;
    }

    public function getDockerfileForService(string $service): ?string
    {
        return $this->composeDefinition?->getDockerfile($service);
    }

    public function setImage(string $service, string $image): DockerManager
    {
        $this->ensureDefinition()->setImage($service, $image);
        return $this;
    }

    public function getImage(string $service): ?string
    {
        return $this->composeDefinition?->getImage($service);
    }

    public function setPorts(string $service, array $ports): DockerManager
    {
        $this->ensureDefinition()->setPorts($service, $ports);
        return $this;
    }

    public function addPort(string $service, string $port): DockerManager
    {
        $this->ensureDefinition()->addPort($service, $port);
        return $this;
    }

    public function getPorts(string $service): array
    {
        return $this->composeDefinition?->getPorts($service) ?? [];
    }

    public function setServiceEnvironmentVariable(string $service, string $name, string $value): DockerManager
    {
        $this->ensureDefinition()->setEnvironmentVariable($service, $name, $value);
        return $this;
    }

    public function setServiceEnvironmentVariables(string $service, array $variables): DockerManager
    {
        $this->ensureDefinition()->setEnvironmentVariables($service, $variables);
        return $this;
    }

    public function getServiceEnvironmentVariables(string $service): array
    {
        return $this->composeDefinition?->getEnvironmentVariables($service) ?? [];
    }

    public function setDependsOn(string $service, array $dependencies): DockerManager
    {
        $this->ensureDefinition()->setDependsOn($service, $dependencies);
        return $this;
    }

    public function getDependsOn(string $service): array
    {
        return $this->composeDefinition?->getDependsOn($service) ?? [];
    }

    public function setName(string $name): DockerManager
    {
        $xname = XString::trim($name);
        if ($xname->isEmpty()) {
            throw new InvalidArgumentException('Container name cannot be empty.');
        }

        $this->name = $xname;
        if ($this->composeDefinition !== null) {
            $this->composeDefinition->setProjectName($xname->toString());
            foreach ($this->composeDefinition->getServices() as $service) {
                $this->composeDefinition->setContainerName($service, $xname->toString() . '-' . $service);
            }
        }

        return $this;
    }

    public function setComposeVersion(string $version): DockerManager
    {
        $this->setDockerComposeValue(['version' => $version]);
        return $this;
    }

    public function getComposeVersion(): ?string
    {
        $compose = $this->composeDefinition?->toArray();
        if ($compose === null) {
            return null;
        }

        $version = $compose['version'] ?? null;
        return is_string($version) ? $version : null;
    }

    ################
    ## Internals ##
    ################

    private function ensureDefinition(): ComposeDefinition
    {
        if ($this->composeDefinition === null) {
            $this->composeDefinition = new ComposeDefinition();
        }

        return $this->composeDefinition;
    }

    private function handleDockerOutput(string $output): void
    {
        if ($output === $this->last_parsed_chunk) {
            return;
        }

        $this->last_parsed_chunk = $output;
        $parsed = $this->output_parser->parse($output);
        if ($parsed !== []) {
            $this->docker_output = $parsed;
            if (is_callable($this->progress_callback)) {
                call_user_func($this->progress_callback, $parsed);
            }
        }
    }

    private function readOutputFile(ProcessResult $result): ?string
    {
        $logPath = $result->getLogFilePath();
        if ($logPath === null || !is_file($logPath)) {
            return null;
        }

        return (string) file_get_contents($logPath);
    }

    private function formatFallbackError(string $command, ProcessContext $context, ProcessResult $result, ?string $composePath = null): string
    {
        $exitCode = $this->last_exit_code ?? $result->getExitCode() ?? -1;
        $workingDir = $context->getWorkingDirectory();
        $message = sprintf(
            'Docker compose command "%s" exited with code %d while running in "%s".',
            $command,
            $exitCode,
            $workingDir
        );

        if ($composePath !== null) {
            $message .= ' Temporary compose file: ' . $composePath . '.';
        }

        $logPath = $result->getLogFilePath();
        if ($logPath !== null) {
            $message .= ' Captured log file: ' . $logPath . '.';
        }

        $summary = $this->summarizeOutputForError($this->last_output);
        if ($summary !== null) {
            $message .= ' Last output: ' . $summary;
        }

        return $message;
    }

    private function summarizeOutputForError(?string $output): ?string
    {
        if ($output === null) {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        $lines = array_values(array_filter(array_map(static function ($line) {
            return trim((string) $line);
        }, $lines), static function ($line) {
            return $line !== '';
        }));

        if ($lines === []) {
            return null;
        }

        $firstLine = $lines[0];
        $lastLine = $lines[count($lines) - 1];

        if (count($lines) === 1 || $firstLine === $lastLine) {
            return $this->truncateErrorSummary($firstLine);
        }

        $summary = $firstLine . ' ... ' . $lastLine;
        return $this->truncateErrorSummary($summary);
    }

    private function truncateErrorSummary(string $summary): string
    {
        if (strlen($summary) <= 500) {
            return $summary;
        }

        return substr($summary, 0, 497) . '...';
    }

    private function handleDebugArtifacts(string $composePath, ?string $logPath): void
    {
        if ($this->debug_path === null) {
            return;
        }

        $debugDir = $this->debug_path->toString();
        if (!is_dir($debugDir)) {
            mkdir($debugDir, 0775, true);
        }

        $timestamp = date('Ymd-His');
        $baseName = 'docker-manager-' . $timestamp . '-' . uniqid('', true);
        $composeTarget = $debugDir . DIRECTORY_SEPARATOR . $baseName . '-compose.yml';
        copy($composePath, $composeTarget);

        if ($logPath !== null && is_file($logPath)) {
            $logTarget = $debugDir . DIRECTORY_SEPARATOR . $baseName . '.log';
            copy($logPath, $logTarget);
        }
    }

    private function cleanupLogFile(?string $logPath, bool $saveLogs): void
    {
        if ($logPath === null) {
            return;
        }

        if ($saveLogs || $this->debug_path !== null) {
            return;
        }

        if (is_file($logPath)) {
            @unlink($logPath);
        }
    }

    private function parseDockerCompose(string $compose_path): ComposeDefinition
    {
        switch ($this->yaml_parser_raw) {
            case 'ext':
                if (!function_exists('yaml_parse_file')) {
                    throw new RuntimeException('ext-yaml is required when using the "ext" YAML parser option.');
                }
                /** @disregard P1010 yaml_parse_file comes from optional ext-yaml */
                $parsed = yaml_parse_file($compose_path);
                break;
            case 'symfony':
                /** @disregard P1009 Symfony\\Component\\Yaml\\Yaml is an optional dependency */
                if (!class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                    throw new RuntimeException('symfony/yaml must be installed to use the "symfony" YAML parser option.');
                }
                /** @disregard P1009 Symfony\\Component\Yaml\Yaml is an optional dependency */
                $parsed = \Symfony\Component\Yaml\Yaml::parseFile($compose_path);
                break;
            default:
                throw new RuntimeException('Unsupported YAML parser.');
        }

        if (!is_array($parsed)) {
            throw new RuntimeException('Docker compose file could not be parsed into an array.');
        }

        return ComposeDefinition::fromArray($parsed);
    }

    private function writeDockerCompose(string $target_path): void
    {
        $data = $this->composeDefinition?->toArray() ?? [];
        $data = $this->normalizeComposeDefinitionForWrite($data);
        switch ($this->yaml_parser_raw) {
            case 'ext':
                if (!function_exists('yaml_emit_file')) {
                    throw new RuntimeException('ext-yaml is required when using the "ext" YAML parser option.');
                }
                /** @disregard P1010 yaml_emit_file comes from optional ext-yaml */
                $result = yaml_emit_file($target_path, $data);
                if ($result === false) {
                    throw new RuntimeException('Failed to write temporary docker compose file.');
                }
                return;
            case 'symfony':
                /** @disregard P1009 Symfony\\Component\Yaml\Yaml is an optional dependency */
                if (!class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                    throw new RuntimeException('symfony/yaml must be installed to use the "symfony" YAML parser option.');
                }
                /** @disregard P1009 Symfony\\Component\Yaml\Yaml is an optional dependency */
                $yaml = \Symfony\Component\Yaml\Yaml::dump($data, 10, 2);
                if (file_put_contents($target_path, $yaml) === false) {
                    throw new RuntimeException('Failed to write temporary docker compose file.');
                }
                return;
        }

        throw new RuntimeException('Unsupported YAML parser.');
    }

    private function normalizeComposeDefinitionForWrite(array $data): array
    {
        if ($this->docker_compose_dir === null) {
            return $data;
        }

        if (!isset($data['services']) || !is_array($data['services'])) {
            return $data;
        }

        $baseDir = rtrim($this->docker_compose_dir->toString(), DIRECTORY_SEPARATOR);

        foreach ($data['services'] as $serviceName => &$service) {
            if (!is_array($service) || !array_key_exists('build', $service)) {
                continue;
            }

            if (is_string($service['build'])) {
                $buildContext = $service['build'];
                if ($this->containsComposeVariable($buildContext)) {
                    continue;
                }

                $contextPath = $this->resolveComposePath($buildContext, $baseDir);
                $this->assertBuildContextDirectoryExists($serviceName, $buildContext, $contextPath);
                $this->assertDefaultDockerfileExists($serviceName, $buildContext, $contextPath);
                $service['build'] = $contextPath;
                continue;
            }

            if (!is_array($service['build'])) {
                continue;
            }

            $build = $service['build'];
            $contextValue = $build['context'] ?? '.';
            if (!is_string($contextValue) || $contextValue === '') {
                $contextValue = '.';
            }

            $contextPath = null;
            if (!$this->containsComposeVariable($contextValue)) {
                $contextPath = $this->resolveComposePath($contextValue, $baseDir);
                $this->assertBuildContextDirectoryExists($serviceName, $contextValue, $contextPath);
                $build['context'] = $contextPath;
            }

            if ($contextPath !== null) {
                $dockerfileValue = $build['dockerfile'] ?? null;
                if (
                    is_string($dockerfileValue)
                    && $dockerfileValue !== ''
                    && !$this->containsComposeVariable($dockerfileValue)
                ) {
                    $dockerfilePath = $this->resolveComposePath($dockerfileValue, $contextPath);
                    $this->assertDockerfileExists($serviceName, $dockerfileValue, $dockerfilePath, $contextPath);
                } elseif (!is_string($dockerfileValue) || trim($dockerfileValue) === '') {
                    $declaredContext = is_string($contextValue) ? $contextValue : '.';
                    $this->assertDefaultDockerfileExists($serviceName, $declaredContext, $contextPath);
                }
            }

            $service['build'] = $build;
        }

        unset($service);

        return $data;
    }

    private function containsComposeVariable(string $value): bool
    {
        return strpos($value, '${') !== false;
    }

    private function resolveComposePath(string $value, string $baseDir): string
    {
        $normalized = trim($value);
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $normalized);

        if ($normalized === '' || $normalized === '.') {
            return $this->normalizeRealPath($baseDir) ?? $baseDir;
        }

        if ($this->isAbsolutePath($normalized)) {
            return $this->normalizeRealPath($normalized) ?? $normalized;
        }

        $candidate = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
        return $this->normalizeRealPath($candidate) ?? $candidate;
    }

    private function normalizeRealPath(string $path): ?string
    {
        $real = realpath($path);
        if ($real === false) {
            return null;
        }

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $real);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':';
    }

    private function assertBuildContextDirectoryExists(string $service, string $declared, string $resolved): void
    {
        if (is_dir($resolved)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Service "%s" build context directory not found. Declared "%s" resolved to "%s".',
            $service,
            $declared,
            $resolved
        ));
    }

    private function assertDockerfileExists(string $service, string $declared, string $resolved, string $contextPath): void
    {
        if (is_file($resolved)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Service "%s" dockerfile not found. Declared "%s" relative to "%s" resolved to "%s".',
            $service,
            $declared,
            $contextPath,
            $resolved
        ));
    }

    private function assertDefaultDockerfileExists(string $service, string $declaredContext, string $resolvedContext): void
    {
        $expected = rtrim($resolvedContext, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Dockerfile';
        if (is_file($expected)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Service "%s" dockerfile not found. Declared build context "%s" resolved to "%s" but default dockerfile expected at "%s".',
            $service,
            $declaredContext,
            $resolvedContext,
            $expected
        ));
    }

    private function getYamlParser(string $yaml_parser): string
    {
        $parser = XString::trim($yaml_parser)->toLowerCase();

        if ($parser->isEmpty() || $parser->equals('ext')) {
            if (!extension_loaded('yaml')) {
                throw new RuntimeException('YAML extension is not loaded. Please install/enable the YAML PHP extension.');
            }
            return 'ext';
        }

        if ($parser->equals('symfony')) {
            if (!class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
                throw new RuntimeException('Symfony YAML component is not installed. Please install it via Composer.');
            }
            return 'symfony';
        }

        throw new InvalidArgumentException("Unsupported YAML parser specified: {$parser}, supported: 'ext', 'symfony'.");
    }

    private function parseDockerComposePath(string $path): XString
    {
        $xpath = XString::trim($path);

        if ($xpath->isEmpty()) {
            throw new InvalidArgumentException('Docker compose path cannot be empty.');
        }

        $xpath = $xpath->replace(['\\', '/'], DIRECTORY_SEPARATOR);

        if (!is_file($xpath->toString())) {
            throw new InvalidArgumentException("Docker compose file not found at path: {$xpath}");
        }

        $this->docker_compose_dir = $xpath->before(DIRECTORY_SEPARATOR, true)->append(DIRECTORY_SEPARATOR);

        return $xpath;
    }

    public function parseDebugPath(?string $path): ?XString
    {
        if ($path === null) {
            return null;
        }

        $xpath = XString::trim($path);

        if ($xpath->isEmpty()) {
            return null;
        }

        $xpath = $xpath->replace(['\\', '/'], DIRECTORY_SEPARATOR);

        return $xpath;
    }

    private function buildProcessEnv(): array
    {
        $env = getenv();
        if (!is_array($env)) {
            $env = [];
        }

        foreach ($this->env_variables as $key => $value) {
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
            @exec($bin . ' version > /dev/null 2>&1', $out, $code);
            if ($code === 0) {
                return $bin;
            }
        }
        return 'docker-compose';
    }

    private function buildComposeUpCommand(string $composePath, bool $rebuild): string
    {
        $baseCommand = $this->buildComposeCommandPrefix($composePath);

        $command = $rebuild
            ? $baseCommand . ' build --no-cache && ' . $baseCommand . ' up -d --force-recreate --renew-anon-volumes'
            : $baseCommand . ' up -d';

        return $this->wrapCommandForShell($command);
    }

    private function buildComposeDownCommand(string $composePath): string
    {
        $command = $this->buildComposeCommandPrefix($composePath) . ' down';

        return $this->wrapCommandForShell($command);
    }

    private function buildComposeCommandPrefix(string $composePath): string
    {
        $composeBin = $this->detectComposeBin();
        $command = $composeBin;

        if ($this->docker_compose_dir !== null) {
            $projectDirectory = rtrim($this->docker_compose_dir->toString(), DIRECTORY_SEPARATOR);
            $command .= ' --project-directory ' . $this->quotePathForCompose($projectDirectory);
        }

        $command .= ' -f ' . $this->quotePathForCompose($composePath);

        return $command;
    }

    private function buildDirectDockerCommand(string $command): string
    {
        return $this->wrapCommandForShell($command);
    }

    private function wrapCommandForShell(string $command): string
    {
        if ($this->isWindows()) {
            return 'cmd.exe /C ' . $command;
        }

        return '/bin/sh -lc ' . escapeshellarg($command);
    }

    private function quotePathForCompose(string $path): string
    {
        if ($this->isWindows()) {
            return '"' . $path . '"';
        }

        return escapeshellarg($path);
    }

    private function createProcessContext(string $command, bool $keepLog): ProcessContext
    {
        $workingDir = $this->docker_compose_dir?->toString();
        if ($workingDir === null) {
            $workingDir = getcwd() ?: '.';
        }

        if (!is_dir($workingDir)) {
            throw new RuntimeException(sprintf('Docker compose working directory does not exist: %s', $workingDir));
        }

        return new ProcessContext(
            $command,
            $workingDir,
            $this->buildProcessEnv(),
            $this->isWindows(),
            $keepLog || $this->debug_path !== null,
            $this->debug_path?->toString()
        );
    }

    private function writeTemporaryComposeFile(): string
    {
        if ($this->composeDefinition === null) {
            throw new RuntimeException('No compose definition available to write.');
        }

        $tmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'docker-compose-' . uniqid('', true) . '.yml';
        $this->writeDockerCompose($tmp);
        return $tmp;
    }

    private function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    private function waitForHealthOfServices(int $timeoutSeconds): void
    {
        $services = $this->composeDefinition?->toArray()['services'] ?? [];
        if (!is_array($services)) {
            return;
        }

        $containersToWatch = [];
        foreach ($services as $serviceName => $config) {
            if (!is_array($config)) {
                continue;
            }
            if (!isset($config['healthcheck'])) {
                continue;
            }
            $containerName = $config['container_name'] ?? null;
            if (is_string($containerName) && $containerName !== '') {
                $containersToWatch[] = $containerName;
            }
        }

        if ($containersToWatch === []) {
            return;
        }

        $start = time();
        foreach ($containersToWatch as $container) {
            while (true) {
                $cmd = $this->isWindows()
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
                    throw new RuntimeException("Container '{$container}' did not become healthy in {$timeoutSeconds} seconds.");
                }

                usleep(500000);
            }
        }
    }
}
