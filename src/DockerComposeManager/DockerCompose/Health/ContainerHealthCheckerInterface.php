<?php

namespace Orryv\DockerComposeManager\DockerCompose\Health;

/**
 * Contract for checking docker container health status.
 */
interface ContainerHealthCheckerInterface
{
    /**
     * Wait until all containers that expose a healthcheck are healthy.
     *
     * Containers without a healthcheck are treated as immediately healthy.
     * Returns false if container inspection fails (e.g., missing Docker CLI or unreachable container).
     */
    public function waitUntilHealthy(array $containerNames, int $pollIntervalMicroSeconds = 250000): bool;
}
