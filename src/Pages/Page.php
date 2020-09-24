<?php
namespace Leafcutter\Pages;

use Flatrr\SelfReferencingFlatArray;
use Leafcutter\Common\Collection;
use Leafcutter\Leafcutter;
use Leafcutter\URL;
use Leafcutter\URLFactory;

class Page implements PageInterface
{
    protected $rawContent = 'No content';
    protected $rawContentType;
    protected $generatedContent;
    protected $url;
    protected $meta;
    protected $dynamic = false;
    protected $parent;
    protected $status = 200;

    public function __construct(URL $url)
    {
        // normalize trailing slashes/.html
        if ($url->path() != 'favicon.ico' && !preg_match('@(/|\.html)$@', $url->path())) {
            $url->setPath($url->path() . '/');
        }
        $this->url = $url;
        $this->calledUrl = $url;
        $this->meta = new SelfReferencingFlatArray([
            'date.generated' => time(),
            'unlisted' => false,
        ]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function setStatus(int $status)
    {
        $this->status = $status;
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
        return $this->meta('template');
    }

    public function setTemplate(?string $template)
    {
        $this->meta('template', $template);
    }

    public function name(): string
    {
        if ($this->meta('name')) {
            return $this->meta('name');
        }
        if ($this->meta('title')) {
            return $this->meta('title');
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
        return 'Untitled page';
    }

    public function meta(string $key, $value = null)
    {
        if ($value !== null) {
            $this->meta[$key] = $value;
            if (substr($key, 0, 5) == 'date.') {
                $this->parseDatesInMeta();
            }
        }
        return $this->meta[$key];
    }

    public function metaMerge(array $meta, $overwrite = false)
    {
        $this->meta->merge($meta, null, $overwrite);
        $this->parseDatesInMeta();
    }

    protected function parseDatesInMeta()
    {
        foreach ($this->meta['date'] as $k => $v) {
            $this->meta["date.$k"] = is_int($v) ? intval($v) : strtotime($v);
        }
    }

    public function url(): URL
    {
        return clone $this->url;
    }

    public function calledUrl(): URL
    {
        return clone $this->calledUrl;
    }

    public function setUrl(URL $url)
    {
        $this->url = clone $url;
    }

    public function setRawContent(string $content, string $type = null)
    {
        $event = new PageContentEvent($this, $content);
        Leafcutter::get()->events()->dispatchEvent(
            'onPageSetRawContent',
            $event
        );
        if ($type) {
            Leafcutter::get()->events()->dispatchEvent(
                'onPageSetRawContent_' . $type,
                $event
            );
        }
        $this->rawContent = $event->content();
        $this->rawContentType = $type;
        $this->generatedContent = null;
    }

    public function rawContent(): string
    {
        return $this->rawContent;
    }

    protected function rawContentForGeneration(): string
    {
        return $this->rawContent();
    }

    public function generateContent(): string
    {
        if ($this->generatedContent === null) {
            URLFactory::beginContext($this->calledUrl());
            $event = new PageContentEvent($this, $this->rawContentForGeneration());
            Leafcutter::get()->events()->dispatchEvent(
                'onPageGenerateContent_raw',
                $event
            );
            if ($this->rawContentType) {
                Leafcutter::get()->events()->dispatchEvent(
                    'onPageGenerateContent_raw_' . $this->rawContentType,
                    $event
                );
            }
            Leafcutter::get()->events()->dispatchEvent(
                'onPageGenerateContent_build',
                $event
            );
            if ($this->rawContentType) {
                Leafcutter::get()->events()->dispatchEvent(
                    'onPageGenerateContent_build_' . $this->rawContentType,
                    $event
                );
            }
            Leafcutter::get()->events()->dispatchEvent(
                'onPageGenerateContent_finalize',
                $event
            );
            if ($this->rawContentType) {
                Leafcutter::get()->events()->dispatchEvent(
                    'onPageGenerateContent_finalize_' . $this->rawContentType,
                    $event
                );
            }
            $this->generatedContent = $event->content();
            URLFactory::endContext();
        }
        return $this->generatedContent;
    }

    public function hash(): string
    {
        return hash('md5', serialize([
            $this->rawContent(), $this->url(),
        ]));
    }

    public function children(): Collection
    {
        return Leafcutter::get()->pages()->children($this->url());
    }

    public function parent(): ?PageInterface
    {
        return $this->parent ?? Leafcutter::get()->pages()->parent($this->url());
    }

    public function breadcrumb(): array
    {
        $current = $this;
        $breadcrumb = [$this];
        while ($current = $current->parent()) {
            //watch for cycles
            if (in_array($current, $breadcrumb)) {
                return $breadcrumb;
            }
            //unshift latest page onto breadcrumb
            \array_unshift($breadcrumb, $current);
        }
        return $breadcrumb;
    }

    public function setParent($parent)
    {
        if ($parent instanceof PageInterface) {
            $this->parent = $parent;
        } elseif (is_string($parent)) {
            URLFactory::beginContext($this->calledUrl());
            $this->parent = Leafcutter::get()->pages()->get(new URL($parent));
            URLFactory::endContext();
        }
    }
}
