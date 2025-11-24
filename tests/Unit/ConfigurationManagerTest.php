<?php

namespace Tests\Unit;

use Orryv\ConfigurationManager;
use Orryv\DockerComposeManager\DockerCompose\Definition\DefinitionFactory;
use Orryv\DockerComposeManager\DockerCompose\DefinitionsCollection\DefinitionsCollection;
use Orryv\DockerComposeManager\DockerCompose\FileHandler\FileHandlerFactory;
use Orryv\DockerComposeManager\Exceptions\DockerComposeManagerException as RootDockerComposeManagerException;
use Orryv\DockerComposeManager\Exceptions\YamlParserException;
use Orryv\DockerComposeManager\YamlParsers\YamlParserInterface;
use PHPUnit\Framework\TestCase;

class ConfigurationManagerTest extends TestCase
{
    private function createParser(): YamlParserInterface
    {
        return new class implements YamlParserInterface {
            public function parse(string $yaml_content): array
            {
                return [
                    'services' => [
                        'app' => ['container_name' => 'app'],
                    ],
                ];
            }

            public function build(array $data): string
            {
                return 'built-yaml';
            }
        };
    }

    public function testFromDockerComposeFileLoadsDefinition(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'compose-');
        file_put_contents($tempFile, 'services: {}');

        $manager = new ConfigurationManager(
            $this->createParser(),
            new DefinitionsCollection(),
            new FileHandlerFactory(),
            new DefinitionFactory()
        );

        $definition = $manager->fromDockerComposeFile('one', $tempFile);

        $this->assertSame(dirname($tempFile), $manager->getExecutionPath());
        $this->assertSame($definition, $manager->getDefinitionsCollection()->get('one'));

        unlink($tempFile);
    }

    public function testFromYamlArrayRegistersDefinition(): void
    {
        $manager = new ConfigurationManager($this->createParser());

        $definition = $manager->fromYamlArray('one', [
            'services' => ['app' => ['container_name' => 'app']],
        ], sys_get_temp_dir());

        $this->assertSame($definition, $manager->getDefinitionsCollection()->get('one'));
        $this->assertSame(sys_get_temp_dir(), $manager->getExecutionPath());
    }

    public function testBuildExecutionContextsCreatesFileHandlers(): void
    {
        $tempDir = sys_get_temp_dir();
        $manager = new ConfigurationManager($this->createParser());
        $manager->fromYamlArray('one', [
            'services' => ['app' => ['container_name' => 'app']],
        ], $tempDir);

        $contexts = $manager->buildExecutionContexts('one');

        $this->assertCount(1, $contexts);
        $context = $contexts[0];
        $this->assertArrayHasKey('id', $context);
        $this->assertArrayHasKey('definition', $context);
        $this->assertArrayHasKey('fileHandler', $context);
        $this->assertFileExists($context['fileHandler']->getFinalDockerComposeFilePath());

        $manager->cleanup();
    }

    public function testCleanupCopiesFilesWhenDebugDirProvided(): void
    {
        $tempDir = sys_get_temp_dir();
        $manager = new ConfigurationManager($this->createParser());
        $manager->fromYamlArray('one', [
            'services' => ['app' => ['container_name' => 'app']],
        ], $tempDir);
        $contexts = $manager->buildExecutionContexts('one');
        $filePath = $contexts[0]['fileHandler']->getFinalDockerComposeFilePath();

        $debugDir = $tempDir . DIRECTORY_SEPARATOR . 'debug-' . uniqid();
        mkdir($debugDir);
        $manager->setDebugDirectory($debugDir);

        $manager->cleanup();

        $this->assertFileExists($debugDir . DIRECTORY_SEPARATOR . basename($filePath));
        $this->assertFileDoesNotExist($filePath);

        array_map('unlink', glob($debugDir . DIRECTORY_SEPARATOR . '*') ?: []);
        rmdir($debugDir);
    }

    public function testNormalizeIds(): void
    {
        $manager = new ConfigurationManager($this->createParser());
        $manager->fromYamlArray('one', [
            'services' => ['app' => ['container_name' => 'app']],
        ], sys_get_temp_dir());

        $this->assertSame(['single'], $manager->normalizeIds('single'));
        $this->assertSame(['a', 'b'], $manager->normalizeIds(['a', 'b']));
        $this->assertSame(['one'], $manager->normalizeIds());
    }

    public function testGetExecutionPathThrowsWhenNotSet(): void
    {
        $manager = new ConfigurationManager($this->createParser());

        $this->expectException(RootDockerComposeManagerException::class);
        $manager->getExecutionPath();
    }

    public function testGetYamlParserThrowsWhenMissing(): void
    {
        $manager = new ConfigurationManager(null);

        $this->expectException(YamlParserException::class);
        $manager->getYamlParser();
    }
}
