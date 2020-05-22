<?php
namespace Leafcutter\Pages;

use Flatrr\SelfReferencingFlatArray;
use Leafcutter\Common\Collection;
use Leafcutter\Leafcutter;
use Leafcutter\URL;
use Leafcutter\URLFactory;
use Symfony\Component\Yaml\Yaml;

class Page implements PageInterface
{
    protected $content, $url, $meta;
    protected $dynamic = false;
    protected $template = 'default.twig';
    protected $parent;

    public function __construct(URL $url, string $content)
    {
        // normalize trailing slashes/.html
        if ($url->path() != 'favicon.ico' && !preg_match('@(/|\.html)$@', $url->path())) {
            $url->setPath($url->path() . '/');
        }
        $this->url = $url;
        $this->calledURL = $url;
        $this->meta = new SelfReferencingFlatArray([
            'date.generated' => time(),
            'unlisted' => false,
        ]);
        $this->setContent($content);
    }

    public function __sleep()
    {
        // ensure that callback has been resolved before serializing
        $this->content();
        return array_keys(get_object_vars($this));
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

    public function metaMerge(array $meta, $overwrite = false)
    {
        $this->meta->merge($meta, null, $overwrite);
    }

    public function url(): URL
    {
        return clone $this->url;
    }

    public function calledURL(): URL
    {
        return clone $this->calledURL;
    }

    public function setUrl(URL $url)
    {
        $this->url = clone $url;
    }

    public function content($wrap = true): string
    {
        if (is_callable($this->content)) {
            $this->setContent(($this->content)());
        }
        if ($wrap) {
            return '<!--@beginContext:' . $this->calledURL() . '-->' . $this->content . '<!--@endContext-->';
        }
        return $this->content;
    }

    public function setContent($content)
    {
        if (is_string($content)) {
            $event = new PageContentEvent($this, $content);
            Leafcutter::get()->events()->dispatchAll(
                'onPageContentString',
                $event
            );
            $content = preg_replace_callback('/<!--@meta(.+?)-->/ms', function ($match) {
                try {
                    $meta = Yaml::parse($match[1]);
                    $this->meta->merge($meta, null, true);
                } catch (\Throwable $th) {
                    Leafcutter::get()->logger()->error('Failed to parse meta yaml content for ' . $this->calledURL());
                    // throw $th;
                }
                return '';
            }, $content);
            if (!$this->meta['name'] && preg_match('@<h1>(.+?)</h1>@', $content, $matches)) {
                $this->meta['name'] = trim(strip_tags($matches[1]));
            }
            $content = $event->content();
        }
        $this->content = $content;
    }

    public function hash(): string
    {
        return hash('md5', serialize([
            $this->content(), $this->url(),
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
            URLFactory::beginContext($this->calledURL());
            $this->parent = Leafcutter::get()->pages()->get(new URL($parent));
            URLFactory::endContext();
        }
    }
}
