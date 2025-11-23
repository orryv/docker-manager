<?php

namespace Orryv\DockerComposeManager\DockerCompose\Definition;

use Orryv\DockerComposeManager\DockerCompose\Definition\Definition;
use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;

class DefinitionFactory implements DefinitionFactoryInterface
{
    public function create(array $dockerComposeArray, ?YamlParserInterface $yamlParser = null): Definition
    {
        return new Definition($dockerComposeArray, $yamlParser);
    }
}
