<?php

namespace Tests\Unit\DockerCompose\OutputParser;

use InvalidArgumentException;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParser;
use PHPUnit\Framework\TestCase;

class OutputParserTest extends TestCase
{
    private function createExecutionResult(string $file): CommandExecutionResult
    {
        return new CommandExecutionResult('config', null, $file);
    }

    public function testParseSuccessfulOutput(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'output-parser-');
        $content = "Network demo created\nContainer app started\nContainer db running\n";
        file_put_contents($tempFile, $content);

        $parser = new OutputParser();
        $result = $parser->parse($this->createExecutionResult($tempFile));

        $this->assertSame('config', $result->getId());
        $this->assertSame(['app' => 'started', 'db' => 'running'], $result->getContainerStates());
        $this->assertTrue($result->isContainerSuccessful('app'));
        $this->assertTrue($result->isContainerSuccessful('db'));
        $this->assertSame(['demo' => 'created'], $result->getNetworkStates());
        $this->assertTrue($result->isNetworkSuccessful('demo'));
        $this->assertTrue($result->isFinishedExecuting());
        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->hasErrors());

        unlink($tempFile);
    }

    public function testParseRecordsErrorsAndBuildLine(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'output-parser-');
        $content = "#12 1.23 building\nError failed to start";
        file_put_contents($tempFile, $content);

        $parser = new OutputParser();
        $result = $parser->parse($this->createExecutionResult($tempFile));

        $this->assertSame('#12 1.23 building', $result->getBuildLastLine());
        $this->assertSame(['Error failed to start'], $result->getErrors());
        $this->assertTrue($result->isFinishedExecuting());
        $this->assertFalse($result->isSuccessful());

        unlink($tempFile);
    }

    public function testParseThrowsWhenFileMissing(): void
    {
        $parser = new OutputParser();

        $this->expectException(InvalidArgumentException::class);
        $parser->parse($this->createExecutionResult('/nonexistent.log'));
    }
}
