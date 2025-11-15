<?php

declare(strict_types=1);

namespace Tests\Integration;

use Orryv\DockerManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FakeProcessRunner;

class DockerManagerTest extends TestCase
{
    private string $composePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->composePath = realpath(__DIR__ . '/../docker/start-container-demo/docker-compose.yml');
        self::assertNotFalse($this->composePath, 'Fixture compose file missing');
    }

    public function testStartUsesProcessRunner(): void
    {
        $logs = [
            "Network demo-net  Created\n",
            "Container storyteller  Started\n",
            "#1 [1/3] Building\n",
        ];
        $runner = new FakeProcessRunner($logs);

        $progressData = [];
        $manager = (new DockerManager('symfony'))
            ->fromDockerCompose($this->composePath)
            ->updateService('storyteller', ['healthcheck' => null])
            ->setName('integration-demo')
            ->onProgress(function (array $progress) use (&$progressData): void {
                $progressData[] = $progress;
            });
        $manager->setProcessRunner($runner);

        $success = $manager->start(false, true);

        $this->assertTrue($success);
        $this->assertSame(0, $manager->getLastExitCode());
        $this->assertNotEmpty($progressData);
        $this->assertStringContainsString('up -d', $runner->commands[0]);
        $expectedProjectDir = rtrim(dirname($this->composePath), DIRECTORY_SEPARATOR);
        $this->assertStringContainsString('--project-directory', $runner->commands[0]);
        $this->assertStringContainsString($expectedProjectDir, $runner->commands[0]);
        $this->assertSame(dirname($this->composePath), rtrim($runner->workingDirectories[0], DIRECTORY_SEPARATOR));
    }

    public function testStopUsesProcessRunner(): void
    {
        $runnerStart = new FakeProcessRunner(["Container storyteller  Started\n"]);
        $runnerStop = new FakeProcessRunner(["Container storyteller  Stopped\n"]);

        $manager = (new DockerManager('symfony'))
            ->fromDockerCompose($this->composePath)
            ->updateService('storyteller', ['healthcheck' => null])
            ->setName('integration-demo')
            ->setProcessRunner($runnerStart);

        $this->assertTrue($manager->start());

        $manager->setProcessRunner($runnerStop);
        $this->assertTrue($manager->stop());
        $this->assertStringContainsString('down', $runnerStop->commands[0]);
    }

    public function testStartFailsWhenBuildContextMissing(): void
    {
        $runner = new FakeProcessRunner();

        $manager = (new DockerManager('symfony'))
            ->fromDockerCompose($this->composePath)
            ->updateService('storyteller', [
                'build' => [
                    'context' => './does-not-exist',
                    'dockerfile' => 'Dockerfile',
                ],
                'healthcheck' => null,
            ])
            ->setProcessRunner($runner);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('build context directory not found');

        $manager->start();
    }

    public function testStartFailsWhenDockerfileMissing(): void
    {
        $runner = new FakeProcessRunner();
        $temporaryContext = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'docker-manager-missing-dockerfile-' . uniqid('', true);
        if (!mkdir($temporaryContext) && !is_dir($temporaryContext)) {
            $this->fail('Unable to create temporary context directory for test.');
        }

        try {
            $manager = (new DockerManager('symfony'))
                ->fromDockerCompose($this->composePath)
                ->updateService('storyteller', [
                    'build' => [
                        'context' => $temporaryContext,
                        'dockerfile' => 'MissingDockerfile',
                    ],
                    'healthcheck' => null,
                ])
                ->setProcessRunner($runner);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('dockerfile not found');

            $manager->start();
        } finally {
            @rmdir($temporaryContext);
        }
    }

    public function testStartFailsWhenDefaultDockerfileMissing(): void
    {
        $runner = new FakeProcessRunner();
        $temporaryContext = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'docker-manager-missing-default-dockerfile-' . uniqid('', true);
        if (!mkdir($temporaryContext) && !is_dir($temporaryContext)) {
            $this->fail('Unable to create temporary context directory for test.');
        }

        try {
            $manager = (new DockerManager('symfony'))
                ->fromDockerCompose($this->composePath)
                ->updateService('storyteller', [
                    'build' => $temporaryContext,
                    'healthcheck' => null,
                ])
                ->setProcessRunner($runner);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('default dockerfile expected');

            try {
                $manager->start();
            } catch (RuntimeException $exception) {
                $this->assertSame([], $runner->commands);
                throw $exception;
            }
        } finally {
            @rmdir($temporaryContext);
        }
    }

    public function testStartFailsWhenBindMountSourceMissing(): void
    {
        $runner = new FakeProcessRunner();
        $missingPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'docker-manager-missing-volume-' . uniqid('', true);
        if (file_exists($missingPath)) {
            $this->fail('Unexpected pre-existing path for bind mount test.');
        }

        $relativeMissing = './' . basename($missingPath);

        $manager = (new DockerManager('symfony'))
            ->fromDockerCompose($this->composePath)
            ->updateService('storyteller', [
                'volumes' => [
                    $relativeMissing . ':/opt/demo/missing',
                ],
                'healthcheck' => null,
            ])
            ->setProcessRunner($runner);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('volume host path not found');

        try {
            $manager->start();
        } catch (RuntimeException $exception) {
            $this->assertSame([], $runner->commands);
            $expectedResolved = $this->resolveFixturePath($relativeMissing);
            $this->assertStringContainsString($expectedResolved, $exception->getMessage());
            throw $exception;
        }
    }

    public function testStopFromContainerName(): void
    {
        $runner = new FakeProcessRunner();
        $manager = (new DockerManager('symfony'))
            ->fromDockerContainerName('existing-container')
            ->setProcessRunner($runner);

        $this->assertTrue($manager->stop());
        $this->assertStringContainsString('docker stop', $runner->commands[0]);
    }

    public function testStartCapturesErrors(): void
    {
        $logs = [
            "Error response from daemon: address already in use\n",
        ];
        $runner = new FakeProcessRunner($logs, exitCode: 1);

        $manager = (new DockerManager('symfony'))
            ->fromDockerCompose($this->composePath)
            ->updateService('storyteller', ['healthcheck' => null])
            ->setProcessRunner($runner);

        $success = $manager->start();

        $this->assertFalse($success);
        $this->assertSame(1, $manager->getLastExitCode());
        $this->assertNotEmpty($manager->getErrors());
        $this->assertTrue($manager->hasPortInUseError());
    }

    public function testStartProvidesContextWhenFallbackErrorGenerated(): void
    {
        $logs = [
            "The system cannot find the path specified.\n",
        ];
        $runner = new FakeProcessRunner($logs, exitCode: 17);

        $manager = (new DockerManager('symfony'))
            ->fromDockerCompose($this->composePath)
            ->updateService('storyteller', ['healthcheck' => null])
            ->setProcessRunner($runner);

        $success = $manager->start();

        $this->assertFalse($success);
        $errors = $manager->getErrors();
        $this->assertNotEmpty($errors);
        $errorMessage = $errors[0];

        $this->assertStringContainsString('running in', $errorMessage);
        $this->assertStringContainsString(dirname($this->composePath), $errorMessage);
        $this->assertStringContainsString('Temporary compose file:', $errorMessage);
        $this->assertStringContainsString('Captured log file:', $errorMessage);
        $this->assertStringContainsString('Last output: The system cannot find the path specified.', $errorMessage);
    }

    private function resolveFixturePath(string $relative): string
    {
        $base = dirname($this->composePath);
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

        if ($normalized === '' || $normalized === '.' || $normalized === DIRECTORY_SEPARATOR) {
            return rtrim($base, DIRECTORY_SEPARATOR);
        }

        if ($this->isAbsolutePath($normalized)) {
            return $normalized;
        }

        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':';
    }
}
