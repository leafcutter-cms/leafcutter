<?php
namespace Leafcutter\Pages;

use Flatrr\SelfReferencingFlatArray;
use Leafcutter\Common\Collection;
use Leafcutter\Leafcutter;
use Leafcutter\URL;

class Page implements PageInterface
{
    protected $content, $url, $meta;
    protected $dynamic = false;
    protected $template = 'default.twig';

    public function __construct(URL $url, string $content)
    {
        // normalize trailing slashes/.html
        if ($url->siteFullPath() != 'favicon.ico' && !preg_match('@(/|\.html)$@', $url->siteFullPath())) {
            $url->setPath($url->siteFullPath() . '/');
        }
        $this->url = $url;
        $this->content = $content;
        $this->meta = new SelfReferencingFlatArray([
            'date.generated' => time(),
        ]);
    }

    public function dynamic(): bool
    {
        return $this->dynamic;
    }

    public function setDynamic(bool $dynamic)
    {
        $this->dynamic = $dynamic;
    }

    public function template(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template)
    {
        $this->template = $template;
    }

    public function name(): string
    {
        if ($this->meta('name')) {
            return $this->meta('name');
        }
        if ($this->meta('title')) {
            return $this->meta('title');
        }
        if (preg_match('@<h1>(.+?)</h1>@im', $this->content(), $matches)) {
            return trim($matches[1]);
        }
        return 'Unnamed page';
    }

    public function title(): string
    {
        if ($this->meta('title')) {
            return $this->meta('title');
        }
        if ($this->meta('name')) {
            return $this->meta('name');
        }
        if (preg_match('@<h1>(.+?)</h1>@im', $this->content(), $matches)) {
            return trim($matches[1]);
        }
        return 'Untitled page';
    }

    public function meta(string $key, $value = null)
    {
        if ($value !== null) {
            $this->meta[$key] = $value;
            if (substr($key, 0, 5) == 'date.') {
                foreach ($this->meta['date'] as $k => $v) {
                    $this->meta["date.$k"] = intval($v) ?? strtotime($v);
                }
            }
        }
        return $this->meta[$key];
    }

    public function url(): URL
    {
        return clone $this->url;
    }

    public function setUrl(URL $url)
    {
        $this->url = clone $url;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function setContent(string $content)
    {
        $this->content = $content;
    }

    public function hash(): string
    {
        return hash('crc32', serialize([
            $this->content(), $this->url(),
        ]));
    }

    public function children(): Collection
    {
        return Leafcutter::get()->pages()->children($this->url());
    }

    public function parent(): ?PageInterface
    {
        return Leafcutter::get()->pages()->parent($this->url());
    }
}
