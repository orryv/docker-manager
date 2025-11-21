<?php

namespace Orryv\DockerComposeManager\DockerCompose;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;

interface DockerComposeHandlerFactoryInterface
{
    public function create(array $dockerComposeArray, ?YamlParserInterface $yamlParser = null): DockerComposeHandler;
}
