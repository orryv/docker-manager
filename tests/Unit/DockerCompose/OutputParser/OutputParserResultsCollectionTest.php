<?php

namespace Tests\Unit\DockerCompose\OutputParser;

use InvalidArgumentException;
use Orryv\DockerComposeManager\DockerCompose\Health\ContainerHealthCheckerInterface;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResult;
use Orryv\DockerComposeManager\DockerCompose\OutputParser\OutputParserResultsCollection;
use PHPUnit\Framework\TestCase;

class OutputParserResultsCollectionTest extends TestCase
{
    public function testAddAndRetrieveResults(): void
    {
        $collection = new OutputParserResultsCollection();
        $result = new OutputParserResult('one', [], [], [], [], [], null, true);

        $collection->add($result);

        $this->assertTrue($collection->has('one'));
        $this->assertSame($result, $collection->get('one'));
        $this->assertTrue($collection->areContainersRunning());
        $this->assertTrue($collection->isSuccessful());
        $this->assertFalse($collection->hasErrors());
        $this->assertSame([], $collection->getErrors());
        $this->assertFalse($collection->isContainerSuccessful('one', 'unknown'));
        $this->assertNull($collection->getContainerState('one', 'unknown'));
        $this->assertSame([], $collection->getContainerStates('one'));
        $this->assertSame([], $collection->getContainerSuccess('one'));
        $this->assertFalse($collection->isNetworkSuccessful('one', 'net'));
        $this->assertNull($collection->getNetworkState('one', 'net'));
        $this->assertSame([], $collection->getNetworkStates('one'));
        $this->assertSame([], $collection->getNetworkSuccess('one'));
        $this->assertCount(1, $collection);
        $this->assertSame(['one' => $result], iterator_to_array($collection));
    }

    public function testGetThrowsWhenMissing(): void
    {
        $collection = new OutputParserResultsCollection();

        $this->expectException(InvalidArgumentException::class);
        $collection->get('missing');
    }

    public function testAggregatedChecksRespectIndividualResults(): void
    {
        $collection = new OutputParserResultsCollection();
        $resultOne = new OutputParserResult('one', [], [], [], [], ['error'], null, false);
        $resultTwo = new OutputParserResult('two', [], [], [], [], [], null, true);

        $collection->add($resultOne);
        $collection->add($resultTwo);

        $this->assertFalse($collection->areContainersRunning());
        $this->assertFalse($collection->isSuccessful());
        $this->assertTrue($collection->hasErrors());
        $this->assertSame(['error'], $collection->getErrors('one'));
        $this->assertTrue($collection->hasErrors('one'));
        $this->assertFalse($collection->hasErrors('two'));
    }

    public function testAreHealthyUsesInjectedHealthChecker(): void
    {
        $healthChecker = new class implements ContainerHealthCheckerInterface {
            public array $areHealthyCalls = [];

            public function areHealthy(array $containerNames): bool
            {
                $this->areHealthyCalls[] = $containerNames;

                return count($containerNames) === 1;
            }

            public function waitUntilHealthy(array $containerNames, int $pollIntervalMicroSeconds = 250000): bool
            {
                return true;
            }
        };

        $collection = new OutputParserResultsCollection($healthChecker);
        $collection->add(new OutputParserResult('one', ['app' => 'running'], [], [], [], [], null, true));
        $collection->add(new OutputParserResult('two', ['db' => 'running'], [], [], [], [], null, true));

        $this->assertTrue($collection->areHealthy('one'));
        $this->assertFalse($collection->areHealthy());

        $this->assertSame([
            ['app'],
            ['app', 'db'],
        ], $healthChecker->areHealthyCalls);
    }

    public function testAreHealthyIsFalseWhenNoContainersPresent(): void
    {
        $healthChecker = new class implements ContainerHealthCheckerInterface {
            public array $areHealthyCalls = [];

            public function areHealthy(array $containerNames): bool
            {
                $this->areHealthyCalls[] = $containerNames;

                return true;
            }

            public function waitUntilHealthy(array $containerNames, int $pollIntervalMicroSeconds = 250000): bool
            {
                return true;
            }
        };

        $collection = new OutputParserResultsCollection($healthChecker);

        $this->assertFalse($collection->areHealthy());
        $this->assertSame([], $healthChecker->areHealthyCalls);
    }
}
