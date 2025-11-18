<?php 

namespace Orryv\DockerComposeManager\YamlParsers;

class SymfonyYamlAvailability
{
    public static function isAvailable(): bool
    {
        return class_exists('\Symfony\Component\Yaml\Yaml');
    }
}