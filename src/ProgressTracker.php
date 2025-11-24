<?php

namespace Orryv;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\Health\ContainerHealthChecker;
use Orryv\DockerComposeManager\DockerCompose\Health\ContainerHealthCheckerInterface;
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

    private BlockingOutputParserInterface $blockingOutputParser;

    /**
     * @param OutputParserInterface|null $outputParser Parser used for non-blocking progress reads.
     * @param BlockingOutputParserInterface|null $blockingOutputParser Parser used for blocking progress reads.
     * @param ContainerHealthCheckerInterface|null $healthChecker Inspector used for health checks after startup.
     */
    public function __construct(
        private OutputParserInterface $outputParser = new OutputParser(),
        ?BlockingOutputParserInterface $blockingOutputParser = null,
        private ContainerHealthCheckerInterface $healthChecker = new ContainerHealthChecker(),
    ) {
        $this->blockingOutputParser = $blockingOutputParser ?? new BlockingOutputParser($this->outputParser, $this->healthChecker);
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
     *
     * @param int $stateCheckIntervalUs Microseconds to wait between state checks.
     */
    public function parseBlocking(
        CommandExecutionResultsCollection $results,
        ?callable $onProgress = null,
        int $stateCheckIntervalUs = 250000
    ): OutputParserResultsCollectionInterface {
        return $this->blockingOutputParser->parse(
            $results,
            $stateCheckIntervalUs,
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
        $results = new OutputParserResultsCollection($this->healthChecker);

        foreach ($executionResults as $executionResult) {
            $results->add($this->outputParser->parse($executionResult));
        }

        return $results;
    }

    /**
     * Check if all tracked executions have running containers.
     *
     * @param array<int, CommandExecutionResult> $executionResults
     */
    public function isFinished(array $executionResults): bool
    {
        return $this->getProgress($executionResults)->areContainersRunning();
    }

    /**
     * Wait until all containers with health checks report healthy.
     *
     * @param int $stateCheckIntervalUs Microseconds to wait between health status checks.
     */
    public function waitForHealthyContainers(
        OutputParserResultsCollectionInterface $parseResults,
        int $stateCheckIntervalUs
    ): bool {
        $containers = [];

        foreach ($parseResults as $parseResult) {
            $containers = array_merge($containers, array_keys($parseResult->getContainerStates()));
        }

        return $this->healthChecker->waitUntilHealthy(array_unique($containers), $stateCheckIntervalUs);
    }
}
