<?php

use Ekok\App\Http;
use Ekok\App\HttpException;

class HttpTest extends \Codeception\Test\Unit
{
    public function testStatusText()
    {
        $this->assertSame(200, Http::OK);
        $this->assertSame(511, Http::NETWORK_AUTHENTICATION_REQUIRED);
        $this->assertSame('OK', Http::statusText(200, true, $exists));
        $this->assertTrue($exists);

        // status exception
        $this->expectException('LogicException');
        $this->expectExceptionMessage('Unsupported HTTP code: 999');

        Http::statusText(999, false);
    }

    public function testParseHeader()
    {
        $accept = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $actual = Http::parseHeader($accept);
        $expected = array(
            array('content' => 'text/html'),
            array('content' => 'application/xhtml+xml'),
            array('content' => 'application/xml', 'q' => 0.9),
            array('content' => '*/*', 'q' => 0.8),
        );

        $this->assertSame($expected, $actual);
    }

    public function testStamp()
    {
        $tz = new \DateTimeZone('GMT');
        $fmt = \DateTimeInterface::RFC7231;
        $now = new \DateTime('now', $tz);
        $yes = new \DateTime('yesterday', $tz);
        $tom = new \DateTime('tomorrow', $tz);
        $t1 = new \DateTime('+2 hours', $tz);
        $t2 = new \DateTime('-2 hours', $tz);

        $this->assertSame($now->format($fmt), Http::stamp($now));
        $this->assertSame($yes->format($fmt), Http::stamp($yes));
        $this->assertSame($tom->format($fmt), Http::stamp($tom));
        $this->assertSame($tom->format($fmt), Http::stamp('tomorrow'));

        $this->assertSame($t1->format($fmt), Http::stamp($t1->getTimestamp(), null, $diff));
        $this->assertSame(7200, $diff);

        $this->assertSame($t2->format($fmt), Http::stamp($t2->getTimestamp(), null, $diff));
        $this->assertSame(-7200, $diff);

        $this->assertSame($t2->format($fmt), Http::stamp(-7200, null, $diff));
        $this->assertSame(-7200, $diff);
    }

    public function testError()
    {
        $e500 = Http::error();
        $e404 = Http::errorNotFound();
        $eFallback = Http::errorOK();

        $this->assertInstanceOf(HttpException::class, $e500);
        $this->assertInstanceOf(HttpException::class, $e404);
        $this->assertInstanceOf(HttpException::class, $eFallback);
    }
}
