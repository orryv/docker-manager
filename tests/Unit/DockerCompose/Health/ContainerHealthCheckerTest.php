<?php

namespace Tests\Unit\DockerCompose\Health;

use Orryv\DockerComposeManager\DockerCompose\Health\ContainerHealthChecker;
use PHPUnit\Framework\TestCase;

class ContainerHealthCheckerTest extends TestCase
{
    public function testWaitUntilHealthyReturnsTrueWhenContainersBecomeHealthy(): void
    {
        $statuses = [
            'web' => [
                ['inspectable' => true, 'hasHealthCheck' => true, 'status' => 'starting'],
                ['inspectable' => true, 'hasHealthCheck' => true, 'status' => 'healthy'],
            ],
            'db' => [
                ['inspectable' => true, 'hasHealthCheck' => false, 'status' => null],
            ],
        ];

        $checker = $this->getMockBuilder(ContainerHealthChecker::class)
            ->onlyMethods(['fetchHealthStatus'])
            ->getMock();

        $checker->method('fetchHealthStatus')->willReturnCallback(function (string $container) use (&$statuses) {
            if (!array_key_exists($container, $statuses)) {
                return ['inspectable' => false, 'hasHealthCheck' => false, 'status' => null];
            }

            $status = array_shift($statuses[$container]);

            if ($status === null) {
                return ['inspectable' => true, 'hasHealthCheck' => false, 'status' => null];
            }

            return $status ?? ['inspectable' => true, 'hasHealthCheck' => false, 'status' => null];
        });

        $this->assertTrue($checker->waitUntilHealthy(['web', 'db'], 0));
    }

    public function testWaitUntilHealthyReturnsFalseWhenContainerUnhealthy(): void
    {
        $checker = $this->getMockBuilder(ContainerHealthChecker::class)
            ->onlyMethods(['fetchHealthStatus'])
            ->getMock();

        $checker->method('fetchHealthStatus')->willReturn([
            'inspectable' => true,
            'hasHealthCheck' => true,
            'status' => 'unhealthy',
        ]);

        $this->assertFalse($checker->waitUntilHealthy(['web'], 0));
    }

    public function testWaitUntilHealthyReturnsFalseWhenInspectFails(): void
    {
        $checker = $this->getMockBuilder(ContainerHealthChecker::class)
            ->onlyMethods(['fetchHealthStatus'])
            ->getMock();

        $checker->method('fetchHealthStatus')->willReturn([
            'inspectable' => false,
            'hasHealthCheck' => false,
            'status' => null,
        ]);

        $this->assertFalse($checker->waitUntilHealthy(['web'], 0));
    }
}
