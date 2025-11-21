<?php

namespace Orryv\DockerComposeManager\DockerCompose;

use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;

class CommandExecutor
{
    private array $pids = [];
    private array $outputFiles = [];

    public function executeAsync(string $command, string $executionPath, ?string $tmpIdentifier = null): array
    {
        if (!is_dir($executionPath)) {
            throw new DockerComposeManagerException('Execution path does not exist: ' . $executionPath);
        }

        $outputFile = $this->buildOutputFilePath($executionPath, $tmpIdentifier);

        if (!file_exists($outputFile)) {
            touch($outputFile);
        }

        $shellCommand = sprintf(
            'cd %s && (%s) > %s 2>&1 & echo $!',
            escapeshellarg($executionPath),
            $command,
            escapeshellarg($outputFile)
        );

        $output = [];
        $resultCode = 0;
        exec($shellCommand, $output, $resultCode);

        if ($resultCode !== 0) {
            throw new DockerComposeManagerException(
                'Failed to start command asynchronously: ' . implode(PHP_EOL, $output)
            );
        }

        $pid = isset($output[0]) ? (int)$output[0] : null;

        $this->pids[] = $pid;
        $this->outputFiles[] = $outputFile;

        return [
            'pid' => $pid,
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

    private function buildOutputFilePath(string $executionPath, ?string $tmpIdentifier): string
    {
        $identifier = $tmpIdentifier ?? uniqid();

        return rtrim($executionPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'docker-compose-output-tmp-' . $identifier . '.log';
    }
}
