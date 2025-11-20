<?php 

namespace Orryv\DockerComposeManager\YamlParsers;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\YamlParsers\SymfonyYamlAvailability;

class SymfonyYamlParser implements YamlParserInterface
{
    public function parse(string $yaml_content): array
    {
        if (!SymfonyYamlAvailability::isAvailable()) {
            throw new YamlParserException("Symfony YAML parser is not available.");
        }

        /** @disregard P1009 Symfony\\Component\\Yaml\\Exception\\ParseException is an optional dependency */
        try {
            /** @disregard P1009 Symfony\\Component\\Yaml\\Yaml is an optional dependency */
            $parsed = Yaml::parse($yaml_content);
        } catch (ParseException $e) {
            throw new YamlParserException("Failed to parse YAML content using Symfony YAML parser: " . $e->getMessage());
        }

        return $parsed;
    }

    public function build(array $data): string
    {
        if (!SymfonyYamlAvailability::isAvailable()) {
            throw new YamlParserException("Symfony YAML parser is not available.");
        }

        /** @disregard P1009 Symfony\\Component\\Yaml\\Exception\\ParseException is an optional dependency */
        try {
            /** @disregard P1009 Symfony\\Component\\Yaml\\Yaml is an optional dependency */
            $yaml_content = Yaml::dump($data, 4, 2);
        } catch (ParseException $e) {
            throw new YamlParserException("Failed to build YAML content using Symfony YAML parser: " . $e->getMessage());
        }

        return $yaml_content;
    }
}