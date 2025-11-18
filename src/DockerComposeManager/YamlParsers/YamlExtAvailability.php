<?php 

namespace Orryv\DockerComposeManager\YamlParsers;

class YamlExtAvailability
{
    public static function isAvailable(): bool
    {
        return extension_loaded('yaml');
    }
}