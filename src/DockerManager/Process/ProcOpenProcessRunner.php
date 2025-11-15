<?php

namespace Orryv\DockerManager\Process;

use RuntimeException;

class ProcOpenProcessRunner implements ProcessRunnerInterface
{
    public function run(ProcessContext $context, callable $tick): ProcessResult
    {
        $logDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0755, true);
        }

        $logFile = $logDirectory . 'docker-compose-' . time() . '-' . uniqid('', true) . '.log';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ];

        $options = $context->isWindows()
            ? ['suppress_errors' => true]
            : [];

        $process = proc_open(
            $context->getCommand(),
            $descriptors,
            $pipes,
            $context->getWorkingDirectory(),
            $context->getEnvironment(),
            $options
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start compose process.');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $timedOut = true;
        $iterations = $context->getMaxIterations();
        for ($i = 0; $i < $iterations; $i++) {
            clearstatcache(false, $logFile);
            if (is_file($logFile)) {
                $logContent = (string) file_get_contents($logFile);
                $tick($logContent);
                
                // On Windows, check for early fatal errors that should fail fast
                if ($context->isWindows() && $i < 10) {
                    $this->checkForWindowsFatalErrors($logContent, $context);
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                $timedOut = false;
                break;
            }

            usleep($context->getPollIntervalMicros());
        }

        $status = proc_get_status($process);
        if ($status['running']) {
            proc_terminate($process);
        }

        if (is_file($logFile)) {
            $tick((string) file_get_contents($logFile));
        }

        $exitCode = proc_close($process);
        if ($exitCode === -1) {
            $exitCode = 1;
        }

        if (!$context->shouldKeepLogFile() && is_file($logFile)) {
            register_shutdown_function(static function () use ($logFile): void {
                @unlink($logFile);
            });
        }

        return new ProcessResult($exitCode, $logFile, $timedOut);
    }

    /**
     * Check for Windows-specific fatal errors that should fail fast.
     * Throws an exception with detailed context when critical errors are detected.
     */
    private function checkForWindowsFatalErrors(string $logContent, ProcessContext $context): void
    {
        // Check for "The system cannot find the path specified." error
        if (stripos($logContent, 'The system cannot find the path specified') !== false) {
            $errorMessage = sprintf(
                "Windows error: 'The system cannot find the path specified.'\n" .
                "This typically means:\n" .
                "  1. The working directory doesn't exist: %s\n" .
                "  2. A path in the command is invalid\n" .
                "  3. An executable in the command cannot be found\n\n" .
                "Command: %s\n" .
                "Working directory: %s\n\n" .
                "Please verify:\n" .
                "  - All paths in your docker-compose.yml exist\n" .
                "  - The working directory exists\n" .
                "  - Docker and docker-compose are in your PATH",
                $context->getWorkingDirectory(),
                $context->getCommand(),
                $context->getWorkingDirectory()
            );
            
            throw new RuntimeException($errorMessage);
        }
        
        // Check for other common Windows fatal errors
        if (stripos($logContent, 'The filename, directory name, or volume label syntax is incorrect') !== false) {
            throw new RuntimeException(sprintf(
                "Windows error: Invalid path syntax detected.\n" .
                "Command: %s\n" .
                "Working directory: %s\n" .
                "Please check that all paths use valid Windows syntax.",
                $context->getCommand(),
                $context->getWorkingDirectory()
            ));
        }
    }
}
