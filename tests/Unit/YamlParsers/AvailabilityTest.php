<?php

namespace Tests\Unit\YamlParsers;

use Orryv\DockerComposeManager\YamlParsers\SymfonyYamlAvailability;
use Orryv\DockerComposeManager\YamlParsers\YamlExtAvailability;
use PHPUnit\Framework\TestCase;

class AvailabilityTest extends TestCase
{
    public function testAvailabilityChecksReturnBoolean(): void
    {
        $this->assertIsBool(SymfonyYamlAvailability::isAvailable());
        $this->assertIsBool(YamlExtAvailability::isAvailable());
    }
}
