<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;

use ArrayIterator;
use InvalidArgumentException;
use Traversable;

/**
 * Collection of OutputParserResultInterface items.
 */
class OutputParserResultsCollection implements OutputParserResultsCollectionInterface
{
    /** @var array<string, OutputParserResultInterface> */
    private array $results = [];

    /**
     * Add or replace a parsed result keyed by id.
     */
    public function add(OutputParserResultInterface $result): void
    {
        $this->results[$result->getId()] = $result;
    }

    /**
     * Retrieve a parsed result for the provided id.
     */
    public function get(string $id): OutputParserResultInterface
    {
        if (!$this->has($id)) {
            throw new InvalidArgumentException("No parsed result available for id: {$id}");
        }

        return $this->results[$id];
    }

    /**
     * Determine whether the collection has a result for the given id.
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->results);
    }

    /**
     * Whether one or all executions finished.
     */
    public function isFinishedExecuting(?string $id = null): bool
    {
        if ($id !== null) {
            return $this->get($id)->isFinishedExecuting();
        }

        foreach ($this->results as $result) {
            if (!$result->isFinishedExecuting()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether one or all executions were successful.
     */
    public function isSuccessful(?string $id = null): bool
    {
        if ($id !== null) {
            return $this->get($id)->isSuccessful();
        }

        foreach ($this->results as $result) {
            if (!$result->isSuccessful()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Latest build output line for the given execution.
     */
    public function getBuildLastLine(string $id): ?string
    {
        return $this->get($id)->getBuildLastLine();
    }

    /**
     * Retrieve errors from a single execution or aggregated across all.
     */
    public function getErrors(?string $id = null): array
    {
        if ($id !== null) {
            return $this->get($id)->getErrors();
        }

        $errors = [];
        foreach ($this->results as $result) {
            $errors = array_merge($errors, $result->getErrors());
        }

        return $errors;
    }

    /**
     * Whether errors are present for a specific execution or any in the collection.
     */
    public function hasErrors(?string $id = null): bool
    {
        if ($id !== null) {
            return $this->get($id)->hasErrors();
        }

        foreach ($this->results as $result) {
            if ($result->hasErrors()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a container within the specified execution succeeded.
     */
    public function isContainerSuccessful(string $id, string $containerName): bool
    {
        return $this->get($id)->isContainerSuccessful($containerName);
    }

    /**
     * Retrieve the state for a container within the specified execution.
     */
    public function getContainerState(string $id, string $containerName): ?string
    {
        return $this->get($id)->getContainerState($containerName);
    }

    /**
     * Retrieve all container states for the specified execution.
     *
     * @return array<string, string>
     */
    public function getContainerStates(string $id): array
    {
        return $this->get($id)->getContainerStates();
    }

    /**
     * Retrieve all container success flags for the specified execution.
     *
     * @return array<string, bool>
     */
    public function getContainerSuccess(string $id): array
    {
        return $this->get($id)->getContainerSuccess();
    }

    /**
     * Whether a network within the specified execution succeeded.
     */
    public function isNetworkSuccessful(string $id, string $networkName): bool
    {
        return $this->get($id)->isNetworkSuccessful($networkName);
    }

    /**
     * Retrieve the state for a network within the specified execution.
     */
    public function getNetworkState(string $id, string $networkName): ?string
    {
        return $this->get($id)->getNetworkState($networkName);
    }

    /**
     * Retrieve all network states for the specified execution.
     *
     * @return array<string, string>
     */
    public function getNetworkStates(string $id): array
    {
        return $this->get($id)->getNetworkStates();
    }

    /**
     * Retrieve all network success flags for the specified execution.
     *
     * @return array<string, bool>
     */
    public function getNetworkSuccess(string $id): array
    {
        return $this->get($id)->getNetworkSuccess();
    }

    /**
     * @return Traversable<string, OutputParserResultInterface>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }

    /**
     * Count results held in the collection.
     */
    public function count(): int
    {
        return count($this->results);
    }
}
