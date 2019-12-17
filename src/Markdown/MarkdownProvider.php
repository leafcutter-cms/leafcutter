<?php
namespace Leafcutter\Markdown;

use \ParsedownExtra;

class MarkdownProvider
{
    protected $parser;

    public function parse(string $markdown) : string
    {
        return $this->parser()->text($markdown);
    }

    protected function parser()
    {
        if (!isset($this->parser)) {
            $this->parser = new ParsedownExtra;
        }
        return $this->parser;
    }
}
