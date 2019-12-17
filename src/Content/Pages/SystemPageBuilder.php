<?php
namespace Leafcutter\Content\Pages;

use Leafcutter\Leafcutter;

/**
 * This class holds the event hooks for building basic built-in page types
 */
class SystemPageBuilder
{
    protected $leafcutter;

    public function onPageFile_md($p) : ?PageInterface
    {
        list($url, $file) = $p;
        $content = file_get_contents($file);
        $page = new Page($content, $url);
        if ($page->getName() == 'Untitled page' && preg_match('@#(.+)@', $content, $matches)) {
            $page->setName(trim(strip_tags($matches[1])));
        }
        $page->setContent(function () use ($content, $page) {
            $this->leafcutter->logger()->debug('SystemPageBuilder: compiling content for '.$page->getUrl());
            $content = $this->leafcutter->templates()->execute($content, ['page'=>$page]);
            $content = $this->leafcutter->markdown()->parse($content);
            return $content;
        });
        return $page;
    }

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
    }
}
