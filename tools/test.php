#!/usr/bin/env php
<?php

declare(strict_types=1);

use Orryv\DockerManager;

require_once __DIR__ . '/../vendor/autoload.php';

echo 'Starting docker container...' . PHP_EOL;

$dm = new DockerManager(__DIR__ . '/../tests/docker/start-container-demo/docker-compose.yml');