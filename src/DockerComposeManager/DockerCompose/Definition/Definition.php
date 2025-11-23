<?php 

namespace Orryv\DockerComposeManager\DockerCompose\Definition;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\DockerComposeManager\Exceptions\YamlParserException;
// use Orryv\DockerComposeManager\FileSystem\Writer;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionInterface;

/**
 * Class Definition
 * 
 * Used to handle Docker Compose configurations.
 *  Meaning it will hold the current state of a Docker Compose file in array format,
 *  and provide methods to manipulate and retrieve information from it.
 */
class Definition implements DefinitionInterface
{
    private array $dockerCompose;
    private ?string $tmpFilePath = null;
    private ?YamlParserInterface $yaml_parser;

    public function __construct(array $dockerComposeArray, ?YamlParserInterface $yaml_parser = null)
    {
        $this->dockerCompose = $dockerComposeArray;
        $this->yaml_parser = $yaml_parser;
    }

    /**
     * Removes all temporary files created by this handler.
     */
    // public function removeTmpFiles(): void
    // {
    //     if($this->tmpFilePath !== null) {
    //         Writer::remove($this->tmpFilePath);
    //     }
    // }

    // public function copyTmpFiles(string $destinationDir): void
    // {
    //     if($this->tmpFilePath !== null && file_exists($this->tmpFilePath)) {
    //         $newPath = $destinationDir . DIRECTORY_SEPARATOR . basename($this->tmpFilePath);
    //         copy($this->tmpFilePath, $newPath);
    //     }
    // }

    // public function saveTmpDockerComposeFile(string $fileDir): void
    // {
    //     if ($this->yaml_parser === null) {
    //         throw new YamlParserException(
    //             'No YAML parser configured. Construct with one, or use fromYamlArray() to build from an array directly.'
    //         );
    //     }

    //     $yamlContent = $this->yaml_parser->build($this->dockerCompose);

    //     if($this->tmpFilePath === null) {
    //         $this->tmpFilePath = $fileDir . DIRECTORY_SEPARATOR . 'docker-compose-tmp-' . uniqid() . '.yml';
    //     }

    //     Writer::overwrite($this->tmpFilePath, $yamlContent);
    // }

    // public function getTmpFilePath(): ?string
    // {
    //     return $this->tmpFilePath;
    // }

    public function toArray(): array
    {
        return $this->dockerCompose;
    }
}