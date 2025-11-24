<?php

namespace Tests\Unit\DockerCompose\Definition;

use Orryv\DockerComposeManager\DockerCompose\Definition\Definition;
use PHPUnit\Framework\TestCase;

class DefinitionTest extends TestCase
{
    public function testToArrayReturnsOriginalData(): void
    {
        $data = ['services' => ['app' => ['container_name' => 'app']]];
        $definition = new Definition($data);

        $this->assertSame($data, $definition->toArray());
    }
}
