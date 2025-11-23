<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\BlockingOutputParserInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserInterface;

/**
 * Default implementation for parsing docker-compose execution output.
 */
class BlockingOutputParser implements BlockingOutputParserInterface
{
    private OutputParserInterface $outputParser;

    public function __construct(OutputParserInterface $outputParser) 
    {
        $this->outputParser = $outputParser;
    }

    /**
     * Parse execution results until all scripts have ended.
     */
    public function parse(CommandExecutionResultsCollection $executionResults, $uSleep = 250000, ?callable $onProgressCallback = null): bool
    {
        $latestParseData = [];
        do{
            $scriptExecutionEnded = true;
            foreach($executionResults as $result) {
                $parseData = $this->outputParser->parse($result);
                $latestParseData[$result->getId()] = $parseData;

                if ($onProgressCallback !== null) {
                    $onProgressCallback($parseData);
                }

                if(!$parseData['script_ended']) {
                    $scriptExecutionEnded = false;
                    usleep($uSleep); // wait 0.25s before re-checking
                }
            }
        } while (!$scriptExecutionEnded);

        // check if successful
        $allSuccessful = true;
        foreach($latestParseData as $parseData) {
            foreach($parseData['success']['containers'] as $result) {
                if(!$result) {
                    $allSuccessful = false;
                    break 2;
                }
            }
        }

        return $allSuccessful;
    }
}