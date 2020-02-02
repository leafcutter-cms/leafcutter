<?php
namespace Leafcutter\Pages;

use Leafcutter\Common\Collection;
use Leafcutter\Content\Content;
use Leafcutter\Leafcutter;
use Leafcutter\URL;
use Leafcutter\URLFactory;

class PageProvider
{
    private $leafcutter;
    private $stack = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->leafcutter->events()->addSubscriber($this);
    }

    public function breadcrumb(PageInterface $page): array
    {
        $current = null;
        $breadcrumb = [];
        while ($current = $this->parent(($current ? $current->url() : null) ?? $page->url())) {
            //watch for cycles
            if (in_array($current, $breadcrumb)) {
                return $breadcrumb;
            }
            //unshift latest page onto breadcrumb
            \array_unshift($breadcrumb, $current);
        }
        return $breadcrumb;
    }

    public function parent(URL $url): ?PageInterface
    {
        //TODO: allow metadata to specify a parent
        $page = $this->get($url);
        if (!$page || $page->url()->siteFullPath() == '') {
            return null;
        }
        $path = dirname($page->url()->siteFullPath());
        while ($path != '') {
            if ($page = $this->get(new URL($path))) {
                return $page;
            } else {
                $ppath = dirname($path);
                if ($ppath == $path) {
                    break;
                } else {
                    $path = $ppath;
                }
            }
        }
        return null;
    }

    public function children(URL $url): Collection
    {
        $search = preg_replace('@[^\/]+$@', '', $url->siteFullPath()) . '*';
        return $this->search($search);
    }

    public function search(string $glob, string $namespace = null): Collection
    {
        $pages = [];
        foreach ($this->leafcutter->content()->files($glob, $namespace) as $file) {
            $url = $file->url();
            $url->setExtension('html');
            if ($url->pathFile() != 'index.html') {
                $pages["$url"] = $this->get($url);
            }
        }
        foreach ($this->leafcutter->content()->directories($glob, $namespace) as $directory) {
            $url = $directory->url();
            if (!($pages["$url"] = $this->get($url)) && substr(basename($directory->path()), 0, 1) != '_') {
                foreach ($this->search($url->sitePath() . '*') as $p) {
                    $pages[$p->url()->__toString()] = $p;
                }
            }
        }
        $pages = array_filter($pages);
        return new Collection($pages);
    }

    public function onPageFile_md(PageFileEvent $e)
    {
        $content = file_get_contents($e->path());
        $content = $this->markdown()->text($content);
        $url = $e->url();
        $url->setQuery([]);
        $page = new Page($url, $content);
        return $page;
    }

    public function error(URL $url, int $code, int $originalCode = null): ?PageInterface
    {
        // try to find a relevant error page in _error_page/[code] somewhere in the
        // parent directories
        $currentPath = explode('/', $url->sitePath());
        $namespace = $url->siteNamespace();
        $page = null;
        do {
            $errorPath = implode('/', $currentPath) . "/_error_pages/$code/";
            if ($namespace) {
                $errorPath = "@$namespace/$errorPath";
            }
            $errorUrl = new URL("@/$errorPath");
            $page = $this->get($errorUrl);
        } while ($page === null && array_pop($currentPath) !== null);
        // if nothing was found, try again in the root
        if ($page === null && $namespace) {
            $errorUrl = new URL("@/_error_pages/$code/");
            $page = $this->get($errorUrl);
        }
        // if still nothing was found, try again with code 999
        if ($page === null && $code != 999) {
            $page = $this->error($url, 999, $code);
        }
        // if we got a page, return it
        if ($page !== null) {
            $page->setUrl($url);
        }
        return $page;
    }

    public function get(URL $url): ?PageInterface
    {
        URLFactory::beginContext($url);
        // skip non-site URLs
        if (!$url->inSite()) {
            URLFactory::endContext();
            return null;
        }
        // break recursion if the same page is seen too many times in a cycle
        $recursionCount = count(array_filter(
            $this->stack,
            function ($e) use ($url) {
                return $e == "$url";
            }
        ));
        if ($recursionCount > 4) {
            URLFactory::endContext();
            return $this->error($url, 555);
        }
        $this->stack[] = "$url";
        // allow pages to fully bypass entire return system
        $page =
        $url->siteNamespace() ? $this->leafcutter->events()->dispatchFirst('onPageURL_namespace_' . $url->siteNamespace(), $url) : null ??
        $this->leafcutter->events()->dispatchFirst('onPageURL', $url);
        if ($page) {
            array_pop($this->stack);
            URLFactory::endContext();
            return $page;
        }
        // allow events to build pages from any URL
        $page =
        $url->siteNamespace() ? $this->leafcutter->events()->dispatchFirst('onPageGet_namespace_' . $url->siteNamespace(), $url) : null ??
        $this->leafcutter->events()->dispatchFirst('onPageGet', $url);
        // otherwise attempt to make a page from content files
        if (!$page) {
            $path = $this->searchPath($url->sitePath());
            $namespace = $url->siteNamespace();
            $files = $this->leafcutter->content()->files($path, $namespace);
            foreach ($files as $file) {
                $page = $this->leafcutter->events()->dispatchFirst(
                    'onPageFile_' . $file->extension(),
                    new PageFileEvent($file->path(), $url)
                );
                if ($page) {
                    break;
                }
            }
        }
        // return page after dispatching events
        if ($page) {
            $page->meta('date.modified', filemtime($file->path()));
            $event = new PageEvent($page, $url);
            $this->leafcutter->events()->dispatchEvent('onPageReady', $event);
            $this->leafcutter->events()->dispatchEvent('onPageReturn', $event);
            array_pop($this->stack);
            URLFactory::endContext();
            return $event->page();
        } else {
            array_pop($this->stack);
            URLFactory::endContext();
            return null;
        }
    }

    protected function searchPath(string $path): string
    {
        if (substr($path, -5) == '.html') {
            $path = substr($path, 0, strlen($path) - 5);
            $path .= '.*';
        } else {
            $path = preg_replace('@/$@', '', $path);
            $path .= '/index.*';
        }
        return $path;
    }

    protected function markdown()
    {
        static $markdown;
        if (!isset($markdown)) {
            $markdown = new \ParsedownExtra;
        }
        return $markdown;
    }
}