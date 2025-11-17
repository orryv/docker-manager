#!/usr/bin/env php
<?php

declare(strict_types=1);

use Orryv\DockerManager\Ports\FindNextPort;
use Orryv\DockerManager\Helper;

// Try to locate Composer's autoloader from several common paths.
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
$autoloadLoaded = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require $autoloadPath;
        $autoloadLoaded = true;
        break;
    }
}

if (!$autoloadLoaded) {
    fwrite(STDERR, "Unable to locate Composer autoload file. Did you run 'composer install'?" . PHP_EOL);
    exit(1);
}

$fixtureDir = realpath(__DIR__ . '/../tests/docker/start-container-demo');
if ($fixtureDir === false) {
    fwrite(STDERR, "Unable to resolve fixture directory for the startContainer demo." . PHP_EOL);
    exit(1);
}

$portFinder = new FindNextPort([8080, 8081], 9000, 9200);

try {
    $port = Helper::startContainer(
        'start-container-demo',
        $fixtureDir,
        'docker-compose.yml',
        $portFinder,
        true,
        false,
        [
            'DEMO_BUILD_MESSAGE' => 'startContainer helper visual test',
        ]
    );

    $composeFile = $fixtureDir . DIRECTORY_SEPARATOR . 'docker-compose.yml';
    echo PHP_EOL;
    echo "Demo container started on http://localhost:{$port}" . PHP_EOL;
    echo "Stop it afterwards with:" . PHP_EOL;
    echo "  docker compose -f {$composeFile} down" . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, PHP_EOL . 'Failed to start the demo container: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
