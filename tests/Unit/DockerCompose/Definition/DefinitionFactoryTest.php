<?php

namespace Tests\Unit\DockerCompose\Definition;

use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionFactory;
use Orryv\DockerComposeManager\DockerCompose\Definition\Definition;
use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use PHPUnit\Framework\TestCase;

class DefinitionFactoryTest extends TestCase
{
    public function testCreateReturnsDefinition(): void
    {
        $factory = new DefinitionFactory();
        $parser = $this->createMock(YamlParserInterface::class);

        $definition = $factory->create(['services' => ['app' => ['container_name' => 'app']]], $parser);

        $this->assertInstanceOf(Definition::class, $definition);
    }
}
