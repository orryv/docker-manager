<?php

namespace Tests\Unit\YamlParsers;

use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\YamlParsers\SymfonyYamlAvailability;
use Orryv\DockerComposeManager\YamlParsers\SymfonyYamlParser;
use Orryv\DockerComposeManager\YamlParsers\YamlExtAvailability;
use Orryv\DockerComposeManager\YamlParsers\YamlExtParser;
use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\YamlParserFactory;
use PHPUnit\Framework\TestCase;

class YamlParserFactoryTest extends TestCase
{
    public function testCreateThrowsForUnknownMode(): void
    {
        $factory = new YamlParserFactory();

        $this->expectException(YamlParserException::class);
        $factory->create('invalid');
    }

    public function testGetYamlParserThrowsWhenNoneAvailable(): void
    {
        $this->expectException(YamlParserException::class);
        YamlParserFactory::getYamlParser(['unknown']);
    }

    public function testGetYamlExtensionParserHonorsAvailability(): void
    {
        if (YamlExtAvailability::isAvailable()) {
            $this->assertInstanceOf(YamlExtParser::class, YamlParserFactory::getYamlExtensionParser());
        } else {
            $this->expectException(YamlParserException::class);
            YamlParserFactory::getYamlExtensionParser();
        }
    }

    public function testGetSymfonyYamlParserHonorsAvailability(): void
    {
        if (SymfonyYamlAvailability::isAvailable()) {
            $this->assertInstanceOf(SymfonyYamlParser::class, YamlParserFactory::getSymfonyYamlParser());
        } else {
            $this->expectException(YamlParserException::class);
            YamlParserFactory::getSymfonyYamlParser();
        }
    }
}
