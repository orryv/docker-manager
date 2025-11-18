<?php 

namespace Orryv\DockerComposeManager\YamlParsers;

use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\YamlParsers\YamlExtAvailability;

class YamlExtParser implements YamlParserInterface
{
    public function parse(string $yaml_content): array
    {
        if (!YamlExtAvailability::isAvailable()) {
            throw new YamlParserException("YAML extension is not available.");
        }

        /** @disregard P1010 yaml_emit_file comes from optional ext-yaml */
        $parsed = yaml_parse($yaml_content);
        if ($parsed === false) {
            throw new YamlParserException("Failed to parse YAML content using ext-yaml.");
        }

        return $parsed;
    }
}