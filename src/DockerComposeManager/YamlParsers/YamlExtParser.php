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

    public function build(array $data): string
    {
        if (!YamlExtAvailability::isAvailable()) {
            throw new YamlParserException("YAML extension is not available.");
        }

        /** @disregard P1010 yaml_emit_file comes from optional ext-yaml */
        $yaml_content = yaml_emit($data);
        if ($yaml_content === false) {
            throw new YamlParserException("Failed to build YAML content using ext-yaml.");
        }

        return $yaml_content;
    }
}