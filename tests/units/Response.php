<?php
namespace Leafcutter\tests\units;

use atoum;
use Leafcutter\URL;

class Response extends atoum\test
{
    public function testConstruct()
    {
        $this->given($this->newTestedInstance)
            ->given($url = new \mock\Leafcutter\URL('https://www.google.com/foo/bar/'))
            ->given($this->testedInstance->setURL($url))
                ->object($this->testedInstance->url())
                    ->isIdenticalTo($url)
            ->boolean($this->testedInstance->dynamic())->isFalse()
            ->given($this->testedInstance->setDynamic(true))
                ->boolean($this->testedInstance->dynamic())->isTrue()
            ->string($this->testedInstance->template())->isEqualTo('default.twig')
            ->given($this->testedInstance->setTemplate('foo.twig'))
                ->string($this->testedInstance->template())->isEqualTo('foo.twig')
            ->integer($this->testedInstance->status())->isEqualTo(200)
            ->given($this->testedInstance->setStatus(404))
                ->integer($this->testedInstance->status())->isEqualTo(404)
        ;
    }

    public function testHeaders()
    {
        // basic new instance should call http_response_code once, and header once
        $this->given($this->newTestedInstance)
            ->given($this->function->http_response_code = 200)
            ->given($this->function->header = true)
            ->given($this->testedInstance->renderHeaders())
                ->function('http_response_code')
                    ->wasCalledWithArguments(200)->once()
                ->function('header')
                    ->wasCalledWithArguments('content-type: text/html; charset=utf-8')->once();
        // setting a response code should change call, as should
        $this->given($this->newTestedInstance)
            ->given($this->function->http_response_code = 200)
            ->given($this->function->header = true)
            ->given($this->testedInstance->setStatus(300))
            ->given($this->testedInstance->setMime('text/plain'))
            ->given($this->testedInstance->setCharset('utf-16'))
            ->given($this->testedInstance->header('foo','bar'))
            ->given($this->testedInstance->renderHeaders())
                ->function('http_response_code')
                    ->wasCalledWithArguments(300)->once()
                ->function('header')
                    ->wasCalledWithArguments('content-type: text/plain; charset=utf-16')->once()
                ->function('header')
                    ->wasCalledWithArguments('foo: bar')->once();
    }

    public function testRedirect()
    {
        $this->given($this->newTestedInstance)
            ->given($this->function->http_response_code = 200)
            ->given($this->function->header = true)
            ->given($this->testedInstance->redirect('http://www.google.com'))
            ->given($this->testedInstance->renderHeaders())
                ->function('http_response_code')
                    ->wasCalledWithArguments(307)->once()
                ->function('header')
                    ->wasCalledWithArguments('Location: http://www.google.com')->once();
    }

    public function testSource()
    {
        $this->given($this->newTestedInstance)
            ->given($src = new class(){
                public function dynamic() {
                    return false;
                }
                public function template() {
                    return 'foo.twig';
                }
                public function url() {
                    return new URL('https://www.google.com/');
                }
            })
            ->given($this->testedInstance->setSource($src))
                ->object($this->testedInstance->source())
                ->variable($this->testedInstance->page())->isNull()
                ->boolean($this->testedInstance->dynamic())->isFalse()
                ->string($this->testedInstance->template())->isEqualTo('foo.twig')
                ->object($this->testedInstance->url())->isEqualTo(new URL('https://www.google.com'));
    }

    public function testPageSource()
    {
        $this->given($this->newTestedInstance)
            ->given($url = new \mock\Leafcutter\URL('https://www.google.com/'))
            ->given($page = new \mock\Leafcutter\Pages\PageInterface($url, 'page content'))
                ->given($this->calling($page)->url = $url)
            ->given($this->testedInstance->setSource($page))
                ->object($this->testedInstance->page())->isEqualTo($page)
                ->object($this->testedInstance->source())->isEqualTo($page)
                ->object($this->testedInstance->url())->isEqualTo($url);
    }

    public function testRender()
    {
        $do_after_count = 0;
        $fastcgi_finish_request = true;
        $this->newTestedInstance();
        $this->testedInstance->doAfter(function() use(&$do_after_count) {
            $do_after_count++;
        });
        $this->testedInstance->setContent('response content');
        $this->function->ignore_user_abort = true;
        $this->function->function_exists = function($fn) use($fastcgi_finish_request) {
            if ($fn == '\fastcgi_finish_request') {
                return $fastcgi_finish_request;
            }else {
                return \function_exists($fn);
            }
        };
        // test with fastcgi_finish_request available
        $this->given($fastcgi_finish_request = true)
            ->and($this->function->fastcgi_finish_request = true)
            ->and($this->function->http_response_code = 200)
            ->and($this->function->header = true)
            ->and($this->function->ignore_user_abort = false)
            ->output(function(){$this->testedInstance->renderContent();})
                ->isEqualTo('response content')
                ->function('ignore_user_abort')
                    ->wasCalled()->once()
                ->function('fastcgi_finish_request')
                    ->wasCalled()->once()
                ->integer($do_after_count)->isEqualTo(1);
    }
}