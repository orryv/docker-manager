<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;

use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionInterface;
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

    public function parse(string $id, string $outputFile, DefinitionInterface $handler): array
    {
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
            'script_ended' => false,
        ];

        foreach(explode("\n", $outputContent) as $pos => $line) {
            $line = XString::trim($line);

            echo "Parsing line {$pos}: {$line}\n";

            if($line->startsWith('Network ')) {
                $name = $line->between(' ', ' ')->trim()->toString();
                $status = $line->after($name, true)->trim()->toString();
                $parsed['states']['networks'][$name] = $status;
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
                $parsed['script_ended'] = true;
            }
        }

        $all_containers_ended = true;
        foreach($parsed['states']['containers'] as $container_name => $status) {
            if(!in_array(strtolower($status), $this->success)) {
                $all_containers_ended = false;
                break;
            }
        }


        if($all_containers_ended && count($parsed['states']['containers']) > 0) {
            $parsed['script_ended'] = true;
        }

        return $parsed;
    }
}
