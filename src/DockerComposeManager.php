<?php 

namespace Orryv;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;
use Orryv\DockerComposeManager\DockerComposeConfig;
use Orryv\DockerComposeManager\FileSystem\Reader;
use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandler;
use Orryv\DockerComposeManager\Validation\DockerComposeValidator;

class DockerComposeManager
{
    /**
     * Instance we use to parse .yml files.
     *  Nullable because we allow building directly from an array.
     */
    private ?YamlParserInterface $yaml_parser;
    private ?DockerComposeHandler $dockerCompose = null;


    public function __construct(YamlParserInterface|null $yaml_parser = null)
    {
        $this->yaml_parser = $yaml_parser;
    }

    public function fromDockerComposeFile(string $id, string $file_path): self
    {
        $dockerFileContents = Reader::readFile($file_path);
        $this->dockerCompose = new DockerComposeHandler($this->getYamlParser()->parse($dockerFileContents));
        DockerComposeValidator::isValid($this->dockerCompose->toArray()); // TODO: move to execution side

        return $this;
    }

    public function fromYamlArray(string $id, array $yaml_array): self
    {
        DockerComposeValidator::isValid($yaml_array); // TODO: move to execution side
        $this->dockerCompose = new DockerComposeHandler($yaml_array);

        return $this;
    }

    ######################
    ## Internal methods ##
    ######################

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