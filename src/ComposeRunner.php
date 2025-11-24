<?php

namespace Orryv;

use Orryv\DockerComposeManager\CommandBuilder\DockerComposeCommandBuilder;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollectionFactory;
use Orryv\DockerComposeManager\DockerCompose\CommandExecutor\CommandExecutor;
use Orryv\DockerComposeManager\DockerCompose\CommandExecutor\CommandExecutorInterface;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionInterface as DockerComposeDefinitionInterface;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerInterface;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;

/**
 * Responsible for preparing and executing docker-compose commands.
 */
class ComposeRunner
{
    /** @var array<string, CommandExecutionResult> */
    private array $executionResults = [];

    /**
     * @param CommandExecutorInterface|null $commandExecutor Runner responsible for executing docker-compose commands.
     * @param CommandExecutionResultsCollectionFactory|null $executionResultsCollectionFactory Factory producing collections
     *     from executed commands.
     */
    public function __construct(
        private CommandExecutorInterface $commandExecutor = new CommandExecutor(),
        private CommandExecutionResultsCollectionFactory $executionResultsCollectionFactory = new CommandExecutionResultsCollectionFactory(),
    ) {
    }

    /**
     * Execute docker-compose commands for the provided contexts.
     *
     * @param array<int, array{id: string, definition: DockerComposeDefinitionInterface, fileHandler: FileHandlerInterface}> $contexts
     */
    public function start(
        array $contexts,
        string $executionPath,
        string|array|null $serviceNames = null,
        bool $rebuildContainers = false
    ): CommandExecutionResultsCollection {
        $commands = [];

        foreach ($contexts as $context) {
            $commands[$context['id']] = [
                'id' => $context['id'],
                'command' => (new DockerComposeCommandBuilder($context['definition'], $context['fileHandler']))
                    ->start($serviceNames, $rebuildContainers),
                'tmp_identifier' => $this->deriveTmpIdentifier($context['fileHandler']->getFinalDockerComposeFilePath()),
            ];
        }

        $executionResults = $this->execute($commands, $executionPath);
        $this->recordResults($executionResults);

        return $executionResults;
    }

    /**
     * Get the running process identifiers by configuration ID.
     */
    public function getRunningPids(): array
    {
        $pids = [];

        foreach ($this->executionResults as $id => $result) {
            $pids[$id] = $result->getPid();
        }

        return $pids;
    }

    /**
     * Get command output log files by configuration ID.
     */
    public function getOutputFiles(): array
    {
        $outputFiles = [];

        foreach ($this->executionResults as $id => $result) {
            $outputFiles[$id] = $result->getOutputFile();
        }

        return $outputFiles;
    }

    /**
     * Fetch execution results for the requested configuration identifiers.
     *
     * @return array<int, CommandExecutionResult>
     */
    public function getExecutionResultsForIds(array $ids): array
    {
        $results = [];

        foreach ($ids as $id) {
            $executionResult = $this->executionResults[$id] ?? null;

            if ($executionResult === null) {
                throw new DockerComposeManagerException("No output file found for config ID: {$id}");
            }

            $results[] = $executionResult;
        }

        return $results;
    }

    /**
     * Retrieve the command output log path for a given configuration.
     */
    public function getOutputFileForId(string $id): string
    {
        if (!array_key_exists($id, $this->executionResults)) {
            throw new DockerComposeManagerException("No final docker-compose output file found for ID: {$id}");
        }

        return $this->executionResults[$id]->getOutputFile();
    }

    /**
     * Remove generated output files and optionally copy them to a debug directory.
     */
    public function cleanupOutputs(?string $debugDir = null): void
    {
        foreach ($this->executionResults as $id => $executionResult) {
            $outputFile = $executionResult->getOutputFile();

            if ($debugDir !== null && file_exists($outputFile)) {
                $outputCopyPath = $debugDir . DIRECTORY_SEPARATOR . basename($outputFile);
                copy($outputFile, $outputCopyPath);
            }

            if (file_exists($outputFile)) {
                unlink($outputFile);
            }

            unset($this->executionResults[$id]);
        }

        $this->commandExecutor->closeAllProcesses();
    }

    /**
     * Execute the provided command definitions within the execution path.
     */
    private function execute(array $commands, string $executionPath): CommandExecutionResultsCollection
    {
        return $this->executionResultsCollectionFactory->createFromCommands(
            $commands,
            $this->commandExecutor,
            $executionPath
        );
    }

    /**
     * Derive the temporary identifier embedded within the docker-compose file name when available.
     */
    private function deriveTmpIdentifier(?string $tmpFilePath): ?string
    {
        if ($tmpFilePath === null) {
            return null;
        }

        $fileName = basename($tmpFilePath);

        if (preg_match('/docker-compose-tmp-([^.]+)\.yml/', $fileName, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Store execution results for later progress tracking and cleanup.
     */
    private function recordResults(CommandExecutionResultsCollection $executionResults): void
    {
        foreach ($executionResults as $executionResult) {
            $this->executionResults[$executionResult->getId()] = $executionResult;
        }
    }
}
