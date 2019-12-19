<?php
namespace Leafcutter\Content\Pages;

use Leafcutter\Leafcutter;
use Leafcutter\Common\Collections\Collection;

class PageProvider
{
    protected $leafcutter;
    protected $systemBuilder;

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->systemBuilder = new SystemPageBuilder($this->leafcutter);
        $this->leafcutter->hooks()->addSubscriber($this->systemBuilder);
    }

    public function parent($page) : ?PageInterface
    {
        if (is_string($page)) {
            $page = $this->get($page);
        }
        if (!($page instanceof PageInterface)) {
            return null;
        }
        if ($page->getUrl()->getFullPath() == '/') {
            return null;
        }
        $path = dirname($page->getUrl()->getFullPath());
        while ($path != '') {
            $this->leafcutter->logger()->debug('PageProvider: parent: '.$path);
            if ($page = $this->get($path)) {
                $this->leafcutter->logger()->debug('PageProvider: parent: found: '.$path);
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

    public function breadcrumb($page) : array
    {
        $current = null;
        $breadcrumb = [];
        while ($current = $this->parent($current??$page)) {
            //watch for cycles
            if (in_array($current, $breadcrumb)) {
                return $breadcrumb;
            }
            //unshift latest page onto breadcrumb
            \array_unshift($breadcrumb, $current);
        }
        return $breadcrumb;
    }

    public function children($page) : Collection
    {
        if (is_string($page)) {
            $page = $this->get($page);
        }
        if (!($page instanceof PageInterface)) {
            return new Collection;
        }
        $children = $this->childrenByPath($page->getUrl()->getContext());
        foreach ($page->getChildren() as $child) {
            $children[] = $child;
        }
        return new Collection($children);
    }

    protected function childrenByPath(string $context)
    {
        $path = $context.'/';
        $pages = [];
        foreach ($this->leafcutter->content()->list("$context*", true) as $path => $file) {
            if (is_file($file) && !preg_match('/index\.[a-zA-Z0-9]+$/', $file)) {
                $pages[] = $this->get($path);
            }
            if (is_dir($file)) {
                if ($page = $this->get($path)) {
                    $pages[] = $page;
                } else {
                    foreach ($this->childrenByPath("$path/*") as $page) {
                        $pages[] = $page;
                    }
                }
            }
        }
        return array_filter($pages);
    }

    public function get(string $path, $context='/') : ?PageInterface
    {
        $this->leafcutter->logger()->debug("PageProvider: get: $path: $context");
        try {
            // normalize URL/context, and the resulting path
            $url = $this->leafcutter->normalizeUrl($path, $context);
        } catch (\Throwable $th) {
            return null;
        }
        $path = $url->getFullPath();
        if ($hits = $this->list($path)) {
            return reset($hits);
        } else {
            return null;
        }
    }

    public function getErrorPage(int $code, string $message, $context, $originalCode=null, $originalContext=null) : PageInterface
    {
        $this->leafcutter->logger()->debug("PageProvider: getErrorPage: $code: $context ($originalCode: $originalContext)");
        if ($context instanceof UrlInterface) {
            $context = $context->getContext();
        }
        $originalContext = $originalContext ?? $context;
        $originalCode = $originalCode ?? $code;
        $path = "{$context}_error_{$code}/";
        if ($page = $this->get($path)) {
            $page->setContent(str_replace('{error_message}', $message, $page->getContent()));
            $page->setContent(str_replace('{error_code}', $originalCode, $page->getContent()));
            return $page;
        }
        $newContext = preg_replace('@[^/]+/$@', '', $context);
        if ($newContext != $context) {
            return $this->getErrorPage($code, $message, $newContext, $originalCode, $originalContext);
        }
        if ($code != 999) {
            return $this->getErrorPage(999, $message, $originalContext, $originalCode, $originalContext);
        }
        throw new \Exception("Couldn't locate an appropriate error page. Tried to get error $originalCode for $originalContext. Message: $message");
    }

    public function list(string $glob) : array
    {
        $this->leafcutter->logger()->debug("PageProvider: list: $glob");
        $glob = $this->normalizeGlob($glob);
        $skipIndexes = preg_match('@/\*$@', $glob);//need to skip indexes for /* type globs, to avoid recursion
        // build an array keyed by normalized paths, each containing an array of potential files
        $files = [];
        $matches = $this->leafcutter->content()->list($glob);
        foreach ($matches as $path => $file) {
            if ($skipIndexes && preg_match('@/index\..+$@', $file)) {
                continue;
            }
            $kp = $path;
            $kp = preg_replace('/\.[a-zA-Z0-9]+$/', '.html', $path);
            $kp = preg_replace('@/index\..+$@', '/', $kp);
            if (!is_dir($file)) {
                $files[$kp][$path] = $file;
            } else {
                foreach ($this->leafcutter->content()->list("$path/index.*") as $path => $file) {
                    $files[$kp.'/'][$path] = $file;
                }
            }
        }
        // turn the array of paths and candidate files into an array of built pages
        array_walk(
            $files,
            function (&$value, $path) {
                $value = $this->getFromFile($path, $value);
            }
        );
        // return the filtered output
        return array_filter($files);
    }

    protected function getFromFile(string $path, $candidates) : ?PageInterface
    {
        if (!is_array($candidates)) {
            $candidates = [$candidates];
        }
        $url = $this->leafcutter->normalizeUrl($path);
        $this->leafcutter->logger()->debug("PageProvider: getFromFile: $path: $url: ".implode(', ', $candidates));
        foreach ($candidates as $candidate) {
            $ext = strtolower(preg_replace('@^.+\.([a-zA-Z0-9]+)$@', '$1', $candidate));
            if ($ext) {
                $page = $this->leafcutter->hooks()->dispatchFirst('onPageFile_'.$ext, [$url,$candidate]);
                if ($page) {
                    $page->setDateModified(filemtime($candidate));
                    $page->setOrder($this->leafcutter->content()->getOrderFromFilename($candidate));
                    return $this->finalizePage($page);
                }
            }
        }
        return null;
    }

    protected function finalizePage($page)
    {
        // fix up URL
        $url = $page->getUrl();
        $url->setBase($this->leafcutter->getBase());
        $page->setUrl($url);
        // run hooks to allow further modification of pages
        $page = $this->leafcutter->hooks()->dispatchAll('onPageReady', $page);
        // extract metadata
        // TODO: does this belong here?
        // return
        return $page;
    }

    protected function normalizeGlob(string $path) : string
    {
        $n = preg_replace('/\.html$/', '.*', $path);
        $n = preg_replace('@/$@', '/index.*', $n);
        //break url into parts, immediately remove single dot directories and blanks
        $n = str_replace('\\', '/', $n);
        $n = array_filter(
            explode('/', $n),
            function ($part) {
                if ($part == '.') {
                    return false;
                } else {
                    return !!$part;
                }
            }
        );
        //loop through parts
        foreach ($n as $i => $part) {
            //apply .. operator
            if ($part == '..') {
                //unset ..
                unset($n[$i]);
                //unset closest prior part
                for ($j = $i; $j >= 0; $j--) {
                    if (isset($n[$j])) {
                        unset($n[$j]);
                        break;
                    }
                }
            }
        }
        //output
        $n = implode('/', $n);
        $this->leafcutter->logger()->debug("PageProvider: normalizeGlob: $path => $n");
        return $n;
    }
}
