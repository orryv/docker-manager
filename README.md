# docker-compose-manager

A PHP library to manage Docker Compose configurations and containers programmatically: create, modify, and control Docker Compose setups using PHP, including starting, stopping, and inspecting containers.

## TODO

- [ ] Wait for healthchecks for isFinishedExecuting() to return true (or add an isHealthy() method)

## Installation

Requirements:

- PHP 8.2 or higher // TODO: check if we can support 8.1, or only 8.4
- Composer
- [`ext-yaml`](https://www.php.net/manual/en/book.yaml.php) AND/OR [`symfony/yaml`](https://github.com/symfony/yaml)
- Docker & Docker Compose CLI installed (e.g. included in Docker Desktop)
- [`psr/log`](https://github.com/php-fig/log) (for logging, optional)

Install the library via Composer as usual:

```
composer require orryv/docker-compose-manager
```

## Notes

- Make sure to add a valid healthcheck to each service in your docker-compose.yml files, so the library can determine when a container is "ready" (and thus can return from start(), etc). Example:

```yaml
services:
  web:
    image: my-web-image
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"] # Depends on the container
      interval: 5s
      timeout: 5s
      retries: 10
```

## Usage

Run multiple docker compose simultaneously from different docker-compose.yml files, manage them independently:

```php
use Orryv\DockerComposeManager;

$dcm = DockerComposeManager::new();


// Each configuration is registered under a custom $id. 
//  You can later start/stop/inspect by passing one or more IDs to the manager.
$config1 = $dcm->fromDockerComposeFile('id-1', 'path/to/docker-compose.yml');
$config2 = $dcm->fromDockerComposeFile('id-2', 'path/to/another-docker-compose.yml');

$config1->setEnvVariable('MY_ENV_VAR', 'value1');

$dcm->start(); // runs all registered compose projects in parallel and returns when they all finish or become healthy

```

## Architecture overview

- **DockerComposeManager**: Thin façade that orchestrates configuration, execution, and progress tracking.
- **ConfigurationManager**: Manages docker-compose definitions, YAML parsing, file handlers, and execution paths.
- **ComposeRunner**: Builds docker-compose commands and executes them, tracking PIDs and output files.
- **ProgressTracker**: Parses execution output and exposes blocking/non-blocking progress reporting.

## Methods

### `DockerComposeManager`

| Method | Signature | Description |
| --- | --- | --- |
| new | `public static function new(string $parser = 'ext-yaml'): self` | Build a manager with default production collaborators and YAML parser selection. |
| __construct | `public function __construct(ConfigurationManager $config, ComposeRunner $runner, ProgressTracker $progress)` | Inject custom collaborators, useful for advanced wiring or testing. |
| debug | `public function debug(string $dir): void` | Copy generated docker-compose and log files into the directory during cleanup. |
| fromDockerComposeFile | `public function fromDockerComposeFile(string $id, string $file_path): DockerComposeDefinitionInterface` | Register a docker-compose definition from disk and set the execution directory. |
| fromYamlArray | `public function fromYamlArray(string $id, array $yaml_array, string $executionFolder): DockerComposeDefinitionInterface` | Register a docker-compose definition from an in-memory array for the provided execution directory. |
| onProgress | `public function onProgress(callable $callback): void` | Register a callback to receive progress updates while blocking on execution. |
| start | `public function start(string|array|null $id = null, string|array|null $serviceNames = null, bool $rebuildContainers = false): bool` | Start registered configurations and block until they complete, returning success state. |
| startAsync | `public function startAsync(string|array|null $id = null, string|array|null $serviceNames = null, bool $rebuildContainers = false): CommandExecutionResultsCollection` | Start registered configurations asynchronously and return execution metadata immediately. |
| getProgress | `public function getProgress(string|array|null $id = null): OutputParserResultsCollectionInterface` | Parse the latest output logs for the requested configuration IDs. |
| isFinished | `public function isFinished(string|array|null $id = null): bool` | Determine whether asynchronous executions have completed. |
| getRunningPids | `public function getRunningPids(): array` | Retrieve process identifiers for running docker-compose commands. |
| getFinalDockerComposeFile | `public function getFinalDockerComposeFile(string $id): string` | Retrieve the output log file path for a configuration. |
| cleanup | `public function cleanup(): void` | Remove generated files and close processes; safe to call multiple times. |

### `ConfigurationManager`

| Method | Signature | Description |
| --- | --- | --- |
| __construct | `public function __construct(?YamlParserInterface $yamlParser, DefinitionsCollectionInterface $definitionsCollection = new DefinitionsCollection(), FileHandlerFactoryInterface $fileHandlerFactory = new FileHandlerFactory(), DefinitionFactoryInterface $definitionFactory = new DefinitionFactory())` | Inject dependencies for configuration management. |
| setDebugDirectory | `public function setDebugDirectory(?string $dir): void` | Configure an optional directory to copy docker-compose files during cleanup. |
| fromDockerComposeFile | `public function fromDockerComposeFile(string $id, string $filePath): DockerComposeDefinitionInterface` | Register a docker-compose definition from a YAML file. |
| fromYamlArray | `public function fromYamlArray(string $id, array $yamlArray, string $executionFolder): DockerComposeDefinitionInterface` | Register a docker-compose definition from an array and execution folder. |
| buildExecutionContexts | `public function buildExecutionContexts(string|array|null $id = null): array` | Prepare execution contexts and persist compose files for the selected IDs. |
| getExecutionPath | `public function getExecutionPath(): string` | Retrieve the execution directory, throwing if none is configured. |
| getYamlParser | `public function getYamlParser(): YamlParserInterface` | Retrieve the configured YAML parser or throw when missing. |
| normalizeIds | `public function normalizeIds(string|array|null $id = null): array` | Normalize configuration identifiers to an array. |
| getDefinitionsCollection | `public function getDefinitionsCollection(): DefinitionsCollectionInterface` | Access the underlying definitions collection. |
| cleanup | `public function cleanup(): void` | Remove generated docker-compose files, optionally copying them to the debug directory. |

### `ComposeRunner`

| Method | Signature | Description |
| --- | --- | --- |
| __construct | `public function __construct(CommandExecutorInterface $commandExecutor = new CommandExecutor(), CommandExecutionResultsCollectionFactory $executionResultsCollectionFactory = new CommandExecutionResultsCollectionFactory())` | Inject command execution dependencies. |
| start | `public function start(array $contexts, string $executionPath, string|array|null $serviceNames = null, bool $rebuildContainers = false): CommandExecutionResultsCollection` | Build and execute docker-compose commands for the provided contexts. |
| getRunningPids | `public function getRunningPids(): array` | Retrieve running process identifiers by configuration ID. |
| getOutputFiles | `public function getOutputFiles(): array` | Retrieve output log paths by configuration ID. |
| getExecutionResultsForIds | `public function getExecutionResultsForIds(array $ids): array` | Fetch execution results for the requested configuration IDs. |
| getOutputFileForId | `public function getOutputFileForId(string $id): string` | Retrieve the output log path for a specific configuration ID. |
| cleanupOutputs | `public function cleanupOutputs(?string $debugDir = null): void` | Remove generated output files, optionally copying them to a debug directory. |

### `ProgressTracker`

| Method | Signature | Description |
| --- | --- | --- |
| __construct | `public function __construct(OutputParserInterface $outputParser = new OutputParser(), BlockingOutputParserInterface $blockingOutputParser = new BlockingOutputParser(new OutputParser()))` | Inject parsing dependencies. |
| onProgress | `public function onProgress(callable $callback): void` | Register a progress callback for blocking parsing. |
| parseBlocking | `public function parseBlocking(CommandExecutionResultsCollection $results, ?callable $onProgress = null): OutputParserResultsCollectionInterface` | Parse execution results while blocking until all commands complete. |
| getProgress | `public function getProgress(array $executionResults): OutputParserResultsCollectionInterface` | Parse the latest output snapshots for provided execution results. |
| isFinished | `public function isFinished(array $executionResults): bool` | Determine whether the provided execution results represent finished work. |
