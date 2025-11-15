#!/usr/bin/env php
<?php

declare(strict_types=1);

use Orryv\DockerManager;

require_once __DIR__ . '/../vendor/autoload.php';

$composePath = __DIR__ . '/../tests/docker/start-container-demo/docker-compose.yml';

$manager = (new DockerManager())
    ->fromDockerCompose($composePath)
    ->setName('docker-manager-test')
    ->setEnvVariable('DEMO_BUILD_MESSAGE', 'Test run via tools/test.php')
    ->onProgress(static function (array $progress): void {
        $summary = $progress['build_status'] ?? '';
        if ($summary !== '') {
            echo $summary . PHP_EOL;
        }
    });

echo 'This script demonstrates the new DockerManager API. No containers are started.' . PHP_EOL;
echo 'Inspect $manager via var_dump to explore the composed services:' . PHP_EOL;
var_dump($manager->getServices());
