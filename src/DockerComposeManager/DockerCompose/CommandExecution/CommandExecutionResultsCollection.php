<?php

namespace Orryv\DockerComposeManager\DockerCompose\CommandExecution;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collection of CommandExecutionResult objects.
 */
class CommandExecutionResultsCollection implements IteratorAggregate, Countable
{
    /** @var array<string, CommandExecutionResult> */
    private array $results = [];

    /**
     * Add a result to the collection.
     */
    public function add(CommandExecutionResult $result): void
    {
        $this->results[$result->getId()] = $result;
    }

    /**
     * Retrieve a result for the given identifier.
     */
    public function get(string $id): ?CommandExecutionResult
    {
        return $this->results[$id] ?? null;
    }

    /**
     * @return Traversable<int, CommandExecutionResult>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_values($this->results));
    }

    /**
     * Number of stored results.
     */
    public function count(): int
    {
        return count($this->results);
    }
}
