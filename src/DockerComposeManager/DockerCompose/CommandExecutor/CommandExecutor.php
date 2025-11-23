<?php

namespace Orryv\DockerComposeManager\DockerCompose\CommandExecutor;

use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;

class CommandExecutor implements CommandExecutorInterface
{
    /** @var array<int|null> */
    private array $pids = [];

    /** @var string[] */
    private array $outputFiles = [];

    /**
     * @var array<int, resource> Process handles keyed by PID (or by index if PID is null)
     */
    private array $processes = [];

    /**
     * Start a command asynchronously and return immediately.
     *
     * - Uses proc_open() so PHP does NOT wait for the command to finish.
     * - STDOUT and STDERR go into $outputFile.
     * - Works the same way on Windows and Unix.
     */
    public function executeAsync(string $command, string $executionPath, ?string $tmpIdentifier = null): array
    {
        if (!\is_dir($executionPath)) {
            throw new DockerComposeManagerException(
                'Execution path does not exist: ' . $executionPath
            );
        }

        $outputFile = $this->buildOutputFilePath($executionPath, $tmpIdentifier);

        if (!\file_exists($outputFile) && !@\touch($outputFile)) {
            throw new DockerComposeManagerException(
                'Cannot create output file: ' . $outputFile
            );
        }

        // Null device for STDIN so the child process doesn't wait on input
        $nullDevice = (\PHP_OS_FAMILY === 'Windows') ? 'NUL' : '/dev/null';

        $descriptorSpec = [
            // Child reads nothing from STDIN
            0 => ['file', $nullDevice, 'r'],
            // STDOUT -> log file (append)
            1 => ['file', $outputFile, 'a'],
            // STDERR -> same log file
            2 => ['file', $outputFile, 'a'],
        ];

        $pipes = [];
        $process = @\proc_open($command, $descriptorSpec, $pipes, $executionPath);

        if (!\is_resource($process)) {
            throw new DockerComposeManagerException(
                'Failed to start command asynchronously: could not open process.'
            );
        }

        // Get initial status (non-blocking)
        $status = \proc_get_status($process);

        // If the process already exited with an error immediately, treat that as a failure
        if ($status !== false && $status['running'] === false && $status['exitcode'] !== 0) {
            // Clean up and read a bit of the log file for error context
            \proc_close($process);

            $logSnippet = '';
            if (\is_readable($outputFile)) {
                $logSnippet = \trim((string) @\file_get_contents($outputFile));
            }

            throw new DockerComposeManagerException(\sprintf(
                'Command exited immediately with code %d. Output: %s',
                $status['exitcode'],
                $logSnippet
            ));
        }

        $pid = $status['pid'] ?? null;

        // Keep the process handle alive so PHP does NOT close/wait on it yet
        $key = $pid ?? (\count($this->processes) + 1);
        $this->processes[$key] = $process;

        $this->pids[]        = $pid;
        $this->outputFiles[] = $outputFile;

        return [
            'pid'         => $pid,
            'output_file' => $outputFile,
        ];
    }

    public function getRegisteredPids(): array
    {
        return $this->pids;
    }

    public function getOutputFiles(): array
    {
        return $this->outputFiles;
    }

    /**
     * Optionally, you can call this later to clean up all processes.
     * This will wait for them to exit.
     */
    public function closeAllProcesses(): void
    {
        foreach ($this->processes as $key => $process) {
            if (\is_resource($process)) {
                \proc_close($process);
            }
            unset($this->processes[$key]);
        }
    }

    private function buildOutputFilePath(string $executionPath, ?string $tmpIdentifier): string
    {
        $identifier = $tmpIdentifier ?? \uniqid('docker-compose-', true);

        return \rtrim($executionPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'docker-compose-output-tmp-' . $identifier . '.log';
    }
}
