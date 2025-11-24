#!/usr/bin/env php
<?php

declare(strict_types=1);

use Orryv\DockerComposeManager;
use Orryv\YamlParserFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$fixtureDir = realpath(__DIR__ . '/../tests/docker/start-container-demo');
$composeFile = $fixtureDir . DIRECTORY_SEPARATOR . 'docker-compose.yml';

echo 'Starting docker container...' . PHP_EOL;

$debug = __DIR__ . '/../tmp';
if (!file_exists($debug)) {
    mkdir($debug, 0777, true);
}

$dcm = DockerComposeManager::new();
$dcm->debug($debug);
$config = $dcm->fromDockerComposeFile('test', $composeFile);
$dcm->startAsync(null, null, true);

do{
    $progress = $dcm->getProgress('test')->get('test');
    // print_r($progress);
    echo 'Progress: ' . ($progress->getBuildLastLine() ?? '') . PHP_EOL;
    usleep(500000);
} while (!$progress->isFinishedExecuting() && !$progress->isHealthy());
