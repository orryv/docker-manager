<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;

/**
 * Parses docker-compose command output and extracts relevant execution details.
 */
interface OutputParserInterface
{
    /**
     * Parse a docker-compose output log file for a specific definition.
     */
    public function parse(CommandExecutionResult $executionResult): array;
}
