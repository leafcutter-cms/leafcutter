<?php
namespace Leafcutter\Assets;

use Leafcutter\URL;

class AssetFileEvent
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
}
