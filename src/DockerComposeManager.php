<?php 

namespace Orryv;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;
use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\FileSystem\Reader;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandler;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandlerCollection;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandlerCollectionInterface;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandlerFactory;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandlerFactoryInterface;
use Orryv\DockerComposeManager\DockerCompose\CommandExecutor;
use Orryv\DockerComposeManager\Validation\DockerComposeValidator;
use Orryv\DockerComposeManager\CommandBuilder\DockerComposeCommandBuilder;

class DockerComposeManager
{
    /**
     * Instance we use to parse .yml files.
     *  Nullable because we allow operations without docker compose arrays.
     */
    private ?YamlParserInterface $yaml_parser;
    private DockerComposeHandlerCollectionInterface $internalConfigManager;
    private ?string $executionPath = null;
    private ?string $debugDir = null;
    private CommandExecutor $commandExecutor;
    private DockerComposeHandlerFactoryInterface $handlerFactory;
    private array $tmpOutputFiles = [];
    private array $runningPids = [];


    public function __construct(
        YamlParserInterface|string $yaml_parser = 'ext-yaml',
        DockerComposeHandlerCollectionInterface|null $internalConfigManager = null,
        ?CommandExecutor $commandExecutor = null,
        ?DockerComposeHandlerFactoryInterface $handlerFactory = null
    ){
        $this->yaml_parser = is_string($yaml_parser)
            ? (new YamlParserFactory())->create($yaml_parser)
            : $yaml_parser;

        $this->internalConfigManager = $internalConfigManager ?? new DockerComposeHandlerCollection();
        $this->commandExecutor = $commandExecutor ?? new CommandExecutor();
        $this->handlerFactory = $handlerFactory ?? new DockerComposeHandlerFactory();
    }

    public function __destruct()
    {
        // Clean up any temporary files created
        foreach ($this->internalConfigManager->getRegisteredIds() as $config_id) {
            $config = $this->internalConfigManager->get($config_id);
            if ($config !== null) {
                if($this->debugDir !== null) {
                    $config->copyTmpFiles($this->debugDir);
                }

                $config->removeTmpFiles();
            }

            if (array_key_exists($config_id, $this->tmpOutputFiles)) {
                $tmpOutputFile = $this->tmpOutputFiles[$config_id];

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

    public function fromDockerComposeFile(string $id, string $file_path): DockerComposeHandler
    {
        $dockerFileContents = Reader::readFile($file_path);
        $this->executionPath = dirname($file_path);
        $dockerComposeHandler = $this->handlerFactory->create(
            $this->getYamlParser()->parse($dockerFileContents),
            $this->yaml_parser,
        );
        $this->internalConfigManager->add($id, $dockerComposeHandler);
        DockerComposeValidator::validate($this->internalConfigManager->getCurrent()->toArray()); // TODO: move to execution side

        return $dockerComposeHandler;
    }

    public function fromYamlArray(string $id, array $yaml_array, string $executionFolder): DockerComposeHandler
    {
        $this->executionPath = $executionFolder;
        DockerComposeValidator::validate($yaml_array); // TODO: move to execution side
        $dockerComposeHandler = $this->handlerFactory->create($yaml_array, $this->yaml_parser);
        $this->internalConfigManager->add($id, $dockerComposeHandler);

        return $dockerComposeHandler;
    }

    public function start(string|array|null $id = null, string|array|null $serviceNames = null, bool $rebuildContainers = false): void
    {
        foreach($this->buildStartCommands($id, $serviceNames, $rebuildContainers) as $commandData) {
            $executionResult = $this->commandExecutor->executeAsync(
                $commandData['command'],
                $this->executionPath,
                $commandData['tmp_identifier']
            );

            if ($executionResult['pid'] !== null) {
                $this->runningPids[$commandData['id']] = $executionResult['pid'];
            }

            $this->tmpOutputFiles[$commandData['id']] = $executionResult['output_file'];
        }
    }

    public function startAsync()
    {
        // TODO
    }

    public function getRunningPids(): array
    {
        return $this->runningPids;
    }

    public function getTmpOutputFiles(): array
    {
        return $this->tmpOutputFiles;
    }

    ######################
    ## Internal methods ##
    ######################

    private function buildStartCommands(
        string|array|null $id = null,
        string|array|null $serviceNames = null,
        bool $rebuildContainers = false
    ): array {
        if($this->executionPath === null) {
            throw new DockerComposeManagerException('Can only start containers when using fromDockerComposeFile() or fromYamlArray().');
        }

        $ids = $this->normalizeInternalIds($id);

        $commands = [];
        foreach($ids as $config_id) {
            $dockerComposeHandler = $this->internalConfigManager->get($config_id);
            $dockerComposeHandler->saveTmpDockerComposeFile($this->executionPath);
            $command = (new DockerComposeCommandBuilder($dockerComposeHandler))->start($serviceNames, $rebuildContainers);
            $commands[] = [
                'id' => $config_id,
                'command' => $command,
                'handler' => $dockerComposeHandler,
                'tmp_identifier' => $this->deriveTmpIdentifier($dockerComposeHandler->getTmpFilePath()),
            ];
        }

        return $commands;
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
            return $this->internalConfigManager->getRegisteredIds();
        }
    }

    private function getYamlParser(): YamlParserInterface
    {
        if ($this->yaml_parser === null) {
            throw new YamlParserException(
                'No YAML parser configured. Construct with one, or use fromYamlArray() to build from an array directly.'
            );
        }

        return $this->yaml_parser;
    }
}