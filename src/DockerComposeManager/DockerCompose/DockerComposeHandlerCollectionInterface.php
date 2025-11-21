<?php

namespace Orryv\DockerComposeManager\DockerCompose;

use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandler;

interface DockerComposeHandlerCollectionInterface
{
    public function add(string $id, DockerComposeHandler $config): void;

    public function get(string $id): DockerComposeHandler;

    public function getCurrent(): ?DockerComposeHandler;

    public function getRegisteredIds(): array;
}
