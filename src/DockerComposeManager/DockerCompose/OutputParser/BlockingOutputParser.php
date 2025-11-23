<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\BlockingOutputParserInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultsCollectionInterface;

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
    public function parse(
        CommandExecutionResultsCollection $executionResults,
        int $uSleep = 250000,
        ?callable $onProgressCallback = null
    ): OutputParserResultsCollectionInterface
    {
        $latestParseResults = new OutputParserResultsCollection();

        do {
            $allFinished = true;
            foreach ($executionResults as $result) {
                $parseResult = $this->outputParser->parse($result);
                $latestParseResults->add($parseResult);

                if ($onProgressCallback !== null) {
                    $onProgressCallback($parseResult);
                }

                if (!$parseResult->isFinishedExecuting()) {
                    $allFinished = false;
                }
            }

            if (!$allFinished) {
                usleep($uSleep); // wait before re-checking
            }
        } while (!$allFinished);

        return $latestParseResults;
    }
}