<?php

namespace Orryv\DockerComposeManager\DockerCompose\FileHandler;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionInterface;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandler;

interface FileHandlerFactoryInterface
{
    public function create(DefinitionInterface $definition, ?YamlParserInterface $yaml_parser = null): FileHandler;
}
