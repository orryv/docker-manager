<?php 

namespace Orryv\DockerComposeManager\DockerCompose\FileHandler;

use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionInterface;

interface FileHandlerInterface
{
    public function getDefinition(): DefinitionInterface;

    public function saveFinalDockerComposeFile(string $fileDir): void;

    public function removeFinalDockerComposeFile(): void;

    public function getFinalDockerComposeFilePath(): ?string;

    public function copyFinalDockerComposeFile(string $destinationDir): void;
}