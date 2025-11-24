<?php

namespace Tests\Unit\DockerCompose\FileHandler;

use Orryv\DockerComposeManager\DockerCompose\Definition\Definition;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandler;
use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use PHPUnit\Framework\TestCase;

class FileHandlerTest extends TestCase
{
    private function createParser(): YamlParserInterface
    {
        return new class implements YamlParserInterface {
            public function parse(string $yaml_content): array
            {
                return [];
            }

            public function build(array $data): string
            {
                return 'built-yaml';
            }
        };
    }

    public function testSaveFinalDockerComposeFileWritesFile(): void
    {
        $definition = new Definition(['services' => ['app' => ['container_name' => 'app']]]);
        $handler = new FileHandler($definition, $this->createParser());
        $tempDir = sys_get_temp_dir();

        $handler->saveFinalDockerComposeFile($tempDir);

        $this->assertNotNull($handler->getFinalDockerComposeFilePath());
        $this->assertFileExists($handler->getFinalDockerComposeFilePath());
        $this->assertSame('built-yaml', file_get_contents($handler->getFinalDockerComposeFilePath()));

        $handler->removeFinalDockerComposeFile();
    }

    public function testSaveFinalDockerComposeFileThrowsWithoutParser(): void
    {
        $definition = new Definition(['services' => ['app' => ['container_name' => 'app']]]);
        $handler = new FileHandler($definition, null);

        $this->expectException(YamlParserException::class);
        $handler->saveFinalDockerComposeFile(sys_get_temp_dir());
    }

    public function testCopyFinalDockerComposeFileCopiesFile(): void
    {
        $definition = new Definition(['services' => ['app' => ['container_name' => 'app']]]);
        $handler = new FileHandler($definition, $this->createParser());
        $tempDir = sys_get_temp_dir();

        $handler->saveFinalDockerComposeFile($tempDir);
        $sourcePath = $handler->getFinalDockerComposeFilePath();

        $destinationDir = $tempDir . DIRECTORY_SEPARATOR . 'file-handler-copy-' . uniqid();
        mkdir($destinationDir);

        $handler->copyFinalDockerComposeFile($destinationDir);

        $this->assertFileExists($destinationDir . DIRECTORY_SEPARATOR . basename($sourcePath));

        $handler->removeFinalDockerComposeFile();
        array_map('unlink', glob($destinationDir . DIRECTORY_SEPARATOR . '*') ?: []);
        rmdir($destinationDir);
    }
}
