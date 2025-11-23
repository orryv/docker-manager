<?php

namespace Orryv\DockerComposeManager\DockerCompose\Definition;

use Orryv\DockerComposeManager\DockerCompose\Definition\Definition;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionsCollectionInterface;

class DefinitionsCollection implements DefinitionsCollectionInterface
{
    private array $configs = []; // id => Definition
    private ?string $activeConfigId = null;

    public function getCurrent(): ?Definition
    {
        if ($this->activeConfigId === null) {
            return null;
        }

        return $this->configs[$this->activeConfigId] ?? null;
    }

    public function get(string $id): Definition
    {
        if (!array_key_exists($id, $this->configs)) {
            throw new \InvalidArgumentException("No configuration found with ID: $id");
        }

        return $this->configs[$id] ?? null;
    }

    public function getRegisteredIds(): array
    {
        return array_keys($this->configs);
    }

    /**
     * Activate/select a configuration by its ID.
     */
    public function activate(string $id): void
    {
        if (!array_key_exists($id, $this->configs)) {
            throw new \InvalidArgumentException("No configuration found with ID: $id");
        }

        $this->activeConfigId = $id;
    }

    public function add(string $id, Definition $config): void
    {
        $this->configs[$id] = $config;

        $this->activeConfigId = $id;
    }
}
