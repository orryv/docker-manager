<?php

namespace Tests\Unit\DockerCompose\CommandExecution;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use PHPUnit\Framework\TestCase;

class CommandExecutionResultTest extends TestCase
{
    public function testGettersExposeData(): void
    {
        $result = new CommandExecutionResult('config-1', 123, '/tmp/output.log', 'abc');

        $this->assertSame('config-1', $result->getId());
        $this->assertSame(123, $result->getPid());
        $this->assertSame('/tmp/output.log', $result->getOutputFile());
        $this->assertSame('abc', $result->getTmpIdentifier());
    }
}
