<?php

namespace VCR\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class YamlTest extends TestCase
{
    private $filePath;
    private $yamlObject;

    public function setUp(): void
    {
        vfsStream::setup('test');
        $this->filePath = vfsStream::url('test/') . DIRECTORY_SEPARATOR . 'yaml_test';
        $this->yamlObject = new Yaml(vfsStream::url('test/'), 'yaml_test');
    }

    public function testIterateOneObject(): void
    {
        $this->iterateAndTest(
            '-' . "\n"
            . '    para1: val1',
            [
                new Recording(['para1' => 'val1']),
            ],
            'Single yaml object was not parsed correctly.'
        );
    }

    public function testIterateTwoObjects(): void
    {
        $this->iterateAndTest(
            '-' . "\n"
            . '    para1: val1' . "\n"
            . '-' . "\n"
            . '   para2: val2',
            [
                new Recording(['para1' => 'val1']),
                new Recording(['para2' => 'val2']),
            ],
            'Two yaml objects were not parsed correctly.'
        );
    }

    public function testIterateFirstNestedObject(): void
    {
        $this->iterateAndTest(
            '-' . "\n"
            . '    para1:' . "\n"
            . '        para2: val2' . "\n"
            . '-' . "\n"
            . '    para3: val3',
            [
                new Recording(['para1' => ['para2' => 'val2']]),
                new Recording(['para3' => 'val3']),
            ],
            'Nested yaml objects were not parsed correctly.'
        );
    }

    public function testIterateSecondNestedObject(): void
    {
        $this->iterateAndTest(
            '-' . "\n"
            . '    para1: val1' . "\n"
            . '-' . "\n"
            . '    para2:' . "\n"
            . '        para3: val3' . "\n",
            [
                new Recording(['para1' => 'val1']),
                new Recording(['para2' => ['para3' => 'val3']]),
            ],
            'Nested yaml objects were not parsed correctly.'
        );
    }

    public function testIterateEmpty(): void
    {
        $this->iterateAndTest(
            '',
            [],
            'Empty yaml was not parsed correctly.'
        );
    }

    public function testStoreRecording(): void
    {
        $request = ['some request'];
        $response = ['some response'];
        $recording = Recording::fromRequestAndResponseArray($request, $response);
        $expected = $recording;

        $this->yamlObject->storeRecording($recording);

        $actual = [];
        foreach ($this->yamlObject as $recording) {
            $actual[] = $recording;
        }

        $this->assertEquals($expected, $actual[0], 'Storing and reading a recording failed.');
    }

    public function testStoreTwoRecording(): void
    {
        $request = ['headers' => ['Content-Type' => 'application/json']];
        $response = ['body' => 'ok', 'status' => 200];
        $recording = Recording::fromRequestAndResponseArray($request, $response);
        $expected = $recording;

        $this->yamlObject->storeRecording($recording);
        $this->yamlObject->storeRecording($recording);

        $actual = [];
        foreach ($this->yamlObject as $recording) {
            $actual[] = $recording;
        }
        $this->assertCount(2, $actual, 'More that two recordings stores.');
        $this->assertEquals($expected, $actual[0], 'Storing and reading first recording failed.');
        $this->assertEquals($expected, $actual[1], 'Storing and reading second recording failed.');
    }

    private function iterateAndTest($yaml, $expected, $message): void
    {
        file_put_contents($this->filePath, $yaml);

        $actual = [];
        foreach ($this->yamlObject as $object) {
            $actual[] = $object;
        }

        $this->assertEquals($expected, $actual, $message);
    }
}
