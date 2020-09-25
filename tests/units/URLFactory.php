<?php
namespace Leafcutter\tests\units;

use atoum;
use Leafcutter\URL;
use Leafcutter\URLFactory as Factory;

class URLFactory extends atoum\test
{
    public function testNormalizeCurrent()
    {
        Factory::beginSite('https://test.domain/');
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'test.domain';
        $_SERVER['REQUEST_URI'] = '/foo/bar';
        $this->variable(Factory::normalizeCurrent())->isEqualTo('https://test.domain/foo/bar/');
        $this->variable(Factory::normalizeCurrent(new URL('https://test.domain/foo/bar')))->isEqualTo('https://test.domain/foo/bar/');
        $this->variable(Factory::normalizeCurrent(new URL('http://test.domain/foo/bar')))->isEqualTo('http://test.domain/foo/bar/');
        $this->variable(Factory::normalizeCurrent(new URL('https://test.domain/foo/bar'), false, false))->isNull();
        $this->variable(Factory::normalizeCurrent(new URL('http://test.domain/foo/bar'), false, false))->isNull();
        Factory::endSite();
    }

    public function testSiteStack()
    {
        $this->given(Factory::beginSite('https://www.google.com/foo/'))
            ->string(Factory::site()->__toString())->isEqualTo('https://www.google.com/foo/');
        $this->given(Factory::beginSite('https://www.google.com/foo/bar/'))
            ->string(Factory::site()->__toString())->isEqualTo('https://www.google.com/foo/bar/');
        $this->given(Factory::endSite())
            ->string(Factory::site()->__toString())->isEqualTo('https://www.google.com/foo/');
        $this->given(Factory::endSite())
            ->variable(Factory::site())->isNull();
    }

    public function testContextStack()
    {
        $this->given(Factory::beginSite('https://www.google.com/foo/'))
            ->string(Factory::context()->__toString())->isEqualTo('https://www.google.com/foo/')
            ->given(Factory::beginContext())
            ->string(Factory::context()->__toString())->isEqualTo('https://www.google.com/foo/')
            ->given(Factory::beginContext('@/bar/'))
            ->string(Factory::context()->__toString())
            ->isEqualTo('https://www.google.com/foo/bar/')
            ->given(Factory::beginContext('@ctx/baz/'))
            ->string(Factory::context()->__toString())
            ->isEqualTo('https://www.google.com/foo/bar/baz/')
            ->given(Factory::endContext())
            ->string(Factory::context()->__toString())
            ->isEqualTo('https://www.google.com/foo/bar/')
            ->given(Factory::endContext())
            ->string(Factory::context()->__toString())
            ->isEqualTo('https://www.google.com/foo/')
        ;
    }

    public function testCurrentActual()
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'test.domain';
        $_SERVER['REQUEST_URI'] = '/foo/bar.html';
        $this->given($url = Factory::currentActual())
            ->string($url)->isEqualTo('https://test.domain/foo/bar.html');
    }
}
