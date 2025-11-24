<?php

namespace Tests\Unit\DockerCompose\OutputParser;

use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResult;
use Orryv\DockerComposeManager\DockerCompose\CommandExecution\CommandExecutionResultsCollection;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\BlockingOutputParser;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResult;
use PHPUnit\Framework\TestCase;

class BlockingOutputParserTest extends TestCase
{
    public function testParseWaitsUntilFinishedAndInvokesCallback(): void
    {
        $executionResults = new CommandExecutionResultsCollection();
        $executionResults->add(new CommandExecutionResult('one', null, '/tmp/one.log'));

        $parser = new class implements OutputParserInterface {
            private int $calls = 0;

            public function parse(CommandExecutionResult $executionResult): OutputParserResult
            {
                $this->calls++;
                $finished = $this->calls >= 2;
                return new OutputParserResult($executionResult->getId(), [], [], [], [], [], null, $finished);
            }
        };

        $blocking = new BlockingOutputParser($parser);
        $callbackInvoked = 0;

        $results = $blocking->parse(
            $executionResults,
            10,
            function () use (&$callbackInvoked) {
                $callbackInvoked++;
            }
        );

        $this->assertTrue($results->areContainersRunning());
        $this->assertSame(2, $callbackInvoked);
    }
}
