<?php

namespace Tests\Unit\DockerCompose\FileHandler;

use Orryv\DockerComposeManager\DockerCompose\Definition\Definition;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerFactory;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandler;
use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use PHPUnit\Framework\TestCase;

class FileHandlerFactoryTest extends TestCase
{
    public function testCreateReturnsFileHandler(): void
    {
        $factory = new FileHandlerFactory();
        $definition = new Definition(['services' => ['app' => ['container_name' => 'app']]]);
        $parser = $this->createMock(YamlParserInterface::class);

        $handler = $factory->create($definition, $parser);

        $this->assertInstanceOf(FileHandler::class, $handler);
    }
}
