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

    public function onErrorPage_404(Page $page)
    {
        $url = array_filter(explode('/', $page->url()->siteFullPath()));
        array_pop($url);
        $checking = '';
        $options = [];
        foreach ($url as $chunk) {
            $checking .= "$chunk/";
            if ($related = $this->get(new URL("@/$checking"))) {
                if ($related->status() == 200) {
                    $options[] = $related;
                }
            }
        }
        if ($options) {
            $page->meta('pages.related', new Collection(array_reverse($options)));
        }
    }

    public function onPageGenerateContent_finalize(PageContentEvent $event)
    {
        $event->setContent(
            preg_replace('/<!--@meta.+?-->/ms', '', $event->content())
        );
    }

    public function onPageSetRawContent(PageContentEvent $event)
    {
        $page = $event->page();
        // try to parse out any @meta comments
        $content = preg_replace_callback('/<!--@meta(.+?)-->/ms', function ($match) use ($page) {
            try {
                $meta = Yaml::parse($match[1]);
                $page->metaMerge($meta, true);
            } catch (\Throwable $th) {
                Leafcutter::get()->logger()->error('Failed to parse meta yaml content for ' . $page->calledUrl());
            }
            return $match[0];
        }, $event->content());
        // try to identify something like an HTML header tag
        if (!$page->meta('title') && preg_match('@<h1>(.+?)</h1>@m', $content, $matches)) {
            $page->meta('title', trim(strip_tags($matches[1])));
        }
        if (!$page->meta('title') && preg_match('@^#(.+)$@m', $content, $matches)) {
            $page->meta('title', trim(strip_tags($matches[1])));
        }
        // make home page named "Home" by default
        if (!$page->meta('name') && $page->url()->siteFullPath() == '') {
            $page->meta('name', 'Home');
        }
        // set content
        $event->setContent($content);
    }

    public function parent(URL $url): ?PageInterface
    {
        $page = $this->get($url);
        if ($page && $parent = $page->meta('parent')) {
            if ($parent instanceof PageInterface) {
                return $parent;
            } elseif ($parent = $this->get($parent)) {
                return $parent;
            }
        }
        if (!$page || $page->url()->siteFullPath() == '') {
            return null;
        }
        $path = dirname($page->url()->siteFullPath());
        while (true) {

            if ($path == '.') {
                return $this->get(new URL("@/"));
            }
            $url = new URL("@/$path");
            $url->fixSlashes();
            if ($page = $this->get($url)) {
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
        $parent = $this->get($url);
        if ($parent && $parent->meta('pages.children')) {
            foreach ($parent->meta('pages.children') as $c) {
                if ($c instanceof PageInterface) {
                    $result->add($c);
                } elseif ($c = $this->get(new URL($c))) {
                    $result->add($c);
                }
            }
        }
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
            $errorPath = trim(implode('/', $currentPath), '/');
            $errorPath .= "/_error_pages/$code/index.*";
            $errorPath = trim($errorPath, '/');
            $page = $this->getFromPath($url, $errorPath, $namespace);
        } while ($page === null && array_pop($currentPath) !== null);
        // if nothing was found, try again in the root
        if ($page === null && $namespace) {
            $page = $this->getFromPath($url, "_error_pages/$code/index.*");
        }
        // if still nothing was found, try again with code 999
        if ($page === null && $code != 999) {
            $page = $this->error($url, 999, $code);
            $page->meta('error.code', $originalCode ?? $code);
        }
        // if we got a page, set it up with its values and status before returning
        if ($page !== null) {
            $page->setUrl($url);
            $page->setStatus($originalCode ?? $code);
            $page->setDynamic(true);
        }
        return $page;
    }

    public function getFromPath(URL $url, string $path, ?string $namespace = ''): ?PageInterface
    {
        // begin context
        if (!$this->beginContext($url)) {
            return $this->error($url, 555);
        }
        // look for files from that path
        $files = $this->leafcutter->content()->files($path, $namespace);
        foreach ($files as $file) {
            // if a page is returned by getFromFile it is already finalized
            $page = $this->getFromFile($url, $file->path());
            if ($page) {
                $page->meta('date.modified', filemtime($file->path()));
                $this->endContext();
                return $page;
            }
        }
        $this->endContext();
        return null;
    }

    public function getFromFile(URL $url, string $file): ?PageInterface
    {
        // begin context
        if (!$this->beginContext($url)) {
            return $this->error($url, 555);
        }
        // construct page from file
        $extension = preg_replace('/^.+\.([a-z0-9]+)/', '$1', $file);
        if ($extension == $file) {
            $eventName = 'onPageFile';
        } else {
            $eventName = 'onPageFile_' . $extension;
        }
        // build page
        $page = $this->leafcutter->events()->dispatchFirst(
            $eventName,
            new PageFileEvent($file, $url)
        );
        $page = $this->finalizePage($page);
        // end context and return
        $this->endContext();
        return $page;
    }

    protected function beginContext(URL $url): bool
    {
        // begin context
        URLFactory::beginContext($url);
        // break recursion if the same page is seen too many times in a cycle
        $recursionCount = count(array_filter(
            $this->stack,
            function ($e) use ($url) {
                return $e == "$url";
            }
        ));
        if ($recursionCount > 4) {
            URLFactory::endContext();
            return false;
        }
        // add to stack if we return true
        $this->stack[] = "$url";
        return true;
    }

    protected function endContext()
    {
        array_pop($this->stack);
        URLFactory::endContext();
    }

    public function get(URL $url): ?PageInterface
    {
        // skip non-site URLs
        if (!$url->inSite()) {
            return null;
        }
        // begin context
        if (!$this->beginContext($url)) {
            return $this->error($url, 555);
        }
        // allow URLs to be transformed
        $this->leafcutter->logger()->debug('PageProvider: get(' . $url . ')');
        $this->leafcutter->events()->dispatchEvent('onPageURL', $url);
        // special event names for namespaces
        if ($url->siteNamespace()) {
            $this->leafcutter->events()->dispatchEvent('onPageURL_namespace_' . $url->siteNamespace(), $url);
        }
        // allow pages to fully bypass entire return system
        $page = $url->siteNamespace() ? $this->leafcutter->events()->dispatchFirst('onPageGet_namespace_' . $url->siteNamespace(), $url) : null ?? $this->leafcutter->events()->dispatchFirst('onPageGet', $url);
        if ($page) {
            $page = $this->finalizePage($page);
            $this->endContext();
            return $page;
        }
        // otherwise attempt to make a page from content files (already finalized by getFromPath())
        $page = $this->getFromPath($url, $this->searchPath($url->sitePath()), $url->siteNamespace());
        if ($page) {
            $this->endContext();
            return $page;
        }
        // nothing found, return null
        $this->endContext();
        return null;
    }

    protected function finalizePage(?PageInterface $page): ?PageInterface
    {
        // return null for null
        if ($page === null) {
            return null;
        }
        // dispatch error events
        if ($page->status() != 200) {
            $this->leafcutter->events()->dispatchEvent('onErrorPage', $page);
            $this->leafcutter->events()->dispatchEvent('onErrorPage_' . $page->status(), $page);
        }
        // dispatch normal events
        $event = new PageEvent($page, $page->url());
        $this->leafcutter->events()->dispatchEvent('onPageReady', $event);
        $this->leafcutter->events()->dispatchEvent('onPageReturn', $event);
        return $event->page();
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
