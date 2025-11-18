<?php 

namespace Orryv\DockerComposeManager\FileSystem;

use Orryv\DockerComposeManager\Exceptions\FileException;

class Reader
{
    public static function readFile(string $file_path): string
    {
        if (!is_file($file_path)) {
            throw new FileException("File not found: {$file_path}");
        }

        if (!is_readable($file_path)) {
            throw new FileException("File not readable: {$file_path}");
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            throw new FileException("Failed to read file: {$file_path}");
        }

        return $content;
    }
}