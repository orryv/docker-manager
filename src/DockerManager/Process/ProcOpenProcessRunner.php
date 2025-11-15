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
                $tick((string) file_get_contents($logFile));
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
}
