<?php

namespace VCR\Event;

use PHPUnit\Framework\TestCase;
use VCR\Request;
use VCR\Cassette;
use VCR\Configuration;
use VCR\Storage;
use VCR\Response;

class AfterPlaybackEventTest extends TestCase
{
    /**
     * @var AfterPlaybackEvent
     */
    private $event;

    protected function setUp(): void
    {
        $this->event = new AfterPlaybackEvent(
            new Request('GET', 'http://example.com'),
            new Response(200),
            new Cassette('test', new Configuration(), new Storage\Blackhole())
        );
    }

    public function testGetRequest(): void
    {
        $this->assertInstanceOf(Request::class, $this->event->getRequest());
    }

    public function testGetResponse(): void
    {
        $this->assertInstanceOf(Response::class, $this->event->getResponse());
    }

    public function testGetCassette(): void
    {
        $this->assertInstanceOf(Cassette::class, $this->event->getCassette());
    }
}
