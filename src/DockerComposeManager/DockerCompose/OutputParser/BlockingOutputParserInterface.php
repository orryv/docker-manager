<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;


use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;

/**
 * Parses docker-compose command output and extracts relevant execution details.
 */
interface BlockingOutputParserInterface
{
    public function parse(CommandExecutionResultsCollection $executionResults, $uSleep = 250000, ?callable $onProgressCallback = null): bool;
}
