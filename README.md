# docker-manager

`orryv/docker-manager` is a small helper library that makes it easy to orchestrate Docker Compose projects from PHP. It was rebuilt to support multi-service compose files, better separation of concerns, and richer introspection while still preserving the proven log parsing behaviour from the original implementation.

## Installation

Install the library via Composer:

```
composer require orryv/docker-manager
```

By default the manager expects the [`ext-yaml`](https://www.php.net/manual/en/book.yaml.php) extension so it can call `yaml_parse_file()` and `yaml_emit_file()`. If the extension is not available you can install the optional [`symfony/yaml`](https://github.com/symfony/yaml) package instead and instruct the manager to use it:

```
composer require symfony/yaml

$manager = new \Orryv\DockerManager('symfony');
```

The constructor accepts either `ext` (default) or `symfony` as parser identifiers.

## Quick start

```php
use Orryv\DockerManager;

$manager = (new DockerManager())
    ->fromDockerCompose(__DIR__ . '/docker-compose.yml')
    ->setName('my-app')
    ->setEnvVariable('APP_ENV', 'local')
    ->onProgress(static function (array $progress): void {
        // Called roughly every 250ms with the parsed docker output.
        // You can surface container status updates or a build progress bar.
        var_export($progress);
    });

$manager->start();        // Runs `docker compose up -d`
// ... work with the containers ...
$manager->stop();         // Runs `docker compose down`
```

Behind the scenes the manager writes a temporary compose file, streams the Docker output to a temporary log every 250ms, parses the log in the same way as the original `DockerManagerOld`, and feeds the aggregated progress back to you.

## Service helpers

The new implementation keeps service level changes isolated inside a dedicated compose helper. Every helper returns `$this`, so you can fluently describe your setup:

```php
$manager
    ->ensureService('web')
    ->setBuildContext('web', __DIR__)
    ->setDockerfileForService('web', 'Dockerfile.dev')
    ->setContainerName('web', 'my-app-web')
    ->setPorts('web', ['8080:80'])
    ->setServiceEnvironmentVariable('web', 'APP_ENV', 'local');

$services = $manager->getServices();            // ['web']
$config   = $manager->getServiceConfig('web');  // Array representation of the service
```

Other handy helpers include:

- `setImage()` / `getImage()`
- `addPort()` / `getPorts()`
- `setDependsOn()` / `getDependsOn()`
- `setServiceEnvironmentVariables()` / `getServiceEnvironmentVariables()`
- `renameService()` / `removeService()`

Use `setDockerComposeValue()` when you want to merge arbitrary values into the compose definition (networks, volumes, custom extensions, ...).

## Starting from alternative sources

### From a Dockerfile

```php
$manager = (new DockerManager())
    ->fromDockerfile(__DIR__ . '/Dockerfile', 'web')
    ->setName('dockerfile-demo')
    ->setPorts('web', ['8080:80']);
```

The manager will build a minimal compose definition for you, pointing the `web` service to the Dockerfile directory. You can continue to customise it with the normal helper methods.

### From a running container name

```php
$manager = (new DockerManager())
    ->fromDockerContainerName('my-existing-container');

$manager->stop(); // Executes `docker stop my-existing-container`
```

This is handy for CLI utilities that need to clean up containers spawned elsewhere.

## Environment variables and debug output

The process environment passed to Docker can be extended via `setEnvVariable()` / `setEnvVariables()`. The manager automatically validates variable names.

If you call `setDebugPath('/path/to/debug')` the temporary compose file and the captured Docker log are copied into that directory for later inspection. Pass `true` to `$save_logs` when calling `start()` if you want to keep the temporary log file even without a debug path.

## Handling progress

The `onProgress()` callback receives an associative array with the keys `containers`, `networks`, `build_status` and `errors`. It is invoked whenever the parsed log changes. Because the log polling happens in a dedicated process runner you can safely update CLI UIs from the callback without blocking Docker.

## Helper class

The `Orryv\DockerManager\Helper` class wraps the fluent API for the most common "start a dev stack" scenario. It keeps probing for a free host port (using `Ports\FindNextPort`), starts the compose project, and reuses the port-on-retry logic from the legacy helper:

```php
use Orryv\DockerManager\Helper;
use Orryv\DockerManager\Ports\FindNextPort;

$port = Helper::startContainer(
    'demo-stack',
    __DIR__ . '/fixtures',
    'docker-compose.yml',
    new FindNextPort([8000, 8001], 9000, 9100),
    true,  // rebuild containers first
    false, // do not keep docker logs
    ['APP_ENV' => 'demo']
);
```

The original behaviour is still available through `Orryv\DockerManager\HelperOld` and `Orryv\DockerManagerOld` for backwards compatibility.

## Testing

The repository ships with a PHPUnit test suite. Run it with:

```
composer install
vendor/bin/phpunit
```

The tests use a fake process runner, so they do not spin up real containers.
