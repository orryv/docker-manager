<?php 

namespace Orryv\DockerComposeManager\DockerCompose;

/**
 * Class DockerComposeHandler
 * 
 * Used to handle Docker Compose configurations.
 *  Meaning it will hold the current state of a Docker Compose file in array format,
 *  and provide methods to manipulate and retrieve information from it.
 */
class DockerComposeHandler
{
    private array $dockerCompose;

    public function __construct(array $dockerComposeArray)
    {
        $this->dockerCompose = $dockerComposeArray;
    }

    public function toArray(): array
    {
        return $this->dockerCompose;
    }
}