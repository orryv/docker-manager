<?php

namespace Tests\Unit\Validation;

use Orryv\DockerComposeManager\Exceptions\YamlException;
use Orryv\DockerComposeManager\Validation\DockerComposeValidator;
use PHPUnit\Framework\TestCase;

class DockerComposeValidatorTest extends TestCase
{
    public function testValidateThrowsWhenVersionPresent(): void
    {
        $this->expectException(YamlException::class);
        DockerComposeValidator::validate([
            'version' => '3',
            'services' => ['app' => ['container_name' => 'app']]
        ]);
    }

    public function testValidateThrowsWhenServicesMissing(): void
    {
        $this->expectException(YamlException::class);
        DockerComposeValidator::validate([]);
    }

    public function testValidateThrowsWhenServiceNotArray(): void
    {
        $this->expectException(YamlException::class);
        DockerComposeValidator::validate([
            'services' => ['app' => 'not-array']
        ]);
    }

    public function testValidateThrowsWhenContainerNameMissing(): void
    {
        $this->expectException(YamlException::class);
        DockerComposeValidator::validate([
            'services' => ['app' => []]
        ]);
    }

    public function testValidatePassesForValidCompose(): void
    {
        $valid = [
            'services' => [
                'app' => [
                    'container_name' => 'app-container',
                ],
            ],
        ];

        $this->assertNull(DockerComposeValidator::validate($valid));
    }
}
