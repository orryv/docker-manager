<?php

namespace Orryv\DockerComposeManager\Runtime\Cli;

/**
 * Small utility that turns raw docker compose output into structured events and
 * extracts error strings for easier reporting.
 */
class DockerOutputParser
{
    /**
     * Parse docker compose output and return aggregated progress data similar to
     * the legacy DockerManager implementation.
     *
     * @return array{
     *     containers:array<string,string>,
     *     networks:array<string,string>,
     *     build_status:string,
     *     errors:array<int,string>,
     *     lines:array<int,string>
     * }
     */
    public function parse(string $content): array
    {
        $trimmed = trim($content);
        $lines = $trimmed === '' ? [] : preg_split('/\r?\n/', $trimmed);
        if (!is_array($lines)) {
            $lines = [];
        }

        $containers = [];
        $networks = [];
        $buildStatus = '';
        $errors = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (stripos($line, 'error ') === 0 || stripos($line, 'error:') === 0) {
                $errors[] = $line;
                continue;
            }

            if (preg_match('/^Network\s+(?P<name>[^\s]+)\s+(?P<status>.+)$/i', $line, $matches)) {
                $networks[$matches['name']] = trim($matches['status']);
                continue;
            }

            if (preg_match('/^Container\s+(?P<name>[^\s]+)\s+(?P<status>.+)$/i', $line, $matches)) {
                $containers[$matches['name']] = trim($matches['status']);
                continue;
            }

            if ($buildStatus === '' && preg_match('/^#[0-9]+\s+/', $line)) {
                $buildStatus = $line;
            }
        }

        return [
            'containers' => $containers,
            'networks' => $networks,
            'build_status' => $buildStatus,
            'errors' => $errors,
            'lines' => $lines,
        ];
    }
}
