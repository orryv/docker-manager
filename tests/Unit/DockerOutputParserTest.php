<?php

namespace Tests\Unit;

use Orryv\DockerComposeManager\Runtime\Cli\DockerOutputParser;
use PHPUnit\Framework\TestCase;

class DockerOutputParserTest extends TestCase
{
    public function testParseReturnsLatestBuildStatusAndContainers(): void
    {
        $parser = new DockerOutputParser();
        $output = <<<'LOG'
Network demo_network  Creating
Network demo_network  Created
#0 [1/2] RUN echo "step one"
#1 [2/2] RUN echo "step two"
Container demo-container  Starting
Container demo-container  Started
Error failed: port is already allocated
LOG;

        $parsed = $parser->parse($output);

        self::assertSame(['demo-container' => 'Started'], $parsed['containers']);
        self::assertSame(['demo_network' => 'Created'], $parsed['networks']);
        self::assertSame('#1 [2/2] RUN echo "step two"', $parsed['build_status']);
        self::assertSame(['Error failed: port is already allocated'], $parsed['errors']);
        self::assertSame('Error failed: port is already allocated', end($parsed['lines']));
    }
}
