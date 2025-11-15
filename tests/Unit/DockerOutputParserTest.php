<?php

declare(strict_types=1);

namespace Tests\Unit;

use Orryv\DockerManager\Parser\DockerOutputParser;
use PHPUnit\Framework\TestCase;

class DockerOutputParserTest extends TestCase
{
    public function testParsesNetworksContainersAndErrors(): void
    {
        $log = <<<LOG
Network demo-net  Created
Container demo-web  Started
#1 [1/3] Building
Error response from daemon: driver failed programming external connectivity on endpoint demo-web
LOG;

        $parser = new DockerOutputParser();
        $result = $parser->parse($log);

        $this->assertSame(['demo-net' => 'Created'], $result['networks']);
        $this->assertSame(['demo-web' => 'Started'], $result['containers']);
        $this->assertSame('#1 [1/3] Building', $result['build_status']);
        $this->assertNotEmpty($result['errors']);
    }
}
