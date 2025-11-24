<?php

namespace Orryv;

use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionFactory;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionFactoryInterface;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionInterface as DockerComposeDefinitionInterface;
use Orryv\DockerComposeManager\DockerCompose\DefinitionsCollection\DefinitionsCollection;
use Orryv\DockerComposeManager\DockerCompose\DefinitionsCollection\DefinitionsCollectionInterface;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerFactory;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerFactoryInterface;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerInterface;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;
use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\FileSystem\Reader;
use Orryv\DockerComposeManager\Validation\DockerComposeValidator;
use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;

/**
 * Coordinates configuration state and docker-compose definition lifecycles.
 */
class ConfigurationManager
{
    private ?string $executionPath = null;

    private ?string $debugDir = null;

    /** @var array<string, FileHandlerInterface> */
    private array $fileHandlers = [];

    /**
     * @param DefinitionsCollectionInterface|null $definitionsCollection Allows custom definition storage for testing.
     * @param FileHandlerFactoryInterface|null $fileHandlerFactory Factory used to produce file handlers for compose files.
     * @param DefinitionFactoryInterface|null $definitionFactory Factory used to produce docker-compose definitions.
     */
    public function __construct(
        private ?YamlParserInterface $yamlParser,
        private DefinitionsCollectionInterface $definitionsCollection = new DefinitionsCollection(),
        private FileHandlerFactoryInterface $fileHandlerFactory = new FileHandlerFactory(),
        private DefinitionFactoryInterface $definitionFactory = new DefinitionFactory()
    ) {
    }

    /**
     * Configure an optional directory where temporary files are copied for debugging purposes.
     */
    public function setDebugDirectory(?string $dir): void
    {
        $this->debugDir = $dir;
    }

    /**
     * Register a docker-compose definition from a YAML file on disk.
     */
    public function fromDockerComposeFile(string $id, string $filePath): DockerComposeDefinitionInterface
    {
        $dockerFileContents = Reader::readFile($filePath);
        $this->executionPath = dirname($filePath);

        $dockerComposeDefinition = $this->definitionFactory->create(
            $this->getYamlParser()->parse($dockerFileContents),
            $this->yamlParser,
        );

        $this->definitionsCollection->add($id, $dockerComposeDefinition);
        DockerComposeValidator::validate($this->definitionsCollection->getCurrent()->toArray());

        return $dockerComposeDefinition;
    }

    /**
     * Register a docker-compose definition from an in-memory YAML array.
     */
    public function fromYamlArray(string $id, array $yamlArray, string $executionFolder): DockerComposeDefinitionInterface
    {
        $this->executionPath = $executionFolder;
        DockerComposeValidator::validate($yamlArray);
        $dockerComposeDefinition = $this->definitionFactory->create($yamlArray, $this->yamlParser);
        $this->definitionsCollection->add($id, $dockerComposeDefinition);

        return $dockerComposeDefinition;
    }

    /**
     * Build the execution contexts required to start the configured docker-compose projects.
     *
     * @return array<int, array{id: string, definition: DockerComposeDefinitionInterface, fileHandler: FileHandlerInterface}>
     */
    public function buildExecutionContexts(string|array|null $id = null): array
    {
        $this->assertExecutionContextIsReady();

        $contexts = [];
        foreach ($this->normalizeIds($id) as $configId) {
            $definition = $this->definitionsCollection->get($configId);
            $fileHandler = $this->getFileHandler($configId);
            $fileHandler->saveFinalDockerComposeFile($this->getExecutionPath());

            $contexts[] = [
                'id' => $configId,
                'definition' => $definition,
                'fileHandler' => $fileHandler,
            ];
        }

        return $contexts;
    }

    /**
     * Retrieve the directory used to execute docker-compose commands.
     */
    public function getExecutionPath(): string
    {
        if ($this->executionPath === null) {
            throw new DockerComposeManagerException('Execution path is not set. Register a compose file before starting.');
        }

        return $this->executionPath;
    }

    /**
     * Retrieve the configured YAML parser, ensuring it is present.
     */
    public function getYamlParser(): YamlParserInterface
    {
        if ($this->yamlParser === null) {
            throw new YamlParserException(
                'No YAML parser configured. Construct with one, or use fromYamlArray() to build from an array directly.'
            );
        }

        return $this->yamlParser;
    }

    /**
     * Normalize input IDs to a consistent array representation.
     */
    public function normalizeIds(string|array|null $id = null): array
    {
        if (is_string($id)) {
            return [$id];
        }

        if (is_array($id)) {
            return $id;
        }

        return $this->definitionsCollection->getRegisteredIds();
    }

    /**
     * Expose the definitions collection for inspection or advanced scenarios.
     */
    public function getDefinitionsCollection(): DefinitionsCollectionInterface
    {
        return $this->definitionsCollection;
    }

    /**
     * Remove any generated docker-compose files, optionally copying them to the debug directory first.
     */
    public function cleanup(): void
    {
        foreach ($this->fileHandlers as $id => $fileHandler) {
            if ($this->debugDir !== null) {
                $fileHandler->copyFinalDockerComposeFile($this->debugDir);
            }

            $fileHandler->removeFinalDockerComposeFile();
            unset($this->fileHandlers[$id]);
        }
    }

    /**
     * Retrieve or build a file handler for the provided configuration ID.
     */
    private function getFileHandler(string $id): FileHandlerInterface
    {
        if (!array_key_exists($id, $this->fileHandlers)) {
            $definition = $this->definitionsCollection->get($id);
            $this->fileHandlers[$id] = $this->fileHandlerFactory->create($definition, $this->yamlParser);
        }

        return $this->fileHandlers[$id];
    }

    /**
     * Ensure execution prerequisites (execution path and YAML parser) are available.
     */
    private function assertExecutionContextIsReady(): void
    {
        $this->getYamlParser();
        $this->getExecutionPath();
    }
}
