<?php 

namespace Orryv;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\DockerComposeManager\YamlParsers\YamlExtParser;
use Orryv\DockerComposeManager\YamlParsers\YamlExtAvailability;
use Orryv\DockerComposeManager\YamlParsers\SymfonyYamlParser;
use Orryv\DockerComposeManager\YamlParsers\SymfonyYamlAvailability;
use Orryv\DockerComposeManager\Exceptions\YamlParserException;

final class YamlParserFactory
{
    public function create(string $mode): YamlParserInterface
    {
        return match ($mode) {
            'ext-yaml'     => self::getYamlExtensionParser(),
            'symfony-yaml' => self::getSymfonyYamlParser(),
            'auto'         => self::getYamlParser(),
            default        => throw new YamlParserException("Unknown YAML parser: {$mode}"),
        };
    }

    public static function getYamlParser(array $order = ['ext-yaml', 'symfony-yaml']): YamlParserInterface
    {
        foreach ($order as $parser_name) {
            try {
                return (new self())->create($parser_name);
            } catch (YamlParserException) {
                // Try next
            }
        }

        throw new YamlParserException('No YAML parser available. Install a parser, or use fromYamlArray() instead of fromDockerComposeFile().');
    }

    public static function getYamlExtensionParser(): YamlExtParser
    {
        if (!YamlExtAvailability::isAvailable()) {
            throw new YamlParserException('YAML extension is not available.');
        }

        return new YamlExtParser();
    }

    public static function getSymfonyYamlParser(): SymfonyYamlParser
    {
        if (!SymfonyYamlAvailability::isAvailable()) {
            throw new YamlParserException('Symfony YAML parser is not available.');
        }

        return new SymfonyYamlParser();
    }
}