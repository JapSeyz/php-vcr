<?php

namespace VCR\Util;

use PHPUnit\Framework\TestCase;
use VCR\Request;

class StreamHelperTest extends TestCase
{
    public function streamContexts(): array
    {
        $test = $this;

        return [
            'header' => [
                ['header' => 'Content-Type: application/json'],
                static function (Request $request) use ($test) {
                    $test->assertEquals('application/json', $request->getHeader('Content-Type'));
                }
            ],

            'header with trailing newline' => [
                ['header' => "Content-Type: application/json\r\n"],
                static function (Request $request) use ($test) {
                    $test->assertEquals('application/json', $request->getHeader('Content-Type'));
                }
            ],

            'multiple headers' => [
                ['header' => "Content-Type: application/json\r\nContent-Length: 123"],
                static function (Request $request) use ($test) {
                    $test->assertEquals('application/json', $request->getHeader('Content-Type'));
                    $test->assertEquals('123', $request->getHeader('Content-Length'));
                }
            ],

            'user_agent' => [
                ['user_agent' => 'example'],
                static function (Request $request) use ($test) {
                    $test->assertEquals('example', $request->getHeader('User-Agent'));
                }
            ],

            'content' => [
                ['content' => 'example'],
                static function (Request $request) use ($test) {
                    $test->assertEquals('example', $request->getBody());
                }
            ],

            'follow_location' => [
                ['follow_location' => '0'],
                static function (Request $request) use ($test) {
                    $test->assertEquals(false, $request->getCurlOption(CURLOPT_FOLLOWLOCATION));
                }
            ],

            'max_redirects' => [
                ['max_redirects' => '2'],
                static function (Request $request) use ($test) {
                    $test->assertEquals('2', $request->getCurlOption(CURLOPT_MAXREDIRS));
                }
            ],

            'timeout' => [
                ['timeout' => '100'],
                static function (Request $request) use ($test) {
                    $test->assertEquals('100', $request->getCurlOption(CURLOPT_TIMEOUT));
                }
            ]
        ];
    }

    /**
     * @dataProvider streamContexts
     * @param array $context
     * @param callable $testCallback
     */
    public function testStreamHttpContext(array $context, callable $testCallback): void
    {
        $context = stream_context_create([
            'http' => $context
        ]);

        $request = StreamHelper::createRequestFromStreamContext($context, 'http://example.com');
        $testCallback($request);
    }
}
