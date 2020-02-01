<?php
namespace Leafcutter\Content;

use Leafcutter\URL;

class ContentFile
{
    protected $path, $url;

    public function __construct(string $path, string $url)
    {
        $this->path = $path;
        $this->url = new URL($url);
    }

    public function name(): string
    {
        return basename($this->path());
    }

    public function url(): URL
    {
        return $this->url;
    }

    public function extension(): ?string
    {
        if (preg_match('@\.([a-z0-9]+)$@', $this->name(), $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function content(): string
    {
        return file_get_contents($this->path());
    }
}
