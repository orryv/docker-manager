<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResult;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultInterface;
use Orryv\XString;

/**
 * Default implementation for parsing docker-compose execution output.
 */
class OutputParser implements OutputParserInterface
{
    /** @var string[] */
    private array $success = [
        'recreated',
        'started',
        'running'
    ];

    /** @var string[] */
    private array $networkSuccess = [
        'created',
        'recreated',
        'exists',
        'up-to-date'
    ];

    /**
     * Parse docker-compose output for a single execution.
     */
    public function parse(CommandExecutionResult $executionResult): OutputParserResultInterface
    {
        $outputFile = $executionResult->getOutputFile();

        if (!file_exists($outputFile)) {
            throw new \InvalidArgumentException("Output file does not exist: {$outputFile}");
        }

        $outputContent = file_get_contents($outputFile);

        $parsed = [
            'states' => [
                'networks' => [],
                'containers' => [],
            ],
            'success' => [
                'networks' => [],
                'containers' => [],
            ],
            'build_last_line' => null,
            'errors' => [],
            'containers_running' => false,
        ];

        foreach(explode("\n", $outputContent) as $pos => $line) {
            $line = XString::trim($line);

            if($line->startsWith('Network ')) {
                $name = $line->between(' ', ' ')->trim()->toString();
                $status = $line->after($name, true)->trim()->toString();
                $parsed['states']['networks'][$name] = $status;
                $parsed['success']['networks'][$name] = in_array(strtolower($status), $this->networkSuccess);
            } elseif($line->startsWith('Container ')) {
                $name = $line->between(' ', ' ')->trim()->toString();
                $status = $line->after($name, true)->trim();
                if(in_array($status->tolower()->toString(), $this->success)) {
                    $parsed['success']['containers'][$name] = true;
                } else {
                    $parsed['success']['containers'][$name] = false;
                }
                $parsed['states']['containers'][$name] = $status->toString();
            } elseif(preg_match('/^#[0-9]+[ ]/', $line)) {
                $parsed['build_last_line'] = $line->toString();
            } elseif($line->startsWith('Error ')) {
                $parsed['errors'][] = $line->toString();
                $parsed['containers_running'] = true;
            }
        }

        $allContainersRunning = true;
        foreach($parsed['states']['containers'] as $container_name => $status) {
            if(!in_array(strtolower($status), $this->success)) {
                $allContainersRunning = false;
                break;
            }
        }

        if($allContainersRunning && count($parsed['states']['containers']) > 0) {
            $parsed['containers_running'] = true;
        }

        return new OutputParserResult(
            $executionResult->getId(),
            $parsed['states']['containers'],
            $parsed['success']['containers'],
            $parsed['states']['networks'],
            $parsed['success']['networks'],
            $parsed['errors'],
            $parsed['build_last_line'],
            $parsed['containers_running']
        );
    }
}
