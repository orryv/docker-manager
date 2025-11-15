<?php

namespace Orryv\DockerManager\Process;

class ProcessContext
{
    private string $command;
    private string $workingDirectory;
    private array $environment;
    private bool $isWindows;
    private bool $keepLogFile;
    private ?string $debugPath;
    private int $maxIterations;
    private int $pollIntervalMicros;

    public function __construct(
        string $command,
        string $workingDirectory,
        array $environment,
        bool $isWindows,
        bool $keepLogFile,
        ?string $debugPath,
        int $maxIterations = 3000,
        int $pollIntervalMicros = 250000
    ) {
        $this->command = $command;
        $this->workingDirectory = $workingDirectory;
        $this->environment = $environment;
        $this->isWindows = $isWindows;
        $this->keepLogFile = $keepLogFile;
        $this->debugPath = $debugPath;
        $this->maxIterations = $maxIterations;
        $this->pollIntervalMicros = $pollIntervalMicros;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getWorkingDirectory(): string
    {
        return $this->workingDirectory;
    }

    public function getEnvironment(): array
    {
        return $this->environment;
    }

    public function isWindows(): bool
    {
        return $this->isWindows;
    }

    public function shouldKeepLogFile(): bool
    {
        return $this->keepLogFile;
    }

    public function getDebugPath(): ?string
    {
        return $this->debugPath;
    }

    public function getMaxIterations(): int
    {
        return $this->maxIterations;
    }

    public function getPollIntervalMicros(): int
    {
        return $this->pollIntervalMicros;
    }
}
