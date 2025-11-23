<?php

namespace Orryv\DockerComposeManager\DockerCompose\FileHandler;

use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionInterface;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerInterface;
use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\DockerComposeManager\FileSystem\Writer;

class FileHandler implements FileHandlerInterface
{
    private DefinitionInterface $definition;
    private ?YamlParserInterface $yaml_parser;
    private ?string $finalComposeFilePath = null;

    public function __construct(DefinitionInterface $definition, ?YamlParserInterface $yaml_parser = null)
    {
        $this->definition = $definition;
        $this->yaml_parser = $yaml_parser;
    }

    public function getDefinition(): DefinitionInterface
    {
        return $this->definition;
    }

    public function saveFinalDockerComposeFile(string $fileDir): void
    {
        if ($this->yaml_parser === null) {
            throw new YamlParserException(
                'No YAML parser configured. Construct with one, or use fromYamlArray() to build from an array directly.'
            );
        }

        $yamlContent = $this->yaml_parser->build($this->definition->toArray());

        if($this->finalComposeFilePath === null) {
            $this->finalComposeFilePath = $fileDir . DIRECTORY_SEPARATOR . 'docker-compose-tmp-' . uniqid() . '.yml';
        }

        Writer::overwrite($this->finalComposeFilePath, $yamlContent);
    }

    public function removeFinalDockerComposeFile(): void
    {
        if($this->finalComposeFilePath !== null) {
            Writer::remove($this->finalComposeFilePath);
        }
    }

    public function getFinalDockerComposeFilePath(): ?string
    {
        return $this->finalComposeFilePath;
    }

    public function copyFinalDockerComposeFile(string $destinationDir): void
    {
        if($this->finalComposeFilePath !== null && file_exists($this->finalComposeFilePath)) {
            $newPath = $destinationDir . DIRECTORY_SEPARATOR . basename($this->finalComposeFilePath);
            copy($this->finalComposeFilePath, $newPath);
        }
    }
}