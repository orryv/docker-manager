<?php

namespace Tests\Integration;

use Orryv\DockerComposeManager;
use Orryv\DockerComposeManager\Runtime\Cli\CliDockerRuntime;
use Orryv\DockerComposeManager\Runtime\Cli\DockerOutputParser;
use Orryv\DockerComposeManager\State\ContainerState;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Stubs\TestCommandBuilder;
use Tests\Integration\Stubs\TestDockerInspector;

class DockerComposeManagerIntegrationTest extends TestCase
{
    public function testStartRunsFakeCliAndUpdatesState(): void
    {
        $runtime = new CliDockerRuntime(
            new TestCommandBuilder(),
            new TestDockerInspector(),
            new DockerOutputParser()
        );

        $manager = new DockerComposeManager($runtime);
        $config = $manager->fromYamlArray('integration', [
            'services' => [
                'worker' => [
                    'image' => 'fake/image',
                    'healthcheck' => ['test' => ['CMD', 'echo', 'ok']],
                ],
            ],
        ]);

        $progressInvocations = 0;
        $config->onProgress(function (string $id, array $progress) use (&$progressInvocations): void {
            $progressInvocations++;
        });

        self::assertTrue($manager->start('integration'));
        self::assertGreaterThan(0, $progressInvocations);
        $state = $manager->getState('integration');
        self::assertInstanceOf(ContainerState::class, $state);
        self::assertSame('running', $state->getStatus());
    }
}
