<?php
namespace Leafcutter\Indexer;

use Leafcutter\URL;

class IndexURL
{
    protected $url, $count, $index;

    public function __construct(string $url, int $count, AbstractIndex $index)
    {
        $this->url = $url;
        $this->count = $count;
        $this->index = $index;
    }

    public function url(): URL
    {
        return new URL('@/' . $this->url);
    }

    public function urlString(): string
    {
        return $this->url;
    }

    public function count(): int
    {
        return $this->count;
    }
}
