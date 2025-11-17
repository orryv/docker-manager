<?php

namespace Orryv\DockerComposeManager\Config;

use Orryv\DockerComposeManager\State\ContainerState;
use Orryv\DockerComposeManager\Yaml\YamlAdapter;
use Psr\Log\LoggerInterface;

/**
 * Rich configuration object for each compose registration that stores the raw
 * docker-compose definition, environment overrides, callbacks and temporary
 * artifacts that should be used during runtime operations.
 */
class DockerComposeConfig
{
    public const TYPE_FILE = 'file';
    public const TYPE_ARRAY = 'array';
    public const TYPE_CONTAINER = 'container';
    public const TYPE_PROJECT = 'project';

    private string $id;
    private string $workingDirectory;
    private array $dockerCompose;
    private ?string $sourceFile;
    private string $type;

    /** @var array<string,string> */
    private array $envVariables = [];

    private ?LoggerInterface $logger = null;

    /** @var callable|null */
    private $progressCallback = null;
    private int $progressInterval = 250;

    /** @var callable|null */
    private $successCallback = null;

    /** @var callable|null */
    private $errorCallback = null;

    private ?string $debugPath = null;

    /** @var array<int,string> */
    private array $temporaryFiles = [];

    private ?ContainerState $state = null;

    private bool $startable;

    /**
     * Build a configuration instance for a compose file/project/container.
     *
     * @param string $id User-facing identifier.
     * @param string $workingDirectory Directory used for relative commands.
     * @param array<string,mixed>|null $dockerCompose Optional compose structure.
     * @param string $type How the config was registered (file, array, etc.).
     * @param string|null $sourceFile Optional reference to the original compose file.
     */
    public function __construct(
        string $id,
        string $workingDirectory,
        ?array $dockerCompose = null,
        string $type = self::TYPE_ARRAY,
        ?string $sourceFile = null
    ) {
        $this->id = $id;
        $this->workingDirectory = rtrim($workingDirectory, DIRECTORY_SEPARATOR);
        $this->dockerCompose = $dockerCompose ?? [];
        $this->type = $type;
        $this->sourceFile = $sourceFile;
        $this->startable = in_array($type, [self::TYPE_FILE, self::TYPE_ARRAY], true);
    }

    /**
     * Ensure temporary files are cleaned up when the config object is destroyed.
     */
    public function __destruct()
    {
        $this->removeTmpFiles();
    }

    /**
     * @return string Identifier assigned at registration.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string Directory where docker-compose commands should run.
     */
    public function getWorkingDirectory(): string
    {
        return $this->workingDirectory;
    }

    /**
     * @return string|null Path to the original compose file when registered from disk.
     */
    public function getSourceFile(): ?string
    {
        return $this->sourceFile;
    }

    /**
     * @return string Registration type (file/array/container/project).
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Whether this configuration can run docker-compose commands (files/arrays only).
     */
    public function canManageContainers(): bool
    {
        return $this->startable;
    }

    /**
     * Enable debug mode by persisting compose/log artifacts to disk.
     *
     * @param string|null $path Base directory for debug files.
     */
    public function debug(?string $path = null): self
    {
        $this->debugPath = $path;
        if ($path !== null && !is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $this;
    }

    /**
     * Add/override a single environment variable for the compose run.
     */
    public function setEnvVariable(string $name, string $value): self
    {
        $this->envVariables[$name] = $value;

        return $this;
    }

    /**
     * Provide a batch of environment variables at once.
     *
     * @param array<string,string> $vars
     */
    public function setEnvVariables(array $vars): self
    {
        foreach ($vars as $key => $value) {
            $this->setEnvVariable($key, (string) $value);
        }

        return $this;
    }

    /**
     * @return array<string,string> Current environment key/value pairs.
     */
    public function getEnvVariables(): array
    {
        return $this->envVariables;
    }

    /**
     * Inject a PSR-3 logger used by the runtime for diagnostic messages.
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @return LoggerInterface|null Logger configured for this config.
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Register a callback invoked while docker compose streams its log output.
     *
     * @param callable $callback Signature: function(string $id, array $progress, string $operation): void
     * @param int $intervalMs Minimum interval between callback invocations.
     */
    public function onProgress(callable $callback, int $intervalMs = 250): self
    {
        $this->progressCallback = $callback;
        $this->progressInterval = $intervalMs;

        return $this;
    }

    /**
     * @return array{0:callable,1:int}|null Callback plus interval for runtime usage.
     */
    public function getProgressCallback(): ?array
    {
        if ($this->progressCallback === null) {
            return null;
        }

        return [$this->progressCallback, $this->progressInterval];
    }

    /**
     * Register a callback invoked when a runtime operation succeeds.
     */
    public function onSuccess(callable $callback): self
    {
        $this->successCallback = $callback;

        return $this;
    }

    /**
     * @return callable|null Success callback configured for this config.
     */
    public function getSuccessCallback(): ?callable
    {
        return $this->successCallback;
    }

    /**
     * Register a callback invoked when runtime errors occur.
     */
    public function onError(callable $callback): self
    {
        $this->errorCallback = $callback;

        return $this;
    }

    /**
     * @return callable|null Error callback configured for this config.
     */
    public function getErrorCallback(): ?callable
    {
        return $this->errorCallback;
    }

    /**
     * Merge additional docker-compose structure into the current definition.
     *
     * @param array<string,mixed> $values
     */
    public function setDockerComposeValues(array $values): self
    {
        $this->dockerCompose = $this->mergeRecursiveDistinct($this->dockerCompose, $values);

        return $this;
    }

    /**
     * Override the compose project name.
     */
    public function setProjectName(string $name): self
    {
        $this->dockerCompose['name'] = $name;

        return $this;
    }

    /**
     * Pin a specific container name for a service.
     */
    public function setContainerName(string $serviceName, string $containerName): self
    {
        $this->ensureService($serviceName);
        $this->dockerCompose['services'][$serviceName]['container_name'] = $containerName;

        return $this;
    }

    /**
     * Rename a service entry within the compose file.
     */
    public function setServiceName(string $serviceName, string $newName): self
    {
        if (!isset($this->dockerCompose['services'][$serviceName])) {
            return $this;
        }
        $this->dockerCompose['services'][$newName] = $this->dockerCompose['services'][$serviceName];
        unset($this->dockerCompose['services'][$serviceName]);

        return $this;
    }

    /**
     * Add a host->container port mapping if it does not exist yet.
     */
    public function setPortMapping(string $serviceName, int $containerPort, int $hostPort, string $protocol = 'tcp'): self
    {
        $this->ensureService($serviceName);
        $mapping = sprintf('%d:%d/%s', $hostPort, $containerPort, $protocol);
        $ports = $this->dockerCompose['services'][$serviceName]['ports'] ?? [];
        if (!in_array($mapping, $ports, true)) {
            $ports[] = $mapping;
        }
        $this->dockerCompose['services'][$serviceName]['ports'] = $ports;

        return $this;
    }

    /**
     * Ensure a network is defined with optional extra options.
     *
     * @param array<string,mixed> $options
     */
    public function setNetwork(string $networkName, array $options = []): self
    {
        $this->dockerCompose['networks'][$networkName] = $options;

        return $this;
    }

    /**
     * Configure CPU limits via the deploy.resources.limits.cpus field.
     */
    public function setCpus(string $serviceName, float $cpus): self
    {
        $this->ensureService($serviceName);
        $this->dockerCompose['services'][$serviceName]['deploy']['resources']['limits']['cpus'] = $cpus;

        return $this;
    }

    /**
     * Configure memory limits via the deploy.resources.limits.memory field.
     */
    public function setMemoryLimit(string $serviceName, string $memory): self
    {
        $this->ensureService($serviceName);
        $this->dockerCompose['services'][$serviceName]['deploy']['resources']['limits']['memory'] = $memory;

        return $this;
    }

    /**
     * @return array<string,mixed> The current compose structure.
     */
    public function getDockerComposeArray(): array
    {
        return $this->dockerCompose;
    }

    /**
     * Dump the compose array to a temporary YAML file stored alongside the source file.
     *
     * @return string Absolute path to the generated compose file.
     */
    public function createTemporaryComposeFile(): string
    {
        $tmpDir = $this->getTempDirectory();
        $file = $tmpDir . DIRECTORY_SEPARATOR . 'docker-compose-' . uniqid($this->id . '-', true) . '.yml';
        $yaml = YamlAdapter::dump($this->dockerCompose, 6, 2);
        file_put_contents($file, $yaml);
        $this->temporaryFiles[] = $file;

        return $file;
    }

    /**
     * Create an empty log file for docker compose output.
     *
     * @param string $operation Name of the operation (start/stop/etc.).
     *
     * @return string Path to the log file.
     */
    public function createTemporaryLogFile(string $operation): string
    {
        $tmpDir = $this->getTempDirectory();
        $file = $tmpDir . DIRECTORY_SEPARATOR . 'docker-compose-' . $operation . '-' . uniqid($this->id . '-', true) . '.log';
        touch($file);
        $this->temporaryFiles[] = $file;

        return $file;
    }

    /**
     * Delete all temporary compose/log files tied to this config.
     *
     * @return void
     */
    public function removeTmpFiles(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->temporaryFiles = [];
    }

    /**
     * Cache the runtime state returned by the manager.
     *
     * @param ContainerState $state
     */
    public function setState(ContainerState $state): void
    {
        $this->state = $state;
    }

    /**
     * @return ContainerState|null Last known container state.
     */
    public function getState(): ?ContainerState
    {
        return $this->state;
    }

    /**
     * Copy compose/log files into the configured debug directory for analysis.
     *
     * @param string $operation Operation name used to name the log file.
     * @param string $composeFile Path to the compose YAML.
     * @param string $logFile Path to the log file.
     */
    public function persistDebugArtifacts(string $operation, string $composeFile, string $logFile): void
    {
        if ($this->debugPath === null) {
            return;
        }

        $targetDir = rtrim($this->debugPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->id;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $this->copyFile($composeFile, $targetDir . DIRECTORY_SEPARATOR . basename($composeFile));
        $this->copyFile($logFile, $targetDir . DIRECTORY_SEPARATOR . $operation . '-output.log');
    }

    /**
     * Copy a file while ignoring missing sources.
     *
     * @param string $source
     * @param string $destination
     */
    private function copyFile(string $source, string $destination): void
    {
        if (is_file($source)) {
            copy($source, $destination);
        }
    }

    /**
     * Guarantee the service array exists before setting nested keys.
     *
     * @param string $name
     */
    private function ensureService(string $name): void
    {
        if (!isset($this->dockerCompose['services'][$name])) {
            $this->dockerCompose['services'][$name] = [];
        }
    }

    /**
     * Recursively merge arrays while replacing scalar values (no duplication like array_merge_recursive).
     *
     * @param array<string,mixed> $array1
     * @param array<string,mixed> $array2
     *
     * @return array<string,mixed>
     */
    private function mergeRecursiveDistinct(array $array1, array $array2): array
    {
        $merged = $array1;
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->mergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Determine the directory to store temporary compose/log files.
     *
     * @return string Absolute directory path.
     */
    private function getTempDirectory(): string
    {
        if ($this->sourceFile !== null) {
            return dirname($this->sourceFile);
        }

        $dir = $this->workingDirectory ?: sys_get_temp_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }
}
