<?php

namespace Orryv\DockerManager\Parser;

use Orryv\XString;
use Orryv\XStringType;

class DockerOutputParser
{
    public function parse(string $output): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        $builds = [
            'containers' => [],
            'networks' => [],
            'build_status' => '',
            'errors' => [],
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $xline = XString::new($line);

            if ($xline->trim()->startsWith('Network ')) {
                $name = (string) $xline->between(' ', ' ')->trim();
                $status = (string) $xline->trim()->after($name, true)->trim();
                $builds['networks'][$name] = $status;
                continue;
            }

            if ($xline->trim()->startsWith('Container ')) {
                $name = (string) $xline->between(' ', ' ')->trim();
                $status = (string) $xline->trim()->after($name, true)->trim();
                $builds['containers'][$name] = $status;
                continue;
            }

            if ($xline->trim()->startsWith(XStringType::regex('/^#[0-9]+[ ]/'))) {
                $builds['build_status'] = (string) $xline->trim();
                continue;
            }

            if ($xline->trim()->startsWith('Error ')) {
                $builds['errors'][] = (string) $xline->trim();
            }
        }

        return $builds;
    }
}
