<?php

namespace Orryv;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\BlockingOutputParser;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\BlockingOutputParserInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParser;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultsCollectionInterface;

/**
 * Coordinates parsing and progress reporting for docker-compose executions.
 */
class ProgressTracker
{
    /** @var callable|null */
    private $onProgressCallback = null;

    /**
     * @param OutputParserInterface|null $outputParser Parser used for non-blocking progress reads.
     * @param BlockingOutputParserInterface|null $blockingOutputParser Parser used for blocking progress reads.
     */
    public function __construct(
        private OutputParserInterface $outputParser = new OutputParser(),
        private BlockingOutputParserInterface $blockingOutputParser = new BlockingOutputParser(new OutputParser()),
    ) {
    }

    /**
     * Register a callback to receive progress updates.
     */
    public function onProgress(callable $callback): void
    {
        $this->onProgressCallback = $callback;
    }

    /**
     * Parse execution results while blocking until all commands complete.
     */
    public function parseBlocking(
        CommandExecutionResultsCollection $results,
        ?callable $onProgress = null
    ): OutputParserResultsCollectionInterface {
        return $this->blockingOutputParser->parse(
            $results,
            250000,
            $onProgress ?? $this->onProgressCallback
        );
    }

    /**
     * Compute parsed progress snapshots for the provided execution results.
     *
     * @param array<int, CommandExecutionResult> $executionResults
     */
    public function getProgress(array $executionResults): OutputParserResultsCollectionInterface
    {
        $results = new OutputParserResultsCollection();

        foreach ($executionResults as $executionResult) {
            $results->add($this->outputParser->parse($executionResult));
        }

        return $results;
    }

    /**
     * Check if all tracked executions have completed.
     *
     * @param array<int, CommandExecutionResult> $executionResults
     */
    public function isFinished(array $executionResults): bool
    {
        return $this->getProgress($executionResults)->isFinishedExecuting();
    }
}
