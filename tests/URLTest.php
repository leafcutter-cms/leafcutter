<?php
declare (strict_types = 1);
namespace Leafcutter;

use PHPUnit\Framework\TestCase;

final class URLTest extends TestCase
{
    public function testConstruct()
    {
        $url = new URL('https://test.com/foo/bar.html');
        $this->assertEquals('https://test.com/foo/bar.html', $url->__toString());
        $url = new URL('http://test.com/foo/bar.html');
        $this->assertEquals('http://test.com/foo/bar.html', $url->__toString());
        $url = new URL('https://test.com:80/foo/bar.html');
        $this->assertEquals('https://test.com:80/foo/bar.html', $url->__toString());
        $url = new URL('http://test.com:443/foo/bar.html');
        $this->assertEquals('http://test.com:443/foo/bar.html', $url->__toString());
    }
}
