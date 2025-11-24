<?php

namespace Tests\Unit\FileSystem;

use Orryv\DockerComposeManager\Exceptions\FileException;
use Orryv\DockerComposeManager\FileSystem\Writer;
use PHPUnit\Framework\TestCase;

class WriterTest extends TestCase
{
    public function testOverwriteCreatesFileWithContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'writer-test-');
        unlink($tempFile);

        $result = Writer::overwrite($tempFile, 'new-content');

        $this->assertTrue($result);
        $this->assertFileExists($tempFile);
        $this->assertSame('new-content', file_get_contents($tempFile));

        unlink($tempFile);
    }

    public function testRemoveDeletesExistingFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'writer-test-');
        file_put_contents($tempFile, 'content');

        $this->assertTrue(Writer::remove($tempFile));
        $this->assertFileDoesNotExist($tempFile);
    }

    public function testRemoveThrowsWhenFileMissing(): void
    {
        $this->expectException(FileException::class);
        Writer::remove('/path/that/does/not/exist');
    }
}
