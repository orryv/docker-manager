<?php

namespace Tests\Unit;

use Orryv\ComposeRunner;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollectionFactory;
use Orryv\DockerComposeManager\DockerCompose\CommandExecutor\CommandExecutorInterface;
use Orryv\DockerComposeManager\DockerCompose\Definition\Definition;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerInterface;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;
use PHPUnit\Framework\TestCase;

class ComposeRunnerTest extends TestCase
{
    public function testStartBuildsCommandsAndRecordsResults(): void
    {
        $definition = new Definition(['services' => ['app' => ['container_name' => 'app']]]);
        $tempComposeFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'docker-compose-tmp-abc.yml';
        file_put_contents($tempComposeFile, 'services: {}');

        $fileHandler = new class($definition, $tempComposeFile) implements FileHandlerInterface {
            public function __construct(private Definition $definition, private string $path) {}
            public function getDefinition(): Definition
            {
                return $this->definition;
            }
            public function saveFinalDockerComposeFile(string $fileDir): void {}
            public function removeFinalDockerComposeFile(): void {}
            public function getFinalDockerComposeFilePath(): ?string
            {
                return $this->path;
            }
            public function copyFinalDockerComposeFile(string $destinationDir): void {}
        };

        $executionResults = new CommandExecutionResultsCollection();
        $commandResult = new CommandExecutionResult('one', 1234, '/tmp/output.log', 'abc');
        $executionResults->add($commandResult);

        $factory = $this->createMock(CommandExecutionResultsCollectionFactory::class);
        $factory->expects($this->once())
            ->method('createFromCommands')
            ->with(
                $this->callback(function (array $commands) use ($tempComposeFile) {
                    $this->assertArrayHasKey('one', $commands);
                    $this->assertStringContainsString(escapeshellarg($tempComposeFile), $commands['one']['command']);
                    $this->assertStringContainsString('--build', $commands['one']['command']);
                    $this->assertStringContainsString("'web'", $commands['one']['command']);
                    $this->assertSame('abc', $commands['one']['tmp_identifier']);
                    return true;
                }),
                $this->isInstanceOf(CommandExecutorInterface::class),
                '/tmp'
            )
            ->willReturn($executionResults);

        $runner = new ComposeRunner($this->createMock(CommandExecutorInterface::class), $factory);

        $resultCollection = $runner->start([
            [
                'id' => 'one',
                'definition' => $definition,
                'fileHandler' => $fileHandler,
            ],
        ], '/tmp', ['web'], true);

        $this->assertSame($executionResults, $resultCollection);
        $this->assertSame(['one' => 1234], $runner->getRunningPids());
        $this->assertSame(['one' => '/tmp/output.log'], $runner->getOutputFiles());
        $this->assertSame('/tmp/output.log', $runner->getOutputFileForId('one'));

        unlink($tempComposeFile);
    }

    public function testGetExecutionResultsThrowsWhenIdUnknown(): void
    {
        $runner = new ComposeRunner();

        $this->expectException(DockerComposeManagerException::class);
        $runner->getExecutionResultsForIds(['missing']);
    }

    public function testGetOutputFileForIdThrowsWhenMissing(): void
    {
        $runner = new ComposeRunner();

        $this->expectException(DockerComposeManagerException::class);
        $runner->getOutputFileForId('missing');
    }

    public function testCleanupOutputsCopiesAndRemovesFiles(): void
    {
        $runner = new ComposeRunner();
        $tempFile = tempnam(sys_get_temp_dir(), 'runner-log-');
        file_put_contents($tempFile, 'log');

        $executionResult = new CommandExecutionResult('one', null, $tempFile, 'abc');

        $reflection = new \ReflectionClass($runner);
        $property = $reflection->getProperty('executionResults');
        $property->setAccessible(true);
        $property->setValue($runner, ['one' => $executionResult]);

        $debugDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'runner-debug-' . uniqid();
        mkdir($debugDir);

        $runner->cleanupOutputs($debugDir);

        $this->assertFileExists($debugDir . DIRECTORY_SEPARATOR . basename($tempFile));
        $this->assertFileDoesNotExist($tempFile);
        $this->assertSame([], $runner->getRunningPids());

        array_map('unlink', glob($debugDir . DIRECTORY_SEPARATOR . '*') ?: []);
        rmdir($debugDir);
    }
}
