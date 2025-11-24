<?php

namespace Tests\Unit;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\BlockingOutputParserInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResult;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultsCollection;
use Orryv\ProgressTracker;
use PHPUnit\Framework\TestCase;

class ProgressTrackerTest extends TestCase
{
    public function testParseBlockingUsesCallback(): void
    {
        $resultsCollection = new CommandExecutionResultsCollection();
        $parserResults = new OutputParserResultsCollection();

        $blockingParser = $this->createMock(BlockingOutputParserInterface::class);
        $blockingParser->expects($this->once())
            ->method('parse')
            ->with($resultsCollection, 250000, $this->callback(function ($callback) {
                $this->assertIsCallable($callback);
                return true;
            }))
            ->willReturn($parserResults);

        $progressTracker = new ProgressTracker(
            $this->createMock(OutputParserInterface::class),
            $blockingParser
        );

        $captured = false;
        $progressTracker->onProgress(function () use (&$captured) {
            $captured = true;
        });

        $this->assertSame($parserResults, $progressTracker->parseBlocking($resultsCollection));
        $this->assertFalse($captured, 'Default callback is passed to blocking parser only');
    }

    public function testGetProgressParsesEachExecutionResult(): void
    {
        $executionResults = [
            new CommandExecutionResult('one', null, '/tmp/one.log'),
            new CommandExecutionResult('two', null, '/tmp/two.log'),
        ];

        $outputParserResult = new OutputParserResult('id', [], [], [], [], [], null, true);

        $outputParser = $this->createMock(OutputParserInterface::class);
        $outputParser->expects($this->exactly(2))
            ->method('parse')
            ->willReturn($outputParserResult);

        $progressTracker = new ProgressTracker($outputParser, $this->createMock(BlockingOutputParserInterface::class));

        $results = $progressTracker->getProgress($executionResults);

        $this->assertInstanceOf(OutputParserResultsCollection::class, $results);
        $this->assertCount(1, $results);
    }

    public function testIsFinishedDelegatesToProgressResults(): void
    {
        $executionResults = [new CommandExecutionResult('one', null, '/tmp/one.log')];

        $outputParser = $this->createMock(OutputParserInterface::class);
        $outputParser->expects($this->once())
            ->method('parse')
            ->willReturn(new OutputParserResult('one', [], [], [], [], [], null, true));

        $progressTracker = new ProgressTracker($outputParser, $this->createMock(BlockingOutputParserInterface::class));

        $this->assertTrue($progressTracker->isFinished($executionResults));
    }
}
