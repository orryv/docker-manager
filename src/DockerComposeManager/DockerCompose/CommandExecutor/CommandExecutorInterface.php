<?php

namespace Orryv\DockerComposeManager\DockerCompose\CommandExecutor;

/**
 * Abstraction for running docker-compose commands.
 */
interface CommandExecutorInterface
{
    /**
     * Start a command asynchronously and return immediately.
     */
    public function executeAsync(
        string $id,
        string $command,
        string $executionPath,
        ?string $tmpIdentifier = null
    ): \Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;

    public function getRegisteredPids(): array;

    public function getOutputFiles(): array;

    /**
     * Optionally, clean up and close any running processes.
     */
    public function closeAllProcesses(): void;
}
