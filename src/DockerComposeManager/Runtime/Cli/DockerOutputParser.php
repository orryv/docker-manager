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
        $rawLines = preg_split('/\r?\n/', $content);
        if (!is_array($rawLines)) {
            $rawLines = [];
        }

        $containers = [];
        $networks = [];
        $buildStatus = '';
        $errors = [];
        $normalizedLines = [];

        foreach ($rawLines as $rawLine) {
            $line = trim((string) $rawLine);
            if ($line === '') {
                continue;
            }

            $normalizedLines[] = $line;

            if (stripos($line, 'Network ') === 0 && preg_match('/^Network\s+(?P<name>[^\s]+)\s+(?P<status>.+)$/i', $line, $matches)) {
                $networks[$matches['name']] = trim($matches['status']);
                continue;
            }

            if (stripos($line, 'Container ') === 0 && preg_match('/^Container\s+(?P<name>[^\s]+)\s+(?P<status>.+)$/i', $line, $matches)) {
                $containers[$matches['name']] = trim($matches['status']);
                continue;
            }

            if (preg_match('/^#[0-9]+\s+/', $line)) {
                $buildStatus = $line;
                continue;
            }

            if (stripos($line, 'Error ') === 0 || stripos($line, 'Error:') === 0) {
                $errors[] = $line;
            }
        }

        return [
            'containers' => $containers,
            'networks' => $networks,
            'build_status' => $buildStatus,
            'errors' => $errors,
            'lines' => $normalizedLines,
        ];
    }
}
