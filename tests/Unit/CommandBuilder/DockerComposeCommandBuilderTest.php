<?php

namespace Tests\Unit\CommandBuilder;

use Orryv\DockerComposeManager\CommandBuilder\DockerComposeCommandBuilder;
use Orryv\DockerComposeManager\DockerCompose\Definition\Definition;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerInterface;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;
use PHPUnit\Framework\TestCase;

class DockerComposeCommandBuilderTest extends TestCase
{
    private function createFileHandler(string $path, Definition $definition): FileHandlerInterface
    {
        return new class($path, $definition) implements FileHandlerInterface {
            public function __construct(private string $path, private Definition $definition) {}
            public function getDefinition(): Definition
            {
                return $this->definition;
            }
            public function saveFinalDockerComposeFile(string $fileDir): void {}
            public function removeFinalDockerComposeFile(): void {}
            public function getFinalDockerComposeFilePath(): ?string
            {
                return $this->path;
            }
            public function copyFinalDockerComposeFile(string $destinationDir): void {}
        };
    }

    public function testStartBuildsCommandWithServicesAndBuildFlag(): void
    {
        $definition = new Definition(['services' => ['app' => ['container_name' => 'app']]]);
        $composeFile = tempnam(sys_get_temp_dir(), 'compose-') . '.yml';
        touch($composeFile);
        $fileHandler = $this->createFileHandler($composeFile, $definition);

        $builder = new DockerComposeCommandBuilder($definition, $fileHandler);
        $command = $builder->start(['web', ''], true);

        $this->assertStringContainsString('docker-compose -f ' . escapeshellarg($composeFile) . ' up -d --build', $command);
        $this->assertStringContainsString('web', $command);

        unlink($composeFile);
    }

    public function testStartThrowsWhenFileMissing(): void
    {
        $definition = new Definition(['services' => ['app' => ['container_name' => 'app']]]);
        $composeFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'missing-compose.yml';
        @unlink($composeFile);
        $fileHandler = $this->createFileHandler($composeFile, $definition);

        $builder = new DockerComposeCommandBuilder($definition, $fileHandler);

        $this->expectException(DockerComposeManagerException::class);
        $builder->start();
    }
}
