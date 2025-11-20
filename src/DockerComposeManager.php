<?php 

namespace Orryv;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;
use Orryv\DockerComposeManager\FileSystem\Reader;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandler;
use Orryv\DockerComposeManager\Validation\DockerComposeValidator;
use Orryv\DockerComposeManager\Internal\InternalContainerConfigManager;
use Orryv\DockerComposeManager\CommandBuilder\DockerComposeCommandBuilder;
use Orryv\DockerComposeManager\Internal\InternalContainerConfigManagerInterface;

class DockerComposeManager
{
    /**
     * Instance we use to parse .yml files.
     *  Nullable because we allow operations without docker compose arrays.
     */
    private ?YamlParserInterface $yaml_parser;
    private InternalContainerConfigManagerInterface $internalConfigManager;
    private ?string $executionPath = null;
    private ?string $debugDir = null;


    public function __construct(
        YamlParserInterface|string $yaml_parser = 'ext-yaml',
        InternalContainerConfigManagerInterface|null $internalConfigManager = null
    ){
        $this->yaml_parser = is_string($yaml_parser)
            ? (new YamlParserFactory())->create($yaml_parser)
            : $yaml_parser;

        $this->internalConfigManager = $internalConfigManager ?? new InternalContainerConfigManager();
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
        $dockerComposeHandler = new DockerComposeHandler(
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
        $dockerComposeHandler = new DockerComposeHandler($yaml_array, $this->yaml_parser);
        $this->internalConfigManager->add($id, $dockerComposeHandler);

        return $dockerComposeHandler;
    }

    public function start(string|array|null $id = null, string|array|null $serviceNames = null, bool $rebuildContainers = false) // TODO add return type
    {
        foreach($this->buildStartCommands($id, $serviceNames, $rebuildContainers) as $command) {
            // TODO: execute command
            echo "Executing command: " . $command . PHP_EOL;
        }
    }

    public function startAsync()
    {
        // TODO
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
            $commands[] = $command;
        }

        return $commands;
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
            throw new DockerComposeManagerException(
                'No YAML parser configured. Construct with one, or use fromYamlArray() to build from an array directly.'
            );
        }

        return $this->yaml_parser;
    }
}