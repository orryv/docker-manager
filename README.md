# docker-compose-manager

A PHP library to manage Docker Compose configurations and containers programmatically: create, modify, and control Docker Compose setups using PHP, including starting, stopping, and inspecting containers.

## TODO

- implement a isDockerRunning() method in the helper class.

## Installation

Requirements:

- PHP 8.2 or higher // TODO: check if we can support 8.1, or only 8.4
- Composer
- [`ext-yaml`](https://www.php.net/manual/en/book.yaml.php) **or** [`symfony/yaml`](https://github.com/symfony/yaml) (only one is required – the library auto-detects ext-yaml first and falls back to Symfony's parser when installed)
- Docker & Docker Compose CLI installed (e.g. included in Docker Desktop)
- [`psr/log`](https://github.com/php-fig/log) (for logging, optional)

Install the library via Composer as usual:

```
composer require orryv/docker-compose-manager
```

## Notes

- Make sure to add a valid healthcheck to each service in your docker-compose.yml files, so the library can determine when a container is "ready" (and thus can return from start(), etc). Example:

```yaml
services:
  web:
    image: my-web-image
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"] # Depends on the container
      interval: 5s
      timeout: 5s
      retries: 10
```

## Usage

Run multiple docker compose simultaneously from different docker-compose.yml files, manage them independently:

```php
use Orryv\DockerComposeManager;

$dcm = new DockerComposeManager();


// Each configuration is registered under a custom $id. 
//  You can later start/stop/inspect by passing one or more IDs to the manager.
$config1 = $dcm->fromDockerComposeFile('id-1', 'path/to/docker-compose.yml');
$config2 = $dcm->fromDockerComposeFile('id-2', 'path/to/another-docker-compose.yml');

$config1->setEnvVariable('MY_ENV_VAR', 'value1');

$dcm->start(); // runs all registered compose projects in parallel and returns when they all finish or become healthy

```

## Methods

### `DockerComposeManager`

```php
use Orryv\DockerComposeManager\DockerComposeConfig;

fromDockerComposeFile(string $id, string $file_path): DockerComposeConfig
fromYamlArray(string $id, array $yaml_array): DockerComposeConfig

// Next "from" methods can't be used to start or reset containers,
//  Only inspect, stop, remove, etc.
fromContainerName(string $id, string $container_name): DockerComposeConfig
fromProjectName(string $id, string $project_name): DockerComposeConfig

// Container management
start(string|array|null $id = null, ?string $service_name = null, bool $rebuild_containers = false): bool
stop(
    string|array|null $id = null, 
    ?string $service_name = null, 
    bool $remove_volumes = false, 
    ?string $remove_images = null, // 'all', 'local', null
): bool
remove(
    string|array|null $id = null, 
    ?string $service_name = null, 
    bool $remove_volumes = false, 
    ?string $remove_images = null, // 'all', 'local', null
): bool
restart(
    string|array|null $id = null, 
    ?string $service_name = null,
    bool $rebuild_containers = false,
    bool $remove_volumes = false, 
    ?string $remove_images = null, // 'all', 'local', null
): bool
inspect(string|array|null $id = null, ?string $service_name = null): ?array
containerExists(string|array|null $id = null, ?string $service_name = null): bool
volumesExist(string|array|null $id = null): bool
imagesExist(string|array|null $id = null): bool
isRunning(string|array|null $id = null, ?string $service_name = null): bool
getErrors(string|array|null $id = null): array
```

### `DockerComposeConfig`

```php
#### Configuration ####
debug(?string $path = null): self // disabled when null, will put tmp files (docker-compose, logs, etc) in $path when set
setEnvVariable(string $name, string $value): self // Sets environment variable so docker-compose can use variables
setEnvVariables(array $vars): self // expects array<string,string>
setLogger(Psr\Log\LoggerInterface $logger): self

// Currently callbacks apply to the whole project, not individual services.
// TODO: check if we can expose per-service callbacks.
onProgress(callable $callback, int $interval_ms = 250): self // TODO: define signature
onSuccess(callable $callback): self // TODO: define signature
onError(callable $callback): self // TODO: define signature

#### docker-compose configuration manipulation ####
// You can edit the parsed docker-compose.yml array directly:
setDockerComposeValues(array $values): self // merges values into docker-compose configuration

// Or use helper methods to set common values:
setProjectName(string $name): self
setContainerName(string $service_name, string $container_name): self
setServiceName(string $service_name, string $new_service_name): self
setPortMapping(string $service_name, int $container_port, int $host_port, string $protocol = 'tcp'): self
setNetwork(string $network_name, array $options = []): self
```
