<?php

namespace Tests\Unit\DockerCompose\CommandExecution;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollectionFactory;
use Orryv\DockerComposeManager\DockerCompose\CommandExecutor\CommandExecutorInterface;
use PHPUnit\Framework\TestCase;

class CommandExecutionResultsCollectionFactoryTest extends TestCase
{
    public function testCreateFromCommandsUsesExecutor(): void
    {
        $commands = [
            'one' => ['id' => 'one', 'command' => 'echo one', 'tmp_identifier' => 'abc'],
            'two' => ['id' => 'two', 'command' => 'echo two', 'tmp_identifier' => null],
        ];

        $executionResultOne = new CommandExecutionResult('one', 1, '/tmp/one.log', 'abc');
        $executionResultTwo = new CommandExecutionResult('two', 2, '/tmp/two.log', null);

        $executor = $this->createMock(CommandExecutorInterface::class);
        $calls = [];
        $executor->expects($this->exactly(2))
            ->method('executeAsync')
            ->willReturnCallback(function (...$args) use (&$calls, $executionResultOne, $executionResultTwo) {
                $calls[] = $args;

                return count($calls) === 1 ? $executionResultOne : $executionResultTwo;
            });

        $factory = new CommandExecutionResultsCollectionFactory();

        $results = $factory->createFromCommands($commands, $executor, '/tmp');

        $this->assertInstanceOf(CommandExecutionResultsCollection::class, $results);
        $this->assertSame($executionResultOne, $results->get('one'));
        $this->assertSame($executionResultTwo, $results->get('two'));
        $this->assertSame([
            ['one', 'echo one', '/tmp', 'abc'],
            ['two', 'echo two', '/tmp', null],
        ], $calls);
    }
}
