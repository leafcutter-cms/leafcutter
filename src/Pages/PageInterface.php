<?php
namespace Leafcutter\Pages;

use Leafcutter\Common\Collection;
use Leafcutter\URL;

interface PageInterface
{
    public function __construct(URL $url, string $content);
    public function url(): URL;
    public function setUrl(URL $url);
    public function content(): string;
    public function setContent(string $content);
    public function hash(): string;
    public function children(): Collection;
    public function parent(): ?PageInterface;
    public function meta(string $key, $value = null);
}
