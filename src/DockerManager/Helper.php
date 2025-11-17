<?php

namespace Orryv\DockerManager;

use Orryv\Cmd;
use Orryv\DockerComposeManager;
use Orryv\DockerComposeManager\Config\DockerComposeConfig;
use Orryv\DockerManager\Ports\FindNextPort;
use Orryv\XString;
use Orryv\XStringType;
use RuntimeException;

/**
 * Convenience façade for starting compose projects with dynamic environment
 * variables, progress output, debug logging, and automatic port retries.
 */
class Helper
{
    /** @var callable|null */
    private static $managerFactory = null;

    /**
     * Allow tests/consumers to inject a pre-configured DockerComposeManager instance.
     */
    public static function useDockerComposeManagerFactory(?callable $factory): void
    {
        self::$managerFactory = $factory;
    }

    /**
     * Start a docker-compose project with helpful defaults (env injection, logging, retries).
     *
     * @param int|FindNextPort $port Either a fixed port or port finder strategy.
     * @param array<string,mixed> $vars Additional env vars for the compose file.
     *
     * @return int Actual host port used for the container.
     */
    public static function startContainer(
        string $name,
        string $workdir,
        string $composePath,
        int|FindNextPort $port,
        bool $rebuildContainers,
        bool $saveLogs,
        array $vars = []
    ): int {
        $manager = self::createManager();
        $composeFile = self::resolveComposeFile($workdir, $composePath);

        $assignedPort = self::resolvePort($port);
        $config = $manager->fromDockerComposeFile($name, $composeFile);
        $config->setEnvVariable('HOST_PORT', (string) $assignedPort);
        if (!empty($vars)) {
            $config->setEnvVariables(self::stringifyVars($vars));
        }
        if ($saveLogs) {
            $config->debug(self::defaultDebugDirectory($workdir));
        }
        self::attachProgressCallback($config);

        Cmd::beginLive(1);
        $success = $manager->start($name, null, $rebuildContainers);
        Cmd::finishLive();

        if ($success) {
            return $assignedPort;
        }

        $errors = self::collectErrors($manager, $name);
        if ($port instanceof FindNextPort && self::hasPortInUseError($errors)) {
            Cmd::beginLive(1);
            do {
                $nextPort = $port->getAvailablePort($assignedPort);
                if ($nextPort === null) {
                    Cmd::finishLive();
                    throw new RuntimeException('Failed to locate a free port to start the container.');
                }
                $assignedPort = $nextPort;
                Cmd::updateLive(0, 'Failed previous port, trying ' . $assignedPort . '...');
                $config->setEnvVariable('HOST_PORT', (string) $assignedPort);
                $success = $manager->start($name, null, $rebuildContainers);
                $errors = $success ? [] : self::collectErrors($manager, $name);
            } while (!$success && self::hasPortInUseError($errors));
            Cmd::finishLive();
        }

        if (!$success) {
            self::reportErrors($errors);
        }

        return $assignedPort;
    }

    /**
     * Lazily instantiate the DockerComposeManager via the factory override or default class.
     */
    private static function createManager(): DockerComposeManager
    {
        if (self::$managerFactory !== null) {
            $manager = call_user_func(self::$managerFactory);
            if (!$manager instanceof DockerComposeManager) {
                throw new RuntimeException('DockerComposeManager factory must return an instance of DockerComposeManager.');
            }

            return $manager;
        }

        return new DockerComposeManager();
    }

    /**
     * Resolve a compose path relative to the given working directory.
     */
    private static function resolveComposeFile(string $workdir, string $composePath): string
    {
        $directory = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $workdir), DIRECTORY_SEPARATOR);
        $relative = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $composePath), DIRECTORY_SEPARATOR);

        return $directory . DIRECTORY_SEPARATOR . $relative;
    }

    /**
     * Decide which host port should be used, optionally via a FindNextPort strategy.
     *
     * @return int
     */
    private static function resolvePort(int|FindNextPort $port): int
    {
        if (is_int($port)) {
            return $port;
        }

        $value = $port->getAvailablePort();
        if ($value === null) {
            throw new RuntimeException('No available port found.');
        }

        return $value;
    }

    /**
     * Cast arbitrary env var values to strings as expected by docker compose.
     *
     * @param array<string,mixed> $vars
     *
     * @return array<string,string>
     */
    private static function stringifyVars(array $vars): array
    {
        $stringified = [];
        foreach ($vars as $key => $value) {
            $stringified[$key] = (string) $value;
        }

        return $stringified;
    }

    /**
     * Attach a progress callback that streams status updates to the terminal.
     */
    private static function attachProgressCallback(DockerComposeConfig $config): void
    {
        $containers = [];
        $lastMessage = '';
        $config->onProgress(function (string $configId, array $payload) use (&$containers, &$lastMessage) {
            if (self::isAggregatedProgressPayload($payload)) {
                $containers = $payload['containers'] ?? [];
                $lines = $payload['lines'] ?? [];
                $latestLine = '';
                if (!empty($lines)) {
                    $latestLine = (string) end($lines);
                    reset($lines);
                }
                $lastMessage = $payload['build_status']
                    ?: $latestLine
                    ?: $lastMessage;

                $line = '  ';
                $inProgress = false;
                foreach ($containers as $container => $status) {
                    $line .= $container . ': ' . $status . ' | ';
                    if (!in_array(strtolower($status), ['started', 'running', 'healthy'], true)) {
                        $inProgress = true;
                    }
                }

                if (!empty($containers) && !$inProgress) {
                    Cmd::updateLive(0, '  Starting up container...');
                    return;
                }

                if ($line !== '  ') {
                    $line .= '=> ';
                }

                $message = $lastMessage !== ''
                    ? XString::new($lastMessage)
                    : XString::new('Waiting for docker compose...');

                if ($message->contains(XStringType::regex('/^#[0-9]+[ ]\[[0-9]+\/[0-9]+\]/'))) {
                    $progress = $message->match(XStringType::regex('/^#[0-9]+[ ]\[[0-9]+\/[0-9]+\]/'));
                    $line .= (string) $progress;
                } else {
                    $line .= $message->trim()->limit(50, '...');
                }

                Cmd::updateLive(0, $line);

                return;
            }

            if (empty($payload)) {
                $containers = [];
                $lastMessage = '';
                Cmd::updateLive(0, '  Starting up container...');
                return;
            }

            foreach ($payload as $event) {
                $container = $event['container'] ?? null;
                if ($container) {
                    $containers[$container] = ucfirst($event['status'] ?? '');
                }
                $lastMessage = $event['raw'] ?? $lastMessage;
            }

            $line = '  ';
            $inProgress = false;
            foreach ($containers as $container => $status) {
                $line .= $container . ': ' . $status . ' | ';
                if (!in_array(strtolower($status), ['started', 'running', 'healthy'], true)) {
                    $inProgress = true;
                }
            }

            if (!empty($containers) && !$inProgress) {
                Cmd::updateLive(0, '  Starting up container...');
                return;
            }

            if ($line !== '  ') {
                $line .= '=> ';
            }

            $message = $lastMessage !== '' ? XString::new($lastMessage) : XString::new('Waiting for docker compose...');
            if ($message->contains(XStringType::regex('/^#[0-9]+[ ]\[[0-9]+\/[0-9]+\]/'))) {
                $progress = $message->match(XStringType::regex('/^#[0-9]+[ ]\[[0-9]+\/[0-9]+\]/'));
                $line .= (string) $progress;
            } else {
                $line .= $message->trim()->limit(50, '...');
            }

            Cmd::updateLive(0, $line);
        });
    }

    /**
     * @param array<int|string,mixed> $payload
     */
    private static function isAggregatedProgressPayload(array $payload): bool
    {
        return isset($payload['containers']) || isset($payload['networks']) || array_key_exists('build_status', $payload);
    }

    /**
     * Collect the latest error list from the manager for a specific id.
     *
     * @return array<int,string>
     */
    private static function collectErrors(DockerComposeManager $manager, string $name): array
    {
        $errors = $manager->getErrors($name);
        if (!isset($errors[$name])) {
            return [];
        }

        return $errors[$name];
    }

    /**
     * Determine whether the error output indicates a host port collision.
     *
     * @param array<int,string> $errors
     *
     * @return bool
     */
    private static function hasPortInUseError(array $errors): bool
    {
        foreach ($errors as $error) {
            $x = XString::new((string) $error);
            if ($x->trim()->endsWith('failed: port is already allocated')) {
                return true;
            }
            if ($x->contains('address already in use')) {
                return true;
            }
            if ($x->contains('Ports are not available')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dump errors to stdout and throw a RuntimeException.
     *
     * @param array<int,string> $errors
     *
     * @return void
     */
    private static function reportErrors(array $errors): void
    {
        echo 'Failed to start Docker containers.' . PHP_EOL;
        if (!empty($errors)) {
            print_r($errors);
        }

        throw new RuntimeException('Failed to start Docker containers for unknown reason (see above).');
    }

    /**
     * @return string
     */
    private static function defaultDebugDirectory(string $workdir): string
    {
        $dir = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $workdir), DIRECTORY_SEPARATOR);

        return $dir . DIRECTORY_SEPARATOR . 'docker-debug';
    }
}
