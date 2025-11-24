<?php

namespace Tests\Unit\FileSystem;

use Orryv\DockerComposeManager\Exceptions\FileException;
use Orryv\DockerComposeManager\FileSystem\Reader;
use PHPUnit\Framework\TestCase;

class ReaderTest extends TestCase
{
    public function testReadFileReturnsContents(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'reader-test-');
        file_put_contents($tempFile, 'sample-content');

        $this->assertSame('sample-content', Reader::readFile($tempFile));

        unlink($tempFile);
    }

    public function testReadFileThrowsWhenMissing(): void
    {
        $this->expectException(FileException::class);
        Reader::readFile('/non/existent/path.yml');
    }
}
