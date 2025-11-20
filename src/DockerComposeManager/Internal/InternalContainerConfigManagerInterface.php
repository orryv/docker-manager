<?php 

namespace Orryv\DockerComposeManager\Internal;

use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandler;

interface InternalContainerConfigManagerInterface
{
    public function add(string $id, DockerComposeHandler $config): void;

    public function get(string $id): DockerComposeHandler;

    public function getCurrent(): ?DockerComposeHandler;

    public function getRegisteredIds(): array;
}