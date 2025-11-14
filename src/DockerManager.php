<?php

namespace Orryv;

use InvalidArgumentException;
use Orryv\Config;
use Orryv\XString;
use Orryv\XStringType;
use RuntimeException;
use Orryv\Path;


/*
TODO:
implement:
public function getDockerCompose(): ?array
public function getDockerComposePath(): ?string
public function getDockerComposeDir(): ?string
public function getEnvVariables(): array
public function getName(): ?string
public function getLastOutput(): ?string
public function getLastExitCode(): ?int

*/

class DockerManager
{
    private ?XString $docker_compose_path = null;
    private ?XString $docker_compose_dir = null;
    private ?XString $name = null;

    private string $yaml_parser_raw;
    private ?array $docker_compose = null;
    private array $env_variables = [];
    private ?XString $debug_path = null; // outputs tmp docker-compose and docker console output dump to this path.
    private ?XString $dockerfile_path = null; // used when fromDockerfile is called.

    private bool $from_is_already_called = false;

    public function __construct(string $yaml_parser = 'ext')
    {
        $this->yaml_parser_raw = $this->getYamlParser($yaml_parser);
    }

    public function fromDockerCompose(string $docker_compose_full_path): DockerManager
    {
        if ($this->from_is_already_called) {
            throw new RuntimeException("a 'from' method has already been called.");
        }

        $this->from_is_already_called = true;

        $this->docker_compose_path = $this->parseDockerComposePath($docker_compose_full_path);
        $this->docker_compose = $this->parseDockerCompose($this->docker_compose_path->toString());
        return $this;
    }

    public function fromDockerContainerName(string $name): DockerManager
    {
        if ($this->from_is_already_called) {
            throw new RuntimeException("a 'from' method has already been called.");
        }

        $this->from_is_already_called = true;

        $this->name = XString::trim($name);
        return $this;
    }

    public function fromDockerfile(string $dockerfile_path): DockerManager
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
        $this->docker_compose_path = null;
        $this->docker_compose_dir = $xpath->before(DIRECTORY_SEPARATOR, true)->append(DIRECTORY_SEPARATOR);

        return $this;
    }

    public function setEnvVariable(string $key, string $value): DockerManager
    {
        $this->env_variables[$key] = $value;
        return $this;
    }

    public function setEnvVariables(array $vars): DockerManager
    {
        foreach ($vars as $key => $value) {
            if(!is_string($key) || !is_string($value)){
                throw new InvalidArgumentException("Environment variable keys and values must be strings.");
            }

            $this->setEnvVariable($key, $value);
        }
        return $this;
    }

    public function setDockerComposeValue(array $values): DockerManager
    {
        if ($this->docker_compose === null) {
            $this->docker_compose = [];
        }

        $this->docker_compose = array_merge_recursive($this->docker_compose, $values);
        return $this;
    }

    public function setDebugPath(?string $path): DockerManager
    {
        $this->debug_path = $this->parseDebugPath($path);
        return $this;
    }

    public function start($rebuild_containers = false): bool
    {
        // Implementation of starting Docker containers goes here.
        return true;
    }

    public function stop(): bool
    {
        // Implementation of stopping Docker containers goes here.
        return true;
    }

    public function getErrors(): array
    {
        // Implementation of error retrieval goes here.
        return [];
    }

    public function hasPortInUseError(): bool
    {
        // Implementation to check for port in use errors goes here.
        return false;
    }

    ##############
    ## Internal ##
    ##############

    private function parseDockerCompose(string $compose_path): array
    {
        switch ($this->yaml_parser_raw) {
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

    private function getYamlParser(string $yaml_parser): string
    {
        $parser = XString::trim($yaml_parser)->toLowerCase();

        if ($parser->isEmpty() || $parser->equals('ext')) {
            if (!extension_loaded('yaml')) {
                throw new RuntimeException("YAML extension is not loaded. Please install/enable the YAML PHP extension.");
            }
            return 'ext';
        }

        if ($parser->equals('symfony')) {
            if (!class_exists('\Symfony\Component\Yaml\Yaml')) {
                throw new RuntimeException("Symfony YAML component is not installed. Please install it via Composer.");
            }
            return 'symfony';
        }

        throw new InvalidArgumentException("Unsupported YAML parser specified: {$parser}, supported: 'ext', 'symfony'.");
    }

    private function parseDockerComposePath(string $path): XString
    {
        $xpath = XString::trim($path);

        if($xpath->isEmpty()){
            throw new InvalidArgumentException("Docker compose path cannot be empty.");
        }

        $xpath = $xpath->replace(['\\', '/'], DIRECTORY_SEPARATOR);

        if(!is_file($xpath->toString())){
            throw new InvalidArgumentException("Docker compose file not found at path: {$xpath}");
        }

        $this->docker_compose_dir = $xpath->before(DIRECTORY_SEPARATOR, true)->append(DIRECTORY_SEPARATOR);

        return $xpath;
    }

    public function parseDebugPath(?string $path): ?XString
    {
        if($path === null){
            return null;
        }

        $xpath = XString::trim($path);

        if($xpath->isEmpty()){
            return null;
        }

        $xpath = $xpath->replace(['\\', '/'], DIRECTORY_SEPARATOR);

        return $xpath;
    }
}