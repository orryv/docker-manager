<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;

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

    public function parse($executionResults, $uSleep = 250000, ?callable $onProgressCallback = null): bool
    {
        do{
            $scriptExecutionEnded = true;
            foreach($executionResults as $id => $result) {
                $outputFile = $result['output_file'];
                $parseData = $this->outputParser->parse($id, $outputFile);

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
        foreach($parseData['success']['containers'] as $id => $result) {
            if(!$result) {
                $allSuccessful = false;
                break;
            }
        }

        return $allSuccessful;
    }
}