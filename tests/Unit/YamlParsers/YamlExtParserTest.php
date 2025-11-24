<?php

namespace Tests\Unit\YamlParsers;

use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\YamlParsers\YamlExtAvailability;
use Orryv\DockerComposeManager\YamlParsers\YamlExtParser;
use PHPUnit\Framework\TestCase;

class YamlExtParserTest extends TestCase
{
    public function testParseAndBuildFollowAvailability(): void
    {
        $parser = new YamlExtParser();

        if (YamlExtAvailability::isAvailable()) {
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
