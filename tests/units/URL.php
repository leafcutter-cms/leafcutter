<?php
namespace Leafcutter\tests\units;

use atoum;
use Leafcutter\URL as LeafcutterURL;
use Leafcutter\URLFactory;

class URL extends atoum\test
{
    public function testBase64()
    {
        $testString = 'abc!@#';
        $this->string($enc = LeafcutterURL::base64_encode($testString))
            ->string($dec = LeafcutterURL::base64_decode($enc))
            ->isEqualTo($testString)
        ;
    }

    public function testLogString()
    {
        $this->object($url = new LeafcutterURL('https://www.google.com/'))
            ->string($url->logString());
    }

    public function testConstruct()
    {
        URLFactory::beginSite("https://www.google.com/");
        $this->object($url = new LeafcutterURL("https://www.google.com/~namespace/foo/bar.html?baz=buzz&zoom=zam#fragment"))
            ->string($url->extension())->isEqualTo('html')
            ->boolean($url->inSite())->isTrue()
            ->string($url->sitePath())->isEqualTo('foo/bar.html')
            ->string($url->siteNamespace())->isEqualTo('namespace')
            ->string($url->siteFullPath())->isEqualTo('~namespace/foo/bar.html')
            ->string($url->pathFile())->isEqualTo('bar.html')
            ->string($url->pathDirectory())->isEqualTo('/~namespace/foo/')
            ->string($url->schemeString())->isEqualTo('https://')
            ->string($url->hostString())->isEqualTo('www.google.com')
            ->string($url->portString())->isEmpty()
            ->string($url->pathString())->isEqualTo('/%7Enamespace/foo/bar.html')
            ->string($url->queryString())->isEqualTo('?baz=buzz&zoom=zam')
            ->string($url->fragmentString())->isEqualTo('#fragment')
            ->string($url->scheme())->isEqualTo('https')
            ->string($url->host())->isEqualTo('www.google.com')
            ->integer($url->port())->isEqualTo(443)
            ->string($url->path())->isEqualTo('/~namespace/foo/bar.html')
            ->array($url->query())->isIdenticalTo(['baz' => 'buzz', 'zoom' => 'zam'])
            ->string($url->fragment())->isEqualTo('fragment')
        ;
        URLFactory::endSite();
    }

    public function testPartialConstructs()
    {
        URLFactory::beginSite("https://www.google.com/");
        $this->given($url = new LeafcutterURL('?foo=bar'))
            ->array($url->query())->isIdenticalTo(['foo'=>'bar']);
        $this->given($url = new LeafcutterURL('#foobar'))
            ->string($url->fragment())->isEqualTo('foobar');
        URLFactory::endSite();
    }

    public function testInSite()
    {
        URLFactory::beginSite("https://www.google.com/");
        $this->given($url = new LeafcutterURL('https://www.goggles.org/foo/bar'))
            ->boolean($url->inSite())->isFalse()
            ->variable($url->siteFullPath())->isNull()
            ->variable($url->siteNamespace())->isNull();
        URLFactory::endSite();
    }

    public function testVagueConstruct()
    {
        URLFactory::beginSite('https://www.google.com/');
        $this->object($url = new LeafcutterURL('/foo/bar.html'))
            ->string($url->scheme())->isEqualTo('https')
            ->string($url->host())->isEqualTo('www.google.com')
        ;
        URLFactory::endSite();
        // throw exceptions without a site
        $this->object($url = new LeafcutterURL('/foo/bar.html'))
            ->exception(function () use ($url) {
                $url->host();
            });
    }

    public function testSpecificConstruct()
    {
        $this->object($url = new LeafcutterURL('http://www.google.com:80/'))
            ->string($url->__toString())->isEqualTo('http://www.google.com/');
    }

    public function testPathSetters()
    {
        URLFactory::beginSite("https://www.google.com/");
        //set up a URL
        $url = new LeafcutterURL("https://www.google.com/foo/bar.html");
        //change extension
        $this->given($url->setExtension('php'))
            ->string($url->extension())->isEqualTo('php')
            ->string($url->__toString())->isEqualTo('https://www.google.com/foo/bar.php')
        ;
        //remove extension
        $this->given($url->setExtension(''))
            ->variable($url->extension())->isNull()
            ->string($url->__toString())->isEqualTo('https://www.google.com/foo/bar')
        ;
        //change whole path
        $this->given($url->setPath('/foo/baz.html'))
            ->string($url->__toString())->isEqualTo('https://www.google.com/foo/baz.html')
        ;
        $this->given($url->setPath('foo/buzz.html'))
            ->string($url->__toString())->isEqualTo('https://www.google.com/foo/buzz.html');
        //set namespace
        $this->given($url->setSiteNamespace('ns'))
            ->string($url->__toString())->isEqualTo('https://www.google.com/%7Ens/foo/buzz.html');
        //set scheme/port
        $this->given($url = new LeafcutterURL('https://www.google.com/'))
            ->and($url->setScheme('http'))
            ->string($url->__toString())->isEqualTo('http://www.google.com/');
        $this->given($url->setPort(8080))
            ->string($url->__toString())->isEqualTo('http://www.google.com:8080/');
        $this->given($url = new LeafcutterURL('http://www.google.com/'))
            ->and($url->setScheme('https'))
            ->string($url->__toString())->isEqualTo('https://www.google.com/');
        $this->given($url->setPort(8080))
            ->string($url->__toString())->isEqualTo('https://www.google.com:8080/');
        //set query
        $this->given($url = new LeafcutterURL('https://www.google.com/'))
            ->and($url->setQuery(['foo' => 'bar']))
            ->string($url->__toString())->isEqualTo('https://www.google.com/?foo=bar');
        //end context site
        URLFactory::endSite();
    }
}
