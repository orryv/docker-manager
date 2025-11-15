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
- implement:
public function getDockerCompose(): ?array
public function getDockerComposePath(): ?string
public function getDockerComposeDir(): ?string
public function getEnvVariables(): array
public function getName(): ?string
public function getLastOutput(): ?string
public function getLastExitCode(): ?int

- Keep track of all the temporary files we create (docker-compose overrides, dockerfiles, logs, etc) and provide a method to clean them up (best in __destruct)

- Also implement "helper" methods which update docker-compose values, like (but not limited to, I'm sure you can think of way more we can implement):
setName($name) // the name above the services (to group them)
setContainerName($target_service, $name)
setServiceName($target_service, $name)
setPortMapping($target_service, $container_port, $host_port, $protocol = 'tcp') // can be called multiple times for multiple ports
setCpus($target_service, $cpus)
setMemoryLimit($target_service, $memory_limit)
setEnvironmentVariable($target_service, $key, $value) 

Note: in these helper methods we need to set which service we are targeting in the docker-compose file.

- Also think of other helpful methods, I'm thinking about:
usesHostPorts(): ?array // returns a list of host ports used by the container (if any)
usesContainerPorts(): ?array // returns a list of container ports used by the container (if any)
usesPorts(): ?array // returns an associative array of host_port => container_port
getErrors(): array // returns an array of errors encountered during the last run (see DockerManagerOld.php to see how we should collect the errors)
Which helpful methods do you think about? implement them?


*/

class DockerManager
{
    private ?XString $docker_compose_path = null;
    private ?XString $docker_compose_dir = null;
    private ?XString $name = null;

    private string $yaml_parser_raw;
    private ?array $docker_compose = null;
    private array $env_variables = [];
    private bool $debug = false; // outputs tmp docker-compose and docker console output dump to the path where docker-compose.yml is located.
    private ?XString $dockerfile_path = null; // used when fromDockerfile is called.

    private bool $from_is_already_called = false;

    private bool $from_docker_compose_is_called = false;
    private bool $from_dockerfile_is_called = false;
    private bool $from_docker_container_name_is_called = false;

    public function __construct(string $yaml_parser = 'ext')
    {
        $this->yaml_parser_raw = $this->getYamlParser($yaml_parser);
    }

    public function fromDockerCompose(string $docker_compose_full_path): DockerManager
    {
        if ($this->from_is_already_called) {
            throw new RuntimeException("a 'from' method has already been called.");
        }

        $this->from_docker_compose_is_called = true;
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

        $this->from_docker_container_name_is_called = true;
        $this->from_is_already_called = true;

        $this->name = XString::trim($name);
        return $this;
    }

    public function fromDockerfile(string $dockerfile_path): DockerManager
    {
        if ($this->from_is_already_called) {
            throw new RuntimeException("a 'from' method has already been called.");
        }

        $this->from_dockerfile_is_called = true;
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

    /**
     * Sets a single environment variable. (so variables can be used in docker-compose.yml)
     */
    public function setEnvVariable(string $key, string $value): DockerManager
    {
        $this->env_variables[$key] = $value;
        return $this;
    }

    /**
     * Sets multiple environment variables at once. (so variables can be used in docker-compose.yml)
     */
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

    /**
     * Merges values into the docker-compose configuration array.
     * Ex.: ['services' => ['web' => ['ports' => ['8080:80']]] ] will set port mapping for 'web' service.
     */
    public function setDockerComposeValue(array $values): DockerManager
    {
        if ($this->docker_compose === null) {
            $this->docker_compose = [];
        }

        $this->docker_compose = array_merge_recursive($this->docker_compose, $values);
        return $this;
    }

    public function debug(bool $enabled = true): DockerManager
    {
        $this->debug = $enabled;
        return $this;
    }

    /**
     * Returns wherhet the docker container exists.
     */
    public function exists(): bool
    {
        // TODO: Implement
        return true;
    }

    /**
     * Returns whether the docker container is running.
     */
    public function isRunning(): bool
    {
        // TODO: Implement
        return true;
    }

    /**
     * Removes the docker container.
     * 
     * @param bool        $removeVolumes  Whether to remove associated volumes.
     * @param string|null $removeImages   'local'|'all' for docker compose, or null
     */
    public function remove(bool $removeVolumes = false, ?string $removeImages = null): bool
    {
        // TODO: Implement
        return true;
    }

    /**
     * Resets the docker container by stopping and removing it, then rebuilding and starting it again.
     * 
     * @param bool        $removeVolumes     Whether to remove associated volumes.
     * @param string|null $removeImages      'local'|'all' for docker compose, or null
     * @param bool        $rebuildContainers Whether to rebuild the containers.
     * @param bool        $saveLogs          Whether to save logs before resetting. (in the same folder as docker-compose.yml)
     */
    public function reset(
        bool $removeVolumes = false,
        ?string $removeImages = null,
        bool $rebuildContainers = false,
        bool $saveLogs = false
    ): bool {
        if($this->from_docker_container_name_is_called){
            throw new RuntimeException("Reset is not supported when using fromDockerContainerName().");
        }

        if($this->from_dockerfile_is_called){
            throw new RuntimeException("Reset is not supported when using fromDockerfile().");
        }

        // TODO: Implement
        return true;
    }

    public function start(): bool
    {
        if($this->from_docker_container_name_is_called){
            throw new RuntimeException("Start is not supported when using fromDockerContainerName().");
        }
        if($this->from_dockerfile_is_called){
            throw new RuntimeException("Start is not supported when using fromDockerfile().");
        }

        // TODO: Implement
        return true;
    }

    public function stop(): bool
    {
        // TODO: Implement
        return true;
    }

    public function getErrors(): array
    {
        // TODO: Implement
        return [];
    }

    public function hasPortInUseError(): bool
    {
        // TODO: Implement
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
}