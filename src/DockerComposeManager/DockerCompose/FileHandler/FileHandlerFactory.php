<?php

namespace Orryv\DockerComposeManager\DockerCompose\FileHandler;

use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandler;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionInterface;
use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;

class FileHandlerFactory implements FileHandlerFactoryInterface
{
    public function create(DefinitionInterface $definition, ?YamlParserInterface $yamlParser = null): FileHandler
    {
        return new FileHandler($definition, $yamlParser);
    }
}
