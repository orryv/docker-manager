<?php

namespace Tests\Unit;

use Orryv\ComposeRunner;
use Orryv\ConfigurationManager;
use Orryv\DockerComposeManager;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultsCollectionInterface;
use Orryv\ProgressTracker;
use PHPUnit\Framework\TestCase;

class DockerComposeManagerTest extends TestCase
{
    public function testStartCoordinatesRunnerAndProgress(): void
    {
        $config = $this->createMock(ConfigurationManager::class);
        $runner = $this->createMock(ComposeRunner::class);
        $progress = $this->createMock(ProgressTracker::class);

        $config->expects($this->once())
            ->method('buildExecutionContexts')
            ->with(null)
            ->willReturn(['context']);
        $config->expects($this->once())
            ->method('getExecutionPath')
            ->willReturn('/tmp');

        $executionResults = new CommandExecutionResultsCollection();
        $runner->expects($this->once())
            ->method('start')
            ->with(['context'], '/tmp', null, false)
            ->willReturn($executionResults);

        $parsedResults = $this->createMock(OutputParserResultsCollectionInterface::class);
        $parsedResults->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(true);

        $progress->expects($this->once())
            ->method('parseBlocking')
            ->with($executionResults, null, 123000)
            ->willReturn($parsedResults);

        $progress->expects($this->once())
            ->method('waitForHealthyContainers')
            ->with($parsedResults, 123000)
            ->willReturn(true);

        $manager = new DockerComposeManager($config, $runner, $progress);

        $this->assertTrue($manager->start(null, null, false, true, 123000));
    }

    public function testAsyncAndProgressHelpers(): void
    {
        $config = $this->createMock(ConfigurationManager::class);
        $runner = $this->createMock(ComposeRunner::class);
        $progress = $this->createMock(ProgressTracker::class);

        $config->expects($this->once())
            ->method('buildExecutionContexts')
            ->with(['id'])
            ->willReturn(['context']);
        $config->expects($this->once())
            ->method('getExecutionPath')
            ->willReturn('/tmp');

        $asyncResults = $this->createMock(CommandExecutionResultsCollection::class);
        $runner->expects($this->once())
            ->method('start')
            ->with(['context'], '/tmp', ['service'], true)
            ->willReturn($asyncResults);

        $config->expects($this->atLeastOnce())
            ->method('normalizeIds')
            ->with('id')
            ->willReturn(['id']);

        $runner->expects($this->atLeastOnce())
            ->method('getExecutionResultsForIds')
            ->with(['id'])
            ->willReturn(['result']);

        $progressResult = $this->createMock(OutputParserResultsCollectionInterface::class);

        $progress->expects($this->once())
            ->method('getProgress')
            ->with(['result'])
            ->willReturn($progressResult);

        $progress->expects($this->once())
            ->method('isFinished')
            ->with(['result'])
            ->willReturn(true);

        $manager = new DockerComposeManager($config, $runner, $progress);

        $this->assertSame($asyncResults, $manager->startAsync(['id'], ['service'], true));
        $this->assertSame($progressResult, $manager->getProgress('id'));
        $this->assertTrue($manager->isFinished('id'));
    }

    public function testStartReturnsFalseWhenParseFails(): void
    {
        $config = $this->createMock(ConfigurationManager::class);
        $runner = $this->createMock(ComposeRunner::class);
        $progress = $this->createMock(ProgressTracker::class);

        $config->expects($this->once())
            ->method('buildExecutionContexts')
            ->with(null)
            ->willReturn(['context']);
        $config->expects($this->once())
            ->method('getExecutionPath')
            ->willReturn('/tmp');

        $executionResults = new CommandExecutionResultsCollection();
        $runner->expects($this->once())
            ->method('start')
            ->with(['context'], '/tmp', null, false)
            ->willReturn($executionResults);

        $parsedResults = $this->createMock(OutputParserResultsCollectionInterface::class);
        $parsedResults->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(false);

        $progress->expects($this->once())
            ->method('parseBlocking')
            ->with($executionResults, null, 250000)
            ->willReturn($parsedResults);

        $progress->expects($this->never())->method('waitForHealthyContainers');

        $manager = new DockerComposeManager($config, $runner, $progress);

        $this->assertFalse($manager->start());
    }

    public function testStartSkipsHealthWhenDisabled(): void
    {
        $config = $this->createMock(ConfigurationManager::class);
        $runner = $this->createMock(ComposeRunner::class);
        $progress = $this->createMock(ProgressTracker::class);

        $config->expects($this->once())
            ->method('buildExecutionContexts')
            ->with('id')
            ->willReturn(['context']);
        $config->expects($this->once())
            ->method('getExecutionPath')
            ->willReturn('/tmp');

        $executionResults = new CommandExecutionResultsCollection();
        $runner->expects($this->once())
            ->method('start')
            ->with(['context'], '/tmp', null, false)
            ->willReturn($executionResults);

        $parsedResults = $this->createMock(OutputParserResultsCollectionInterface::class);
        $parsedResults->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(true);

        $progress->expects($this->once())
            ->method('parseBlocking')
            ->with($executionResults, null, 500000)
            ->willReturn($parsedResults);

        $progress->expects($this->never())
            ->method('waitForHealthyContainers');

        $manager = new DockerComposeManager($config, $runner, $progress);

        $this->assertTrue($manager->start('id', null, false, false, 500000));
    }

    public function testDebugAndCleanup(): void
    {
        $config = $this->createMock(ConfigurationManager::class);
        $runner = $this->createMock(ComposeRunner::class);
        $progress = $this->createMock(ProgressTracker::class);

        $config->expects($this->once())
            ->method('setDebugDirectory')
            ->with('/debug');

        $config->expects($this->exactly(2))
            ->method('cleanup');
        $runner->expects($this->exactly(2))
            ->method('cleanupOutputs')
            ->with('/debug');

        $manager = new DockerComposeManager($config, $runner, $progress);

        $manager->debug('/debug');
        $manager->cleanup();
        unset($manager);
    }
}
