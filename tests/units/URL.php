<?php
namespace Leafcutter\tests\units;

use atoum;
use Leafcutter\URL as LeafcutterURL;
use Leafcutter\URLFactory;

class URL extends atoum
{
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
            ->string($url->__toString())->isEqualTo('https://www.google.com/%40ns/foo/buzz.html');
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
            ->and($url->setQuery(['foo'=>'bar']))
            ->string($url->__toString())->isEqualTo('https://www.google.com/?foo=bar');
        //end context site
        URLFactory::endSite();
    }
}
