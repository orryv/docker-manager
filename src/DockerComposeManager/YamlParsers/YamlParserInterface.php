<?php 

namespace Orryv\DockerComposeManager\YamlParsers;

interface YamlParserInterface
{
    public function parse(string $yaml_content): array;
    public function build(array $data): string;
}