<?php

namespace Tests\Unit\DockerCompose\CommandExecutor;

use Orryv\DockerComposeManager\DockerCompose\CommandExecutor\CommandExecutor;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;
use PHPUnit\Framework\TestCase;

class CommandExecutorTest extends TestCase
{
    public function testExecuteAsyncCreatesOutputFileAndTracksPid(): void
    {
        $executor = new CommandExecutor();
        $tempDir = sys_get_temp_dir();
        $command = 'php -r "echo \"hello\";"';

        $result = $executor->executeAsync('test', $command, $tempDir);

        $this->assertFileExists($result->getOutputFile());
        $this->assertSame('test', $result->getId());
        $this->assertContains($result->getPid(), $executor->getRegisteredPids());
        $this->assertContains($result->getOutputFile(), $executor->getOutputFiles());

        // allow process to finish writing
        usleep(200000);
        $this->assertStringContainsString('hello', file_get_contents($result->getOutputFile()));

        $executor->closeAllProcesses();
        @unlink($result->getOutputFile());
    }

    public function testExecuteAsyncThrowsWhenExecutionPathMissing(): void
    {
        $executor = new CommandExecutor();

        $this->expectException(DockerComposeManagerException::class);
        $executor->executeAsync('test', 'echo hi', '/path/that/does/not/exist');
    }
}
