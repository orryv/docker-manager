<?php

namespace Tests\Unit\DockerCompose\CommandExecution;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use PHPUnit\Framework\TestCase;

class CommandExecutionResultsCollectionTest extends TestCase
{
    public function testAddAndRetrieveResults(): void
    {
        $collection = new CommandExecutionResultsCollection();
        $result = new CommandExecutionResult('one', 1, '/tmp/one.log');

        $collection->add($result);

        $this->assertSame($result, $collection->get('one'));
        $this->assertCount(1, $collection);
        $this->assertSame([$result], iterator_to_array($collection));
    }

    public function testGetReturnsNullWhenMissing(): void
    {
        $collection = new CommandExecutionResultsCollection();

        $this->assertNull($collection->get('missing'));
    }
}
