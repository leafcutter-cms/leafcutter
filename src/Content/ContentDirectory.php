<?php
namespace Leafcutter\Content;

use Leafcutter\URL;

class ContentDirectory
{
    protected $path, $url;

    public function __construct(string $path, string $url)
    {
        $this->path = $path;
        $this->url = new URL($url);
    }

    public function name(): string
    {
        return basename($this->path);
    }

    public function url(): URL
    {
        return $this->url;
    }

    public function extension(): ?string
    {
        return null;
    }

    public function path(): string
    {
        return $this->path;
    }
}
