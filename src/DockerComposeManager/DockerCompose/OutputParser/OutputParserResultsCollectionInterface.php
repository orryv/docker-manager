<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;

use Countable;
use IteratorAggregate;

/**
 * Iterable collection of OutputParserResultInterface items.
 */
interface OutputParserResultsCollectionInterface extends IteratorAggregate, Countable
{
    /**
     * Add a parsed result to the collection.
     */
    public function add(OutputParserResultInterface $result): void;

    /**
     * Retrieve a parsed result for a specific docker-compose definition.
     */
    public function get(string $id): OutputParserResultInterface;

    /**
     * Whether the collection contains results for the given id.
     */
    public function has(string $id): bool;

    /**
     * Determine whether container startup reached a terminal state. When no id is provided, all results must be in a terminal state.
     */
    public function areContainersRunning(?string $id = null): bool;

    /**
     * Determine whether containers are healthy. When no id is provided, all containers across results must be healthy.
     */
    public function areHealthy(?string $id = null): bool;

    /**
     * Determine whether the execution(s) were successful. When no id is provided, all results must be successful.
     */
    public function isSuccessful(?string $id = null): bool;

    /**
     * Retrieve the last build line for a specific result.
     */
    public function getBuildLastLine(string $id): ?string;

    /**
     * Retrieve errors from either a specific result or all results.
     *
     * @return string[]
     */
    public function getErrors(?string $id = null): array;

    /**
     * Determine whether any errors exist for a specific result or within the collection.
     */
    public function hasErrors(?string $id = null): bool;

    /**
     * Check whether a container in a given result has succeeded.
     */
    public function isContainerSuccessful(string $id, string $containerName): bool;

    /**
     * Retrieve the state of a specific container for the given result.
     */
    public function getContainerState(string $id, string $containerName): ?string;

    /**
     * Retrieve all container states for the given result.
     *
     * @return array<string, string>
     */
    public function getContainerStates(string $id): array;

    /**
     * Retrieve all container success flags for the given result.
     *
     * @return array<string, bool>
     */
    public function getContainerSuccess(string $id): array;

    /**
     * Check whether a network in a given result has succeeded.
     */
    public function isNetworkSuccessful(string $id, string $networkName): bool;

    /**
     * Retrieve the state of a specific network for the given result.
     */
    public function getNetworkState(string $id, string $networkName): ?string;

    /**
     * Retrieve all network states for the given result.
     *
     * @return array<string, string>
     */
    public function getNetworkStates(string $id): array;

    /**
     * Retrieve all network success flags for the given result.
     *
     * @return array<string, bool>
     */
    public function getNetworkSuccess(string $id): array;
}
