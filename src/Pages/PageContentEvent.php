<?php
namespace Leafcutter\Pages;

class PageContentEvent
{
    protected $page, $content;

    public function __construct(Page $page, string $content)
    {
        $this->page = $page;
        $this->content = $content;
    }

    public function setContent(string $content)
    {
        $this->content = $content;
    }

    public function page(): Page
    {
        return $this->page;
    }

    public function content(): string
    {
        return $this->content;
    }
}
