<?php

namespace Orryv\DockerComposeManager\DockerCompose\CommandExecution;

use Orryv\DockerComposeManager\DockerCompose\CommandExecutor\CommandExecutorInterface;

/**
 * Builds collections of command execution results from configured commands.
 */
class CommandExecutionResultsCollectionFactory
{
    /**
     * Execute the provided commands and aggregate their results.
     *
     * @param array<string, array{id: string, command: string, tmp_identifier: string|null}> $commands
     */
    public function createFromCommands(
        array $commands,
        CommandExecutorInterface $commandExecutor,
        string $executionPath
    ): CommandExecutionResultsCollection {
        $executionResults = new CommandExecutionResultsCollection();

        foreach ($commands as $commandData) {
            $executionResult = $commandExecutor->executeAsync(
                $commandData['id'],
                $commandData['command'],
                $executionPath,
                $commandData['tmp_identifier']
            );

            $executionResults->add($executionResult);
        }

        return $executionResults;
    }
}
