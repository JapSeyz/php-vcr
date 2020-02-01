<?php

namespace VCR\Util;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Exceptions\InvalidHostException;
use VCR\Request;
use VCR\Response;
use VCR\VCRException;

class CurlHelperTest extends TestCase
{
    /**
     * @dataProvider getHttpMethodsProvider()
     */
    public function testSetCurlOptionMethods(string $method): void
    {
        $request = new Request($method, 'http://example.com');
        $headers = ['Host: example.com'];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals($method, $request->getMethod());
    }

    /**
     * Returns a list of HTTP methods for testing testSetCurlOptionMethods.
     *
     * @return array HTTP methods.
     */
    public function getHttpMethodsProvider(): array
    {
        return [
            ['CONNECT'],
            ['DELETE'],
            ['GET'],
            ['HEAD'],
            ['OPTIONS'],
            ['POST'],
            ['PUT'],
            ['TRACE'],
        ];
    }

    public function testSetCurlOptionOnRequestPostFieldsQueryString(): void
    {
        $request = new Request('POST', 'http://example.com');
        $payload = 'para1=val1&para2=val2';

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals($payload, (string) $request->getBody());
    }

    public function testSetCurlOptionOnRequestPostFieldsArray(): void
    {
        $request = new Request('POST', 'http://example.com');
        $payload = ['some' => 'test'];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertNull($request->getBody());
        $this->assertEquals($payload, $request->getPostFields());
    }

    public function testSetCurlOptionOnRequestPostFieldsString(): void
    {
        $request = new Request('POST', 'http://example.com');
        $payload = json_encode(['some' => 'test']);

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals($payload, (string) $request->getBody());
    }

    public function testSetCurlOptionOnRequestPostFieldsEmptyString(): void
    {
        $request = new Request('POST', 'http://example.com');
        $payload = '';

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        // This is consistent with how requests are read out of storage using
        // \VCR\Request::fromArray(array $request).
        $this->assertNull($request->getBody());
    }

    public function testSetCurlOptionOnRequestSetSingleHeader(): void
    {
        $request = new Request('GET', 'http://example.com');
        $headers = ['Host: example.com'];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals(['Host' => 'example.com'], $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestSetSingleHeaderTwice(): void
    {
        $request = new Request('GET', 'http://example.com');
        $headers = ['Host: example.com'];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals(['Host' => 'example.com'], $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestSetMultipleHeadersTwice(): void
    {
        $request = new Request('GET', 'http://example.com');
        $headers = [
            'Host: example.com',
            'Content-Type: application/json',
        ];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $expected = [
            'Host' => 'example.com',
            'Content-Type' => 'application/json'
        ];
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestEmptyPostFieldsRemovesContentType(): void
    {
        $request = new Request('GET', 'http://example.com');
        $headers = [
            'Host: example.com',
            'Content-Type: application/json',
        ];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, []);

        $expected = [
            'Host' => 'example.com',
        ];
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestPostFieldsSetsPostMethod(): void
    {
        $request = new Request('GET', 'http://example.com');
        $payload = json_encode(['some' => 'test']);

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals('POST', $request->getMethod());
    }

    public function testSetCurlOptionReadFunctionToNull(): void
    {
        $request = new Request('POST', 'http://example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_READFUNCTION, null, curl_init());

        $this->assertNull($request->getCurlOption(CURLOPT_READFUNCTION));
    }

    public function testInvalidHostException(): void
    {
        $this->expectException(InvalidHostException::class, 'URL must be valid.');
        new Request('POST', 'example.com');
    }

    public function testSetCurlOptionReadFunctionMissingSize(): void
    {
        $this->expectException(VCRException::class, 'To set a CURLOPT_READFUNCTION, CURLOPT_INFILESIZE must be set.');
        $request = new Request('POST', 'http://example.com');

        $callback = static function ($curlHandle, $fileHandle, $size) {
        };

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_READFUNCTION, $callback, curl_init());
        CurlHelper::validateCurlPOSTBody($request, curl_init());
    }

    public function testSetCurlOptionReadFunction(): void
    {
        $expected = 'test body';
        $request = new Request('POST', 'http://example.com');

        $test = $this;
        $callback = static function ($curlHandle, $fileHandle, $size) use ($test, $expected) {
            $test->assertIsResource($curlHandle);
            $test->assertIsResource($fileHandle);
            $test->assertEquals(strlen($expected), $size);

            return $expected;
        };

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_INFILESIZE, strlen($expected));
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_READFUNCTION, $callback, curl_init());
        CurlHelper::validateCurlPOSTBody($request, curl_init());

        $this->assertEquals($expected, $request->getBody());
    }

    public function testHandleResponseReturnsBody(): void
    {
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true
        ];
        $response = new Response(200, [], 'example response');

        $output = CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals($response->getBody(true), $output);
    }

    public function testHandleResponseEchosBody(): void
    {
        $response = new Response(200, [], 'example response');

        ob_start();
        CurlHelper::handleOutput($response, [], curl_init());
        $output = ob_get_clean();

        $this->assertEquals($response->getBody(true), $output);
    }

    public function testHandleResponseIncludesHeader(): void
    {
        $curlOptions = [
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
        ];
        $status = [
            'code' => 200,
            'message' => 'OK',
            'http_version' => '1.1'
        ];
        $response = new Response($status, [], 'example response');

        $output = CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals("HTTP/1.1 200 OK\r\n\r\n" . $response->getBody(), $output);
    }

    public function testHandleOutputHeaderFunction(): void
    {
        $actualHeaders = [];
        $curlOptions = [
            CURLOPT_HEADERFUNCTION => static function ($ch, $header) use (&$actualHeaders) {
                $actualHeaders[] = $header;
            },
        ];
        $status = [
            'code' => 200,
            'message' => 'OK',
            'http_version' => '1.1',
        ];
        $headers = [
            'Content-Length' => 0,
        ];
        $response = new Response($status, $headers, 'example response');
        ob_start();
        CurlHelper::handleOutput($response, $curlOptions, curl_init());
        ob_end_clean();

        $expected = [
            'HTTP/1.1 200 OK',
            'Content-Length: 0',
            ''
        ];
        $this->assertEquals($expected, $actualHeaders);
    }

    public function testHandleResponseUsesWriteFunction(): void
    {
        $test = $this;
        $expectedCh = curl_init();
        $expectedBody = 'example response';
        $curlOptions = [
            CURLOPT_WRITEFUNCTION => static function ($ch, $body) use ($test, $expectedCh, $expectedBody) {
                $test->assertEquals($expectedCh, $ch);
                $test->assertEquals($expectedBody, $body);

                return strlen($body);
            }
        ];
        $response = new Response(200, [], $expectedBody);

        CurlHelper::handleOutput($response, $curlOptions, $expectedCh);
    }

    public function testHandleResponseWritesFile(): void
    {
        vfsStream::setup('test');
        $expectedBody = 'example response';
        $testFile = vfsStream::url('test/write_file');

        $curlOptions = [
            CURLOPT_FILE => fopen($testFile, 'w+')
        ];

        $response = new Response(200, [], $expectedBody);

        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals($expectedBody, file_get_contents($testFile));
    }

    /**
     * @dataProvider getCurlOptionProvider()
     *
     * @param Response $response
     * @param integer $curlOption cURL option to get.
     * @param mixed $expectedCurlOptionValue Expected value of cURL option
     */
    public function testGetCurlOptionFromResponse(Response $response, $curlOption, $expectedCurlOptionValue): void
    {
        $this->assertEquals(
            $expectedCurlOptionValue,
            CurlHelper::getCurlOptionFromResponse($response, $curlOption)
        );
    }

    public function getCurlOptionProvider(): array
    {
        return [
            [
                Response::fromArray(
                    [
                        'status'    => [
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ],
                        'headers'   => [
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',

                        ],
                    ]
                ),
                CURLINFO_HEADER_SIZE,
                100,
            ],
            [
                Response::fromArray(
                    [
                        'status'    => [
                            'http_version' => '1.1',
                            'code' => 404,
                            'message' => 'Not Found',
                        ],
                        'headers'   => [
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',

                        ],
                    ]
                ),
                CURLINFO_HEADER_SIZE,
                107,
            ],
            [
                Response::fromArray(
                    [
                        'status'    => [
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ],
                        'headers'   => [
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',
                            'X-Powered-By' => 'PHP/5.6.4-4ubuntu6',
                        ],
                    ]
                ),
                CURLINFO_HEADER_SIZE,
                134,
            ],
            [
                Response::fromArray(
                    [
                        'status'    => [
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ],
                        'headers'   => [
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',
                            'Cache-Control' => 'no-cache, must-revalidate',
                            'Pragma' => 'no-cache',
                        ],
                    ]
                ),
                CURLINFO_HEADER_SIZE,
                160,
            ],
            [
                Response::fromArray(
                    [
                        'status'    => [
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ],
                        'headers'   => [
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'X-Powered-By' => 'PHP/5.6.4-4ubuntu6',
                            'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
                            'Last-Modified' => 'Sat, 13 Jun 2015 20:36:15 GMT',
                            'Cache-Control' => 'no-store, no-cache, must-revalidate',
                            'Pragma' => 'no-cache',
                            'Content-type' => 'text/html; charset=UTF-8',
                        ],
                    ]
                ),
                CURLINFO_HEADER_SIZE,
                290,
            ],
        ];
    }

    public function testSetCurlOptionCustomRequest(): void
    {
        $request = new Request('POST', 'http://example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_CUSTOMREQUEST, 'PUT');

        $this->assertEquals('PUT', $request->getCurlOption(CURLOPT_CUSTOMREQUEST));
    }

    public function testCurlCustomRequestAlwaysOverridesMethod(): void
    {
        $request = new Request('POST', 'http://example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_CUSTOMREQUEST, 'DELETE');

        $this->assertEquals('DELETE', $request->getMethod());

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, ['some' => 'test']);

        $this->assertEquals('DELETE', $request->getMethod());
    }
}
