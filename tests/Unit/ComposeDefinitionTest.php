<?php

declare(strict_types=1);

namespace Tests\Unit;

use Orryv\DockerManager\Compose\ComposeDefinition;
use PHPUnit\Framework\TestCase;

class ComposeDefinitionTest extends TestCase
{
    public function testServiceHelpersMaintainState(): void
    {
        $definition = new ComposeDefinition();
        $definition
            ->ensureService('web')
            ->setBuildContext('web', '/app')
            ->setDockerfile('web', 'Dockerfile.dev')
            ->setContainerName('web', 'demo-web')
            ->setImage('web', 'demo:latest')
            ->setPorts('web', ['8080:80'])
            ->setEnvironmentVariable('web', 'APP_ENV', 'local')
            ->setDependsOn('web', ['db']);

        $this->assertSame(['web'], $definition->getServices());
        $config = $definition->getService('web');
        $this->assertIsArray($config);
        $this->assertSame('demo-web', $config['container_name']);
        $this->assertSame('/app', $definition->getBuildContext('web'));
        $this->assertSame('Dockerfile.dev', $definition->getDockerfile('web'));
        $this->assertSame(['8080:80'], $definition->getPorts('web'));
        $this->assertSame(['APP_ENV' => 'local'], $definition->getEnvironmentVariables('web'));
        $this->assertSame(['db'], $definition->getDependsOn('web'));
        $this->assertSame('demo:latest', $definition->getImage('web'));
    }

    public function testMergeReplacesNumericArrays(): void
    {
        $definition = ComposeDefinition::fromArray([
            'services' => [
                'web' => [
                    'ports' => ['8080:80', '443:443'],
                    'environment' => ['APP_ENV' => 'local'],
                ],
            ],
        ]);

        $definition->updateService('web', ['ports' => ['8081:80']]);
        $definition->setEnvironmentVariables('web', ['APP_ENV' => 'test', 'CACHE' => '0']);

        $ports = $definition->getPorts('web');
        $this->assertSame(['8081:80'], $ports);
        $this->assertSame(['APP_ENV' => 'test', 'CACHE' => '0'], $definition->getEnvironmentVariables('web'));
    }

    public function testRenamingService(): void
    {
        $definition = ComposeDefinition::fromArray([
            'services' => ['web' => ['image' => 'demo']],
        ]);

        $definition->renameService('web', 'frontend');
        $this->assertFalse($definition->hasService('web'));
        $this->assertTrue($definition->hasService('frontend'));
    }

    public function testFromDockerfileCreatesExpectedStructure(): void
    {
        $definition = ComposeDefinition::fromDockerfile('web', '/project', 'Dockerfile');
        $config = $definition->getService('web');

        $this->assertIsArray($config);
        $this->assertSame('/project', $definition->getBuildContext('web'));
        $this->assertSame('Dockerfile', $definition->getDockerfile('web'));
    }
}
