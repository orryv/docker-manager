<?php

namespace Orryv\DockerComposeManager\DockerCompose\DefinitionsCollection;

use Orryv\DockerComposeManager\DockerCompose\Definition\Definition;

interface DefinitionsCollectionInterface
{
    public function add(string $id, Definition $config): void;

    public function get(string $id): Definition;

    public function getCurrent(): ?Definition;

    public function getRegisteredIds(): array;
}
