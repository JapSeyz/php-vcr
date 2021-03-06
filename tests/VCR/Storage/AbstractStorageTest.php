<?php

namespace VCR\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\VCRException;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class AbstractStorageTest extends TestCase
{
    protected $handle;
    protected $filePath;
    protected $storage;

    public function testFilePathCreated(): void
    {
        $fs = vfsStream::setup('test');

        $this->storage = new TestStorage(vfsStream::url('test/'), 'file');
        $this->assertTrue($fs->hasChild('file'));

        $this->storage = new TestStorage(vfsStream::url('test/'), 'folder/file');
        $this->assertTrue($fs->hasChild('folder'));
        $this->assertTrue($fs->getChild('folder')->hasChild('file'));
    }

    public function testRootNotExisting(): void
    {
        $this->expectException(VCRException::class);
        $this->expectExceptionMessage("Cassette path 'vfs://test/foo' is not existing or not a directory");

        vfsStream::setup('test');
        new TestStorage(vfsStream::url('test/foo'), 'file');
    }
}

class TestStorage extends AbstractStorage
{
    private $recording;

    public function storeRecording(Recording $recording): void
    {
        $this->recording = $recording;
    }

    public function next()
    {
        $this->position = key($this->recording);
        $this->current = current($this->recording);
        next($recording);

        return $this->current;
    }

    public function valid(): bool
    {
        return (boolean) $this->position;
    }

    public function rewind(): void
    {
        reset($this->recording);
    }
}
