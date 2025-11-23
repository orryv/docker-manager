<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;

/**
 * Parses docker-compose command output and extracts relevant execution details.
 */
interface BlockingOutputParserInterface
{
    public function parse(\Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection $executionResults, $uSleep = 250000, ?callable $onProgressCallback = null): bool;
}
