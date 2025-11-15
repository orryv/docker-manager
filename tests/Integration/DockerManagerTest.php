<?php

declare(strict_types=1);

namespace Tests\Integration;

use Orryv\DockerManager;
use PHPUnit\Framework\TestCase;
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
}
