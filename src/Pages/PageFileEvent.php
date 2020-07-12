<?php
namespace Leafcutter\Pages;

use Leafcutter\Leafcutter;
use Leafcutter\URL;

class PageFileEvent
{
    protected $path, $url;

    public function __construct(string $path, URL $url)
    {
        $this->path = $path;
        $this->url = $url;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function url(): URL
    {
        return clone $this->url;
    }

    public function getContents(): string
    {
        $content = file_get_contents($this->path());
        $content = Leafcutter::get()->events()->dispatchAll(
            'onPageFileContents',
            $content
        );
        return $content;
    }
}
