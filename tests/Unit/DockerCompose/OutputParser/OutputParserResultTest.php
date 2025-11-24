<?php

namespace Tests\Unit\DockerCompose\OutputParser;

use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResult;
use PHPUnit\Framework\TestCase;

class OutputParserResultTest extends TestCase
{
    public function testAccessorsAndSuccessEvaluation(): void
    {
        $result = new OutputParserResult(
            'id-1',
            ['container' => 'running'],
            ['container' => true],
            ['network' => 'created'],
            ['network' => true],
            [],
            'build-line',
            true
        );

        $this->assertSame('id-1', $result->getId());
        $this->assertTrue($result->areContainersRunning());
        $this->assertSame('running', $result->getContainerState('container'));
        $this->assertTrue($result->isContainerSuccessful('container'));
        $this->assertSame(['container' => 'running'], $result->getContainerStates());
        $this->assertSame(['container' => true], $result->getContainerSuccess());
        $this->assertTrue($result->isNetworkSuccessful('network'));
        $this->assertSame('created', $result->getNetworkState('network'));
        $this->assertSame(['network' => 'created'], $result->getNetworkStates());
        $this->assertSame(['network' => true], $result->getNetworkSuccess());
        $this->assertSame('build-line', $result->getBuildLastLine());
        $this->assertFalse($result->hasErrors());
        $this->assertTrue($result->isSuccessful());
    }

    public function testHasErrorsAffectsSuccess(): void
    {
        $result = new OutputParserResult(
            'id-1',
            [],
            [],
            [],
            [],
            ['failure'],
            null,
            false
        );

        $this->assertTrue($result->hasErrors());
        $this->assertFalse($result->isSuccessful());
    }
}
