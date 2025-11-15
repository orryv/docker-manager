<?php

namespace Orryv\DockerManager\Compose;

use InvalidArgumentException;

class ComposeDefinition
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public static function fromDockerfile(string $serviceName, string $contextDir, string $dockerfile): self
    {
        $definition = new self([
            'version' => '3.8',
            'services' => [
                $serviceName => [
                    'build' => [
                        'context' => $contextDir,
                        'dockerfile' => $dockerfile,
                    ],
                ],
            ],
        ]);

        return $definition;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function merge(array $values): self
    {
        $this->data = self::mergeRecursive($this->data, $values);
        return $this;
    }

    public function getServices(): array
    {
        $services = $this->data['services'] ?? [];
        if (!is_array($services)) {
            return [];
        }

        return array_keys($services);
    }

    public function hasService(string $service): bool
    {
        $services = $this->data['services'] ?? [];
        return isset($services[$service]) && is_array($services[$service]);
    }

    public function ensureService(string $service): self
    {
        if (!isset($this->data['services']) || !is_array($this->data['services'])) {
            $this->data['services'] = [];
        }

        if (!isset($this->data['services'][$service]) || !is_array($this->data['services'][$service])) {
            $this->data['services'][$service] = [];
        }

        return $this;
    }

    public function getService(string $service): ?array
    {
        $services = $this->data['services'] ?? [];
        if (!isset($services[$service]) || !is_array($services[$service])) {
            return null;
        }

        return $services[$service];
    }

    public function setService(string $service, array $config): self
    {
        $this->ensureServiceContainer();
        $this->data['services'][$service] = $config;
        return $this;
    }

    public function updateService(string $service, array $config): self
    {
        $this->ensureService($service);
        $this->data['services'][$service] = self::mergeRecursive($this->data['services'][$service], $config);
        return $this;
    }

    public function removeService(string $service): self
    {
        if (isset($this->data['services'][$service])) {
            unset($this->data['services'][$service]);
        }

        return $this;
    }

    public function renameService(string $oldName, string $newName): self
    {
        if ($oldName === $newName) {
            return $this;
        }

        if (!$this->hasService($oldName)) {
            throw new InvalidArgumentException("Cannot rename missing service '{$oldName}'.");
        }

        $this->ensureServiceContainer();
        $this->data['services'][$newName] = $this->data['services'][$oldName];
        unset($this->data['services'][$oldName]);

        return $this;
    }

    public function setProjectName(?string $name): self
    {
        if ($name === null) {
            unset($this->data['name']);
            return $this;
        }

        $this->data['name'] = $name;
        return $this;
    }

    public function getProjectName(): ?string
    {
        $name = $this->data['name'] ?? null;
        return is_string($name) && $name !== '' ? $name : null;
    }

    public function setContainerName(string $service, string $containerName): self
    {
        $this->ensureService($service);
        $this->data['services'][$service]['container_name'] = $containerName;
        return $this;
    }

    public function getContainerName(string $service): ?string
    {
        $serviceData = $this->getService($service);
        if ($serviceData === null) {
            return null;
        }

        $name = $serviceData['container_name'] ?? null;
        return is_string($name) && $name !== '' ? $name : null;
    }

    public function setBuildContext(string $service, string $context): self
    {
        $this->ensureService($service);
        $build = $this->data['services'][$service]['build'] ?? [];
        if (!is_array($build)) {
            $build = ['context' => $context];
        } else {
            $build['context'] = $context;
        }
        $this->data['services'][$service]['build'] = $build;
        return $this;
    }

    public function getBuildContext(string $service): ?string
    {
        $serviceData = $this->getService($service);
        if ($serviceData === null) {
            return null;
        }

        $build = $serviceData['build'] ?? null;
        if (is_string($build)) {
            return $build;
        }
        if (is_array($build) && isset($build['context']) && is_string($build['context'])) {
            return $build['context'];
        }

        return null;
    }

    public function setDockerfile(string $service, string $dockerfile): self
    {
        $this->ensureService($service);
        $build = $this->data['services'][$service]['build'] ?? [];
        if (!is_array($build)) {
            $build = ['dockerfile' => $dockerfile];
        } else {
            $build['dockerfile'] = $dockerfile;
        }
        $this->data['services'][$service]['build'] = $build;
        return $this;
    }

    public function getDockerfile(string $service): ?string
    {
        $serviceData = $this->getService($service);
        if ($serviceData === null) {
            return null;
        }

        $build = $serviceData['build'] ?? null;
        if (is_array($build) && isset($build['dockerfile']) && is_string($build['dockerfile'])) {
            return $build['dockerfile'];
        }

        return null;
    }

    public function setImage(string $service, string $image): self
    {
        $this->ensureService($service);
        $this->data['services'][$service]['image'] = $image;
        return $this;
    }

    public function getImage(string $service): ?string
    {
        $serviceData = $this->getService($service);
        if ($serviceData === null) {
            return null;
        }

        $image = $serviceData['image'] ?? null;
        return is_string($image) && $image !== '' ? $image : null;
    }

    public function setPorts(string $service, array $ports): self
    {
        $this->ensureService($service);
        $this->data['services'][$service]['ports'] = array_values($ports);
        return $this;
    }

    public function addPort(string $service, string $port): self
    {
        $this->ensureService($service);
        if (!isset($this->data['services'][$service]['ports']) || !is_array($this->data['services'][$service]['ports'])) {
            $this->data['services'][$service]['ports'] = [];
        }
        $this->data['services'][$service]['ports'][] = $port;
        return $this;
    }

    public function getPorts(string $service): array
    {
        $serviceData = $this->getService($service);
        if ($serviceData === null) {
            return [];
        }

        $ports = $serviceData['ports'] ?? [];
        if (!is_array($ports)) {
            return [];
        }

        return array_values($ports);
    }

    public function setEnvironmentVariable(string $service, string $name, string $value): self
    {
        $this->ensureService($service);
        if (!isset($this->data['services'][$service]['environment']) || !is_array($this->data['services'][$service]['environment'])) {
            $this->data['services'][$service]['environment'] = [];
        }
        $this->data['services'][$service]['environment'][$name] = $value;
        return $this;
    }

    public function setEnvironmentVariables(string $service, array $variables): self
    {
        foreach ($variables as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new InvalidArgumentException('Environment variable keys and values must be strings.');
            }
            $this->setEnvironmentVariable($service, $key, $value);
        }

        return $this;
    }

    public function getEnvironmentVariables(string $service): array
    {
        $serviceData = $this->getService($service);
        if ($serviceData === null) {
            return [];
        }

        $env = $serviceData['environment'] ?? [];
        if (!is_array($env)) {
            return [];
        }

        return $env;
    }

    public function setDependsOn(string $service, array $dependencies): self
    {
        $this->ensureService($service);
        $this->data['services'][$service]['depends_on'] = array_values($dependencies);
        return $this;
    }

    public function getDependsOn(string $service): array
    {
        $serviceData = $this->getService($service);
        if ($serviceData === null) {
            return [];
        }

        $depends = $serviceData['depends_on'] ?? [];
        if (!is_array($depends)) {
            return [];
        }

        return array_values($depends);
    }

    private function ensureServiceContainer(): void
    {
        if (!isset($this->data['services']) || !is_array($this->data['services'])) {
            $this->data['services'] = [];
        }
    }

    private static function mergeRecursive(array $original, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($original[$key]) && is_array($original[$key])) {
                $bothAssoc = self::isAssoc($value) || self::isAssoc($original[$key]);
                if ($bothAssoc) {
                    $original[$key] = self::mergeRecursive($original[$key], $value);
                    continue;
                }
            }

            $original[$key] = $value;
        }

        return $original;
    }

    private static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
