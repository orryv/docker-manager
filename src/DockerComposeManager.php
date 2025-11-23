<?php 

namespace Orryv;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;
use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\FileSystem\Reader;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionInterface as DockerComposeDefinitionInterface;
use Orryv\DockerComposeManager\DockerCompose\DefinitionsCollection\DefinitionsCollection;
use Orryv\DockerComposeManager\DockerCompose\DefinitionsCollection\DefinitionsCollectionInterface;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionFactory;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionFactoryInterface;
use Orryv\DockerComposeManager\DockerCompose\CommandExecutor\CommandExecutor;
use Orryv\DockerComposeManager\DockerCompose\CommandExecutor\CommandExecutorInterface;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollectionFactory;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerFactoryInterface;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerFactory;
use Orryv\DockerComposeManager\Validation\DockerComposeValidator;
use Orryv\DockerComposeManager\CommandBuilder\DockerComposeCommandBuilder;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\BlockingOutputParserInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\BlockingOutputParser;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParser as DockerComposeOutputParser;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultsCollectionInterface;
use Closure;

class DockerComposeManager
{
    /**
     * Instance we use to parse .yml files.
     *  Nullable because we allow operations without docker compose arrays.
     */
    private ?YamlParserInterface $yamlarser;
    private DefinitionsCollectionInterface $definitionsCollection;
    private ?string $executionPath = null;
    private ?string $debugDir = null;
    private CommandExecutorInterface $commandExecutor;
    private DefinitionFactoryInterface $handlerFactory;
    private array $finalDockerComposeFile = [];
    private array $runningPids = [];
    private ?OutputParserInterface $outputParser = null;
    private ?BlockingOutputParserInterface $blockingOutputParser = null;
    private FileHandlerFactoryInterface $fileHandlerFactory;
    private ?Closure $onProgressCallback = null;
    private CommandExecutionResultsCollectionFactory $executionResultsCollectionFactory;


    public function __construct(
        YamlParserInterface|string $yamlarser = 'ext-yaml',
        ?DefinitionsCollectionInterface $definitionsCollection = null,
        ?FileHandlerFactoryInterface $fileHandlerFactory = null,
        ?CommandExecutorInterface $commandExecutor = null,
        ?DefinitionFactoryInterface $handlerFactory = null,
        ?OutputParserInterface $outputParser = null,
        ?BlockingOutputParserInterface $blockingOutputParser = null,
        ?CommandExecutionResultsCollectionFactory $executionResultsCollectionFactory = null
    ){
        $this->yamlarser = is_string($yamlarser)
            ? (new YamlParserFactory())->create($yamlarser)
            : $yamlarser;

        $this->handlerFactory = $handlerFactory ?? new DefinitionFactory();
        $this->fileHandlerFactory = $fileHandlerFactory ?? new FileHandlerFactory();
        $this->definitionsCollection = $definitionsCollection ?? new DefinitionsCollection();
        $this->commandExecutor = $commandExecutor ?? new CommandExecutor();
        $this->outputParser = $outputParser ?? new DockerComposeOutputParser();
        $this->blockingOutputParser = $blockingOutputParser ?? new BlockingOutputParser($this->outputParser);
        $this->executionResultsCollectionFactory = $executionResultsCollectionFactory
            ?? new CommandExecutionResultsCollectionFactory();

    }

    public function __destruct()
    {
        // Clean up any temporary files created
        foreach ($this->definitionsCollection->getRegisteredIds() as $config_id) {
            $definition = $this->definitionsCollection->get($config_id);
            $fileHandler = $this->fileHandlerFactory->create($definition, $this->yamlarser);
            if ($definition !== null) {
                if($this->debugDir !== null) {
                    $fileHandler->copyFinalDockerComposeFile($this->debugDir);
                }

                $fileHandler->removeFinalDockerComposeFile();
            }

            if (array_key_exists($config_id, $this->finalDockerComposeFile)) {
                $tmpOutputFile = $this->finalDockerComposeFile[$config_id];

                if ($tmpOutputFile !== null && file_exists($tmpOutputFile)) {
                    if ($this->debugDir !== null) {
                        $outputCopyPath = $this->debugDir . DIRECTORY_SEPARATOR . basename($tmpOutputFile);
                        copy($tmpOutputFile, $outputCopyPath);
                    }

                    unlink($tmpOutputFile);
                }
            }
        }
    }

    public function debug(string $dir): void
    {
        $this->debugDir = $dir;
    }

    public function fromDockerComposeFile(string $id, string $file_path): DockerComposeDefinitionInterface
    {
        $dockerFileContents = Reader::readFile($file_path);
        $this->executionPath = dirname($file_path);
        $dockerComposeDefinition = $this->handlerFactory->create(
            $this->getYamlParser()->parse($dockerFileContents),
            $this->yamlarser,
        );
        $this->definitionsCollection->add($id, $dockerComposeDefinition);
        DockerComposeValidator::validate($this->definitionsCollection->getCurrent()->toArray()); // TODO: move to execution side

        return $dockerComposeDefinition;
    }

    public function fromYamlArray(string $id, array $yaml_array, string $executionFolder): DockerComposeDefinitionInterface
    {
        $this->executionPath = $executionFolder;
        DockerComposeValidator::validate($yaml_array); // TODO: move to execution side
        $dockerComposeDefinition = $this->handlerFactory->create($yaml_array, $this->yamlarser);
        $this->definitionsCollection->add($id, $dockerComposeDefinition);

        return $dockerComposeDefinition;
    }

    /**
     * Registers a callback to receive progress updates during container start (non async methods).
     *
     * @param callable(OutputParserResultInterface):void $callback
     */
    public function onProgress(callable $callback): void
    {
        $this->onProgressCallback = $callback;
    }

    /**
     * Starts containers defined in the Docker Compose configurations. Returns true if all containers started successfully.
     * Does NOT throw exceptions on failure, instead returns false. use getErrors() to retrieve error details.
     */
    public function start(string|array|null $id = null, string|array|null $serviceNames = null, bool $rebuildContainers = false): bool
    {
        $executionResults = $this->executeStart($id, $serviceNames, $rebuildContainers);

        $parseResults = $this->blockingOutputParser->parse($executionResults, 250000, $this->onProgressCallback);

        return $parseResults->isSuccessful();
    }

    public function startAsync(string|array|null $id = null, string|array|null $serviceNames = null, bool $rebuildContainers = false) // TODO: return type
    {
        return $this->executeStart($id, $serviceNames, $rebuildContainers);
    }

    /**
     * Get progress for async methods (startAsync, restartAsync, etc).
     */
    public function getProgress(string|array|null $id = null): OutputParserResultsCollectionInterface
    {
        $results = new OutputParserResultsCollection();
        $ids = $this->normalizeInternalIds($id);
        foreach($ids ?? [] as $config_id) {
            $outputFile = $this->finalDockerComposeFile[$config_id] ?? null;
            if ($outputFile === null) {
                throw new DockerComposeManagerException("No output file found for config ID: {$config_id}");
            }
            $results->add($this->outputParser->parse(
                new CommandExecutionResult($config_id, $this->runningPids[$config_id] ?? null, $outputFile)
            ));
        }

        return $results;
    }

    public function isFinished(string|array|null $id = null): bool
    {
        $progress = $this->getProgress($id);

        return $progress->isFinishedExecuting();
    }

    public function getRunningPids(): array
    {
        return $this->runningPids;
    }

    public function getFinalDockerComposeFile(string $id): array
    {
        if(!isset($this->finalDockerComposeFile[$id])) {
            throw new DockerComposeManagerException("No final docker-compose output file found for ID: {$id}");
        }

        return $this->finalDockerComposeFile[$id];
    }

    ######################
    ## Internal methods ##
    ######################

    private function executeStart(
        string|array|null $id = null,
        string|array|null $serviceNames = null,
        bool $rebuildContainers = false
    ): CommandExecutionResultsCollection {
        if($this->executionPath === null) {
            throw new DockerComposeManagerException('Can only start containers when using fromDockerComposeFile() or fromYamlArray().');
        }

        if($this->yamlarser === null) {
            throw new YamlParserException(
                'No YAML parser configured. Construct with one, or use fromYamlArray() to build from an array directly.'
            );
        }

        $ids = $this->normalizeInternalIds($id);

        $commands = [];
        foreach($ids as $config_id) {
            $dockerComposeDefinition = $this->definitionsCollection->get($config_id);
            $fileHandler = $this->fileHandlerFactory->create($dockerComposeDefinition, $this->yamlarser);
            $fileHandler->saveFinalDockerComposeFile($this->executionPath);
            $command = (new DockerComposeCommandBuilder($dockerComposeDefinition, $fileHandler))
                ->start($serviceNames, $rebuildContainers);
            $commands[$config_id] = [
                'id' => $config_id,
                'command' => $command,
                'handler' => $dockerComposeDefinition,
                'tmp_identifier' => $this->deriveTmpIdentifier($fileHandler->getFinalDockerComposeFilePath()),
            ];
        }

        return $this->execute($commands);
    }

    private function execute(array $commands): CommandExecutionResultsCollection
    {
        $executionResults = $this->executionResultsCollectionFactory->createFromCommands(
            $commands,
            $this->commandExecutor,
            $this->executionPath
        );

        foreach ($executionResults as $executionResult) {
            if ($executionResult->getPid() !== null) {
                $this->runningPids[$executionResult->getId()] = $executionResult->getPid();
            }

            $this->finalDockerComposeFile[$executionResult->getId()] = $executionResult->getOutputFile();
        }

        return $executionResults;
    }

    private function deriveTmpIdentifier(?string $tmpFilePath): ?string
    {
        if ($tmpFilePath === null) {
            return null;
        }

        $fileName = basename($tmpFilePath);

        if (preg_match('/docker-compose-tmp-([^.]+)\.yml/', $fileName, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function normalizeInternalIds(string|array|null $id = null): array
    {
        if (is_string($id)) {
            return [$id];
        } elseif (is_array($id)) {
            return $id;
        } else {
            return $this->definitionsCollection->getRegisteredIds();
        }

        return [];
    }

    private function getYamlParser(): YamlParserInterface
    {
        if ($this->yamlarser === null) {
            throw new YamlParserException(
                'No YAML parser configured. Construct with one, or use fromYamlArray() to build from an array directly.'
            );
        }

        return $this->yamlarser;
    }
}