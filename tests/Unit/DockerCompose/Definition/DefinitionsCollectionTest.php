<?php

namespace Tests\Unit\DockerCompose\Definition;

use InvalidArgumentException;
use Orryv\DockerComposeManager\DockerCompose\Definition\Definition;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionsCollection;
use PHPUnit\Framework\TestCase;

class DefinitionsCollectionTest extends TestCase
{
    public function testAddAndGetCurrent(): void
    {
        $collection = new DefinitionsCollection();
        $definition = new Definition(['services' => ['app' => ['container_name' => 'app']]]);

        $collection->add('config-1', $definition);

        $this->assertSame($definition, $collection->getCurrent());
        $this->assertSame(['config-1'], $collection->getRegisteredIds());
    }

    public function testActivateSwitchesCurrent(): void
    {
        $collection = new DefinitionsCollection();
        $first = new Definition(['services' => ['app' => ['container_name' => 'app']]]);
        $second = new Definition(['services' => ['api' => ['container_name' => 'api']]]);

        $collection->add('first', $first);
        $collection->add('second', $second);

        $collection->activate('first');

        $this->assertSame($first, $collection->getCurrent());
    }

    public function testGetThrowsForUnknownId(): void
    {
        $collection = new DefinitionsCollection();

        $this->expectException(InvalidArgumentException::class);
        $collection->get('missing');
    }

    public function testActivateThrowsForUnknownId(): void
    {
        $collection = new DefinitionsCollection();

        $this->expectException(InvalidArgumentException::class);
        $collection->activate('missing');
    }
}
