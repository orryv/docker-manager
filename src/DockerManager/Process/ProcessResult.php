<?php

namespace Orryv\DockerManager\Process;

class ProcessResult
{
    private int $exitCode;
    private ?string $logFilePath;
    private bool $timedOut;

    public function __construct(int $exitCode, ?string $logFilePath, bool $timedOut)
    {
        $this->exitCode = $exitCode;
        $this->logFilePath = $logFilePath;
        $this->timedOut = $timedOut;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getLogFilePath(): ?string
    {
        return $this->logFilePath;
    }

    public function hasTimedOut(): bool
    {
        return $this->timedOut;
    }
}
