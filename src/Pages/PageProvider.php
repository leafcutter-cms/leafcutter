<?php
namespace Leafcutter\Pages;

use Leafcutter\Common\Collection;
use Leafcutter\Content\Content;
use Leafcutter\Leafcutter;
use Leafcutter\URL;
use Leafcutter\URLFactory;
use Symfony\Component\Yaml\Yaml;

class PageProvider
{
    private $leafcutter;
    private $stack = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->leafcutter->events()->addSubscriber($this);
    }

    public function onPageSetRawContent(PageContentEvent $event)
    {
        $page = $event->page();
        // try to parse out any @meta comments
        $content = preg_replace_callback('/<!--@meta(.+?)-->/ms', function ($match) use ($page) {
            try {
                $meta = Yaml::parse($match[1]);
                $page->metaMerge($meta);
            } catch (\Throwable $th) {
                Leafcutter::get()->logger()->error('Failed to parse meta yaml content for ' . $page->calledUrl());
            }
            return '';
        }, $event->content());
        // try to identify something like an HTML header tag
        if (!$page->meta('name') && preg_match('@^<h1>(.+?)</h1>$@m', $content, $matches)) {
            $page->meta('name', trim(strip_tags($matches[1])));
        }
        if (!$page->meta('name') && preg_match('@^#(.+)$@m', $content, $matches)) {
            $page->meta('name', trim(strip_tags($matches[1])));
        }
        $event->setContent($content);
    }

    public function parent(URL $url): ?PageInterface
    {
        $page = $this->get($url);
        if (!$page || $page->url()->siteFullPath() == '') {
            return null;
        }
        $path = dirname($page->url()->siteFullPath());
        while (true) {
            if ($path == '.') {
                return $this->get(new URL("@/"));
            }
            if ($page = $this->get(new URL("@/$path"))) {
                return $page;
            }
            $ppath = dirname($path);
            if ($ppath == $path) {
                break;
            } else {
                $path = $ppath;
            }
        }
        return null;
    }

    public function children(URL $url): Collection
    {
        $search = preg_replace('@[^\/]+$@', '', $url->siteFullPath()) . '*';
        $result = $this->search($search, $url->siteNamespace());
        $result->remove($this->get($url));
        return $result;
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
        return $this->handle_onPageFile($e, 'md');
    }

    public function onPageGenerateContent_build_md(PageContentEvent $e)
    {
        $e->setContent(
            $this->markdown()->text($e->content())
        );
    }

    public function onPageFile_html(PageFileEvent $e)
    {
        return $this->handle_onPageFile($e, 'html');
    }

    public function onPageFile_htm(PageFileEvent $e)
    {
        return $this->handle_onPageFile($e, 'html');
    }

    protected function handle_onPageFile(PageFileEvent $e, string $type = null)
    {
        $url = $e->url();
        $url->setQuery([]);
        $page = new Page($url);
        $page->setRawContent($e->getContents(), $type);
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
        // allow URLs to be transformed
        $this->leafcutter->events()
            ->dispatchEvent('onPageURL', $url);
        $this->leafcutter->logger()->debug('PageProvider: get(' . $url . ')');
        // allow pages to fully bypass entire return system
        $page =
        $url->siteNamespace() ? $this->leafcutter->events()->dispatchFirst('onPageGet_namespace_' . $url->siteNamespace(), $url) : null ??
        $this->leafcutter->events()->dispatchFirst('onPageGet', $url);
        if ($page) {
            array_pop($this->stack);
            URLFactory::endContext();
            return $page;
        }
        // allow events to build pages from any URL
        $page =
        $url->siteNamespace() ? $this->leafcutter->events()->dispatchFirst('onPageBuild_namespace_' . $url->siteNamespace(), $url) : null ??
        $this->leafcutter->events()->dispatchFirst('onPageBuild', $url);
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
