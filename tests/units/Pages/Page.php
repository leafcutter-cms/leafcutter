<?php
namespace Leafcutter\tests\units\Pages;

use atoum;
use Leafcutter\Leafcutter;
use Leafcutter\Pages\Page as PagesPage;
use Leafcutter\URL;
use Leafcutter\URLFactory;

class Page extends atoum\test
{
    public function testConstruct()
    {
        $this->newTestedInstance(new URL('https://www.google.com/foo/bar'));
        $this->string($this->testedInstance->url()->__toString())
            ->isEqualTo('https://www.google.com/foo/bar/')
            ->boolean($this->testedInstance->dynamic())->isFalse()
            ->given($this->testedInstance->setDynamic(true))
                ->boolean($this->testedInstance->dynamic())->isTrue
                ->variable($this->testedInstance->template())->isNull()
            ->given($this->testedInstance->setTemplate('foo.twig'))
                ->string($this->testedInstance->template())->isEqualTo('foo.twig')
            ->given($this->testedInstance->setUrl(new URL('https://www.google.com/foo/baz/')))
                ->string($this->testedInstance->url()->__toString())
                    ->isEqualTo('https://www.google.com/foo/baz/')
                ->string($this->testedInstance->calledUrl()->__toString())
                    ->isEqualTo('https://www.google.com/foo/bar/')
            ->string($this->testedInstance->hash())
        ;
    }

    public function testContent()
    {
        URLFactory::beginSite('http://www.google.com/');
        $leafcutter = new \mock\Leafcutter();
        Leafcutter::beginContext($leafcutter);
        $this->given($page = new PagesPage(new URL('http://www.google.com/foo/bar.html')))
            ->string($page->rawContent())
                ->isEqualTo('No content')
            ->string($page->generateContent())
                ->contains('<html>')
                ->contains('No content')
            ->given($page->setRawContent("# Markdown\n\nPage content", 'md'))
                ->string($page->rawContent())
                    ->isEqualTo("# Markdown\n\nPage content")
                ->string($page->generateContent())
                    ->contains('<h1>')
        ;
        Leafcutter::endContext();
        URLFactory::endSite();
    }

    public function testChildren()
    {
        URLFactory::beginSite('http://www.google.com/');
        $leafcutter = new \mock\Leafcutter();
        Leafcutter::beginContext($leafcutter);
        $this->given($page = new PagesPage(new URL('http://www.google.com/foo/bar.html')))
            ->object($page->children());
        ;
        Leafcutter::endContext();
        URLFactory::endSite();
    }

    public function testBreadcrumb()
    {
        $leafcutter = new \mock\Leafcutter();
        Leafcutter::beginContext($leafcutter);
        $this->given($page = new PagesPage(new URL('http://www.google.com/foo/bar.html')))
            ->and($parent = new PagesPage(new URL('http://www.google.com/foo/')))
            ->and($page->setParent($parent))
                ->object($page->parent())
                    ->isEqualTo($parent)
                ->array($page->breadcrumb())
                    ->contains($page)
                    ->contains($parent)
                    ->hasSize(2)
            ->given($parent->setParent($page))
                ->object($parent->parent())
                    ->isEqualTo($page)
                ->array($page->breadcrumb())
                    ->contains($page)
                    ->contains($parent)
                    ->hasSize(2)
            ->given($parent->setParent('@/'))
                ->variable($parent->parent())->isNull()
                ->array($page->breadcrumb())
                    ->contains($page)
                    ->contains($parent)
                    ->hasSize(2)
        ;
        Leafcutter::endContext();
    }

    public function testMeta()
    {
        $this->given($this->newTestedInstance(new URL('https://www.google.com/foo/bar.html')))
            ->given($this->testedInstance->metaMerge(['foo'=>'bar']))
                ->string($this->testedInstance->meta('foo'))
                    ->isEqualTo('bar')
            ->given($this->testedInstance->meta('date.test','January 1, 1980, 12:00 pm MDT'))
                ->integer($this->testedInstance->meta('date.test'))
            ->given($this->testedInstance->metaMerge(['date.foo'=>'January 1, 1980, 12:00 pm MDT']))
                ->integer($this->testedInstance->meta('date.foo'))
                    ->isEqualTo($this->testedInstance->meta('date.test'))
        ;
    }

    public function testNameAndTitle()
    {
        // default untitled strings
        $this->given($this->newTestedInstance(new URL('https://www.google.com/foo/bar.html')))
            ->string($this->testedInstance->name())
                ->isEqualTo('Unnamed page')
            ->string($this->testedInstance->title())
                ->isEqualTo('Untitled page');
        // name set but not title
        $this->given($this->newTestedInstance(new URL('https://www.google.com/foo/bar.html')))
        ->and($this->testedInstance->meta('name','Named page'))
            ->string($this->testedInstance->name())
                ->isEqualTo('Named page')
            ->string($this->testedInstance->title())
                ->isEqualTo('Named page');
        // title set but not name
        $this->given($this->newTestedInstance(new URL('https://www.google.com/foo/bar.html')))
        ->and($this->testedInstance->meta('title','Titled page'))
            ->string($this->testedInstance->name())
                ->isEqualTo('Titled page')
            ->string($this->testedInstance->title())
                ->isEqualTo('Titled page');
        // name and title set
        $this->given($this->newTestedInstance(new URL('https://www.google.com/foo/bar.html')))
        ->and($this->testedInstance->meta('title','Titled page'))
        ->and($this->testedInstance->meta('name','Named page'))
            ->string($this->testedInstance->name())
                ->isEqualTo('Named page')
            ->string($this->testedInstance->title())
                ->isEqualTo('Titled page');
    }
}