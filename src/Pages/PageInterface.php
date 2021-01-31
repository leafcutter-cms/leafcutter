<?php
namespace Leafcutter\Pages;

use Leafcutter\Common\Collection;
use Leafcutter\URL;

interface PageInterface
{
    public function __construct(URL $url);
    public function url(): URL;
    public function calledUrl(): URL;
    public function setUrl(URL $url);
    public function rawContent(): string;
    public function generateContent(): string;
    public function setRawContent(string $content);
    public function hash(): string;
    public function children(): Collection;
    public function parent(): ?PageInterface;
    public function meta(string $key, $value = null);
    public function status(): int;
    public function setStatus(int $status);
    public function dynamic(): bool;
    public function setDynamic(bool $dynamic);
    public function public(): bool;
    public function setPublic(bool $public);
    public function headers(): array;
}
