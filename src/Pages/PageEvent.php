<?php
namespace Leafcutter\Pages;

use Leafcutter\URL;

class PageEvent
{
    protected $page, $url;

    public function __construct(Page $page, URL $url)
    {
        $this->page = $page;
        $this->url = $url;
    }

    public function setPage(Page $page)
    {
        $this->page = $page;
    }

    public function page(): Page
    {
        return $this->page;
    }

    public function url(): URL
    {
        return clone $this->url;
    }
}
