<?php

namespace Orryv;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollectionFactory;
use Orryv\DockerComposeManager\DockerCompose\CommandExecutor\CommandExecutor;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionFactory;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionInterface as DockerComposeDefinitionInterface;
use Orryv\DockerComposeManager\DockerCompose\DefinitionsCollection\DefinitionsCollection;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerFactory;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\BlockingOutputParser;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParser;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultsCollectionInterface;

/**
 * Facade for orchestrating Docker Compose operations via dedicated collaborators.
 */
class DockerComposeManager
{
    private ?string $debugDir = null;

    /**
     * @param ConfigurationManager $config Coordinates docker-compose definitions and file handlers.
     * @param ComposeRunner $runner Executes docker-compose commands.
     * @param ProgressTracker $progress Parses and reports execution progress.
     */
    public function __construct(
        private ConfigurationManager $config,
        private ComposeRunner $runner,
        private ProgressTracker $progress,
    ) {
    }

    /**
     * Build a fully-wired DockerComposeManager using production defaults.
     */
    public static function new(string $parser = 'ext-yaml'): self
    {
        $yamlParser = (new YamlParserFactory())->create($parser);
        $definitionsCollection = new DefinitionsCollection();
        $fileHandlerFactory = new FileHandlerFactory();
        $definitionFactory = new DefinitionFactory();
        $commandExecutor = new CommandExecutor();
        $executionResultsFactory = new CommandExecutionResultsCollectionFactory();
        $outputParser = new OutputParser();
        $blockingOutputParser = new BlockingOutputParser($outputParser);

        $config = new ConfigurationManager(
            $yamlParser,
            $definitionsCollection,
            $fileHandlerFactory,
            $definitionFactory
        );

        $runner = new ComposeRunner($commandExecutor, $executionResultsFactory);
        $progress = new ProgressTracker($outputParser, $blockingOutputParser);

        return new self($config, $runner, $progress);
    }

    /**
     * Destructor performing best-effort cleanup.
     */
    public function __destruct()
    {
        try {
            $this->cleanup();
        } catch (\Throwable $e) {
            // Best-effort cleanup; swallow exceptions during destruction.
        }
    }

    /**
     * Enable debug mode by copying temporary files into the provided directory during cleanup.
     */
    public function debug(string $dir): void
    {
        $this->debugDir = $dir;
        $this->config->setDebugDirectory($dir);
    }

    /**
     * Register a docker-compose definition from a YAML file.
     */
    public function fromDockerComposeFile(string $id, string $file_path): DockerComposeDefinitionInterface
    {
        return $this->config->fromDockerComposeFile($id, $file_path);
    }

    /**
     * Register a docker-compose definition from an in-memory YAML array.
     */
    public function fromYamlArray(string $id, array $yaml_array, string $executionFolder): DockerComposeDefinitionInterface
    {
        return $this->config->fromYamlArray($id, $yaml_array, $executionFolder);
    }

    /**
     * Registers a callback to receive progress updates during container start (non async methods).
     *
     * @param callable $callback
     */
    public function onProgress(callable $callback): void
    {
        $this->progress->onProgress($callback);
    }

    /**
     * Starts containers defined in the Docker Compose configurations. Returns true if all containers started successfully.
     * When $waitForHealthy is true, the call blocks until all containers with health checks report a healthy status.
     * Does NOT throw exceptions on failure, instead returns false. use getErrors() to retrieve error details.
     */
    public function start(
        string|array|null $id = null,
        string|array|null $serviceNames = null,
        bool $rebuildContainers = false,
        bool $waitForHealthy = true,
        int $stateCheckIntervalUs = 250000
    ): bool {
        $executionContexts = $this->config->buildExecutionContexts($id);
        $executionResults = $this->runner->start(
            $executionContexts,
            $this->config->getExecutionPath(),
            $serviceNames,
            $rebuildContainers
        );

        $parseResults = $this->progress->parseBlocking($executionResults, null, $stateCheckIntervalUs);

        if (!$parseResults->isSuccessful()) {
            return false;
        }

        if (!$waitForHealthy) {
            return true;
        }

        return $this->progress->waitForHealthyContainers($parseResults, $stateCheckIntervalUs);
    }

    /**
     * Start containers asynchronously, returning immediately with execution metadata.
     */
    public function startAsync(
        string|array|null $id = null,
        string|array|null $serviceNames = null,
        bool $rebuildContainers = false
    ): CommandExecutionResultsCollection {
        $executionContexts = $this->config->buildExecutionContexts($id);

        return $this->runner->start(
            $executionContexts,
            $this->config->getExecutionPath(),
            $serviceNames,
            $rebuildContainers
        );
    }

    /**
     * Get progress for async methods (startAsync, restartAsync, etc).
     */
    public function getProgress(string|array|null $id = null): OutputParserResultsCollectionInterface
    {
        $ids = $this->config->normalizeIds($id);
        $executionResults = $this->runner->getExecutionResultsForIds($ids);

        return $this->progress->getProgress($executionResults);
    }

    /**
     * Determine if async executions have completed.
     */
    public function isFinished(string|array|null $id = null): bool
    {
        $ids = $this->config->normalizeIds($id);
        $executionResults = $this->runner->getExecutionResultsForIds($ids);

        return $this->progress->isFinished($executionResults);
    }

    /**
     * Retrieve process identifiers for running docker-compose commands.
     */
    public function getRunningPids(): array
    {
        return $this->runner->getRunningPids();
    }

    /**
     * Get the output log file for a registered configuration.
     */
    public function getFinalDockerComposeFile(string $id): string
    {
        return $this->runner->getOutputFileForId($id);
    }

    /**
     * Clean up temporary files and running processes.
     */
    public function cleanup(): void
    {
        $this->config->cleanup();
        $this->runner->cleanupOutputs($this->debugDir);
    }
}
