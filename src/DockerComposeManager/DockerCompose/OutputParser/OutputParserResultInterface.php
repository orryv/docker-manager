<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;

/**
 * Represents the parsed output of a docker-compose command execution.
 */
interface OutputParserResultInterface
{
    /**
     * Get the identifier for the docker-compose definition.
     */
    public function getId(): string;

    /**
     * Determine whether container startup reached a terminal state (running or ended due to an error).
     */
    public function areContainersRunning(): bool;

    /**
     * Get the state of a container as reported by docker-compose.
     */
    public function getContainerState(string $containerName): ?string;

    /**
     * Get all container states keyed by container name.
     *
     * @return array<string, string>
     */
    public function getContainerStates(): array;

    /**
     * Check whether a container reached a successful state.
     */
    public function isContainerSuccessful(string $containerName): bool;

    /**
     * Get success flags for all containers keyed by container name.
     *
     * @return array<string, bool>
     */
    public function getContainerSuccess(): array;

    /**
     * Check whether a network reached a successful state.
     */
    public function isNetworkSuccessful(string $networkName): bool;

    /**
     * Get success flags for all networks keyed by network name.
     *
     * @return array<string, bool>
     */
    public function getNetworkSuccess(): array;

    /**
     * Determine whether the overall execution succeeded.
     */
    public function isSuccessful(): bool;

    /**
     * Get the state of a network as reported by docker-compose.
     */
    public function getNetworkState(string $networkName): ?string;

    /**
     * Get all network states keyed by network name.
     *
     * @return array<string, string>
     */
    public function getNetworkStates(): array;

    /**
     * Retrieve the last build line printed by docker-compose (useful for progress).
     */
    public function getBuildLastLine(): ?string;

    /**
     * Get all collected errors.
     *
     * @return string[]
     */
    public function getErrors(): array;

    /**
     * Determine whether any errors were collected.
     */
    public function hasErrors(): bool;
}
