<?php

namespace Orryv\DockerComposeManager\DockerCompose\Health;

/**
 * Default docker container health checker using `docker inspect`.
 */
class ContainerHealthChecker implements ContainerHealthCheckerInterface
{
    /**
     * @inheritDoc
     */
    public function waitUntilHealthy(array $containerNames, int $pollIntervalMicroSeconds = 250000): bool
    {
        $containers = array_values(array_unique($containerNames));

        if (empty($containers)) {
            return true;
        }

        while (true) {
            $hasHealthChecks = false;
            $allHealthy = true;

            foreach ($containers as $container) {
                $status = $this->fetchHealthStatus($container);

                if (!$status['inspectable']) {
                    return false;
                }

                if (!$status['hasHealthCheck']) {
                    continue;
                }

                $hasHealthChecks = true;

                if ($status['status'] === 'unhealthy') {
                    return false;
                }

                if ($status['status'] !== 'healthy') {
                    $allHealthy = false;
                }
            }

            if (!$hasHealthChecks) {
                return true;
            }

            if ($allHealthy) {
                return true;
            }

            usleep($pollIntervalMicroSeconds);
        }
    }

    /**
     * Read health status via docker inspect.
     *
     * @return array{inspectable: bool, hasHealthCheck: bool, status: ?string}
     */
    protected function fetchHealthStatus(string $containerName): array
    {
        $command = sprintf(
            'docker inspect --format=%s %s',
            escapeshellarg('{{.State.Health.Status}}'),
            escapeshellarg($containerName)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !isset($output[0])) {
            return [
                'inspectable' => false,
                'hasHealthCheck' => false,
                'status' => null,
            ];
        }

        $status = strtolower(trim($output[0], " \t\n\r\""));

        if ($status === '' || $status === '<nil>' || $status === '<no value>') {
            return [
                'inspectable' => true,
                'hasHealthCheck' => false,
                'status' => null,
            ];
        }

        return [
            'inspectable' => true,
            'hasHealthCheck' => true,
            'status' => $status,
        ];
    }
}
