<?php

namespace Orryv\DockerComposeManager\DockerCompose\Definition;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\DockerComposeManager\DockerCompose\Definition\Definition;

interface DefinitionFactoryInterface
{
    public function create(array $dockerComposeArray, ?YamlParserInterface $yamlParser = null): Definition;
}
