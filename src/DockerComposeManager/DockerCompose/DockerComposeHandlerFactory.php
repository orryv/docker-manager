<?php

namespace Orryv\DockerComposeManager\DockerCompose;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;

class DockerComposeHandlerFactory implements DockerComposeHandlerFactoryInterface
{
    public function create(array $dockerComposeArray, ?YamlParserInterface $yamlParser = null): DockerComposeHandler
    {
        return new DockerComposeHandler($dockerComposeArray, $yamlParser);
    }
}
