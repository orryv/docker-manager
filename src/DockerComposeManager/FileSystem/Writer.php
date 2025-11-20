<?php 

namespace Orryv\DockerComposeManager\FileSystem;

use Orryv\DockerComposeManager\Exceptions\FileException;

class Writer
{
    public static function overwrite(string $file_path, string $content): bool
    {
        if(file_exists($file_path) && !is_writable($file_path)) {
            @unlink($file_path) && !is_writable($file_path);
        }

        return file_put_contents($file_path, $content) !== false;
    }

    public static function remove(string $file_path): bool
    {
        if (!is_file($file_path)) {
            throw new FileException("File not found: {$file_path}");
        }

        return @unlink($file_path);
    }
}