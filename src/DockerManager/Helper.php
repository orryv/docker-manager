<?php

namespace Orryv\DockerManager;

use Orryv\Cmd;
use Orryv\DockerManager;
use Orryv\DockerManager\Ports\FindNextPort;
use Orryv\XString;
use Orryv\XStringType;

class Helper
{
    public static function startContainer(
        string $name,
        string $workdir,
        string $compose_path,
        int|FindNextPort $port,
        bool $build_containers,
        bool $save_logs,
        array $vars = []
    ): int {
        Cmd::beginLive(1);

        $tmp_port = $port instanceof FindNextPort
            ? $port->getAvailablePort()
            : $port;

        $composeFullPath = rtrim($workdir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . ltrim($compose_path, DIRECTORY_SEPARATOR);

        $manager = (new DockerManager())
            ->fromDockerCompose($composeFullPath)
            ->setName($name)
            ->onProgress(static function (array $builds): void {
                $line = '  ';
                $inProgress = false;

                foreach ($builds['containers'] ?? [] as $container => $status) {
                    $line .= "{$container}: {$status} | ";
                    if ($status !== 'Started' && $status !== 'Running') {
                        $inProgress = true;
                    }
                }

                if (!empty($builds['containers']) && !$inProgress) {
                    Cmd::updateLive(0, '  Starting up container...');
                    return;
                }

                if (!empty($line)) {
                    $line .= '=> ';
                }

                $status = XString::new($builds['build_status'] ?? '');
                if ($status->contains(XStringType::regex('/^#[0-9]+[ ]\[[0-9]+\/[0-9]+\]/'))) {
                    $progress = $status->match(XStringType::regex('/^#[0-9]+[ ]\[[0-9]+\/[0-9]+\]/'));
                    $line .= $progress;
                } else {
                    $line .= $status->trim()->limit(50, '...');
                }

                Cmd::updateLive(0, $line);
            })
            ->setEnvVariable('HOST_PORT', (string) $tmp_port);

        foreach ($vars as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new \InvalidArgumentException('Environment variable keys and values must be strings.');
            }
            $manager->setEnvVariable($key, $value);
        }

        $success = $manager->start($build_containers, $save_logs);

        Cmd::finishLive();

        if (!$success) {
            if ($manager->hasPortInUseError() && $port instanceof FindNextPort) {
                Cmd::beginLive(1);
                do {
                    $tmp_port = $port->getAvailablePort($tmp_port);
                    Cmd::updateLive(0, 'Failed previous port, trying ' . $tmp_port . '...');
                    $manager->setEnvVariable('HOST_PORT', (string) $tmp_port)
                        ->onProgress(null);
                    $success = $manager->start($build_containers, $save_logs);
                    if (!$success && !$manager->hasPortInUseError()) {
                        print_r($manager->getErrors());
                        throw new \RuntimeException('Failed to start Docker containers for unknown reason (see above).');
                    }
                } while (!$success);
                Cmd::finishLive();
            } else {
                echo 'Failed to start Docker containers.' . PHP_EOL;
                print_r($manager->getErrors());
                throw new \RuntimeException('Failed to start Docker containers for unknown reason (see above).');
            }
        }

        return $tmp_port;
    }
}
