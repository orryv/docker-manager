<?php

namespace Tests\Support;

use Orryv\DockerManager\Process\ProcessContext;
use Orryv\DockerManager\Process\ProcessResult;
use Orryv\DockerManager\Process\ProcessRunnerInterface;

class FakeProcessRunner implements ProcessRunnerInterface
{
    private array $logChunks;
    private int $exitCode;
    private bool $timedOut;

    public array $commands = [];
    public array $workingDirectories = [];
    public array $environments = [];

    public function __construct(array $logChunks = [], int $exitCode = 0, bool $timedOut = false)
    {
        $this->logChunks = $logChunks;
        $this->exitCode = $exitCode;
        $this->timedOut = $timedOut;
    }

    public function run(ProcessContext $context, callable $tick): ProcessResult
    {
        $this->commands[] = $context->getCommand();
        $this->workingDirectories[] = $context->getWorkingDirectory();
        $this->environments[] = $context->getEnvironment();

        $logFile = tempnam(sys_get_temp_dir(), 'docker-manager-test-');
        $content = '';
        if ($logFile === false) {
            throw new \RuntimeException('Unable to create temporary log file.');
        }

        foreach ($this->logChunks as $chunk) {
            $content .= $chunk;
            file_put_contents($logFile, $content);
            $tick($content);
        }

        if (!$this->logChunks) {
            file_put_contents($logFile, $content);
            $tick($content);
        }

        return new ProcessResult($this->exitCode, $logFile, $this->timedOut);
    }
}
