<?php

namespace Tests\Unit\YamlParsers;

use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\YamlParsers\SymfonyYamlAvailability;
use Orryv\DockerComposeManager\YamlParsers\SymfonyYamlParser;
use PHPUnit\Framework\TestCase;

class SymfonyYamlParserTest extends TestCase
{
    public function testParseAndBuildFollowAvailability(): void
    {
        $parser = new SymfonyYamlParser();

        if (SymfonyYamlAvailability::isAvailable()) {
            $yaml = "services:\n  app:\n    container_name: app";
            $parsed = $parser->parse($yaml);
            $this->assertSame('app', $parsed['services']['app']['container_name']);

            $built = $parser->build($parsed);
            $this->assertIsString($built);
        } else {
            $this->expectException(YamlParserException::class);
            $parser->parse('services: {}');
        }
    }
}
