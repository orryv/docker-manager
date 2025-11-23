<?php 

namespace Orryv\DockerComposeManager\DockerCompose\Definition;

interface DefinitionInterface
{
    // public function removeTmpFiles(): void;

    // public function copyTmpFiles(string $destinationDir): void;

    // public function saveTmpDockerComposeFile(string $fileDir): void;

    // public function getTmpFilePath(): ?string;

    public function toArray(): array;
}