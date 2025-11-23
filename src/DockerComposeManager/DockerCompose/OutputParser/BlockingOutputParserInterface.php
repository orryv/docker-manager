<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;


use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultsCollectionInterface;

/**
 * Parses docker-compose command output and extracts relevant execution details.
 */
interface BlockingOutputParserInterface
{
    public function parse(
        CommandExecutionResultsCollection $executionResults,
        int $uSleep = 250000,
        ?callable $onProgressCallback = null
    ): OutputParserResultsCollectionInterface;
}
