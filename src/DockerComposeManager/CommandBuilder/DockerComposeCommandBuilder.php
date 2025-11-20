<?php 

namespace Orryv\DockerComposeManager\CommandBuilder;

use Orryv\DockerComposeManager\DockerCompose\DockerComposeHandler;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException;

class DockerComposeCommandBuilder
{
    private DockerComposeHandler $dockerComposeHandler;

    public function __construct(DockerComposeHandler $dockerComposeHandler)
    {
        $this->dockerComposeHandler = $dockerComposeHandler;
    }

    public function start(null|string|array $serviceNames = null, bool $rebuildContainers = false): string
    {
        // Path to the specific docker-compose file you want to use
        $composeFile = $this->dockerComposeHandler->getTmpFilePath();

        if ($composeFile === null) {
            throw new DockerComposeManagerException('Temporary docker-compose file has not been created yet.');
        }

        if(!file_exists($composeFile)) {
            throw new DockerComposeManagerException('Temporary docker-compose file does not exist: ' . $composeFile);
        }

        // Base command: tell docker-compose which file to use
        $command = 'docker-compose -f ' . escapeshellarg($composeFile) . ' up -d';

        if ($rebuildContainers) {
            $command .= ' --build';
        }

        if (is_string($serviceNames)) {
            $serviceNames = [trim($serviceNames)];
        }

        if (is_array($serviceNames)) {
            $serviceNames = array_filter(
                array_map('trim', $serviceNames),
                fn($s) => $s !== ''
            );

            if ($serviceNames) {
                $command .= ' ' . implode(' ', array_map('escapeshellarg', $serviceNames));
            }
        }

        return $command;
    }
}