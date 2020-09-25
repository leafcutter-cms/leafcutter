<?php
namespace Leafcutter\Indexer;

use Leafcutter\Common\Collection;
use Leafcutter\Leafcutter;
use Leafcutter\Pages\Page;
use Leafcutter\Pages\PageContentEvent;
use Leafcutter\URL;
use PDO;

class IndexProvider
{
    protected $leafcutter;
    protected $pdos = [];
    protected $classes = [];
    protected $indexes = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->leafcutter->events()->addSubscriber($this);
    }

    public function onErrorPage(Page $page)
    {
        $this->index('uid')->deleteByURL($page->url());
    }

    public function onPageGenerateContent_finalize(PageContentEvent $event)
    {
        if ($uid = $event->page()->meta('uid')) {
            $index = $this->index('uid');
            $index->save($event->page()->url(), $uid);
        }
    }

    public function onPageGet_namespace_uid(URL $url): ?Page
    {
        $url->fixSlashes();
        $uid = trim($url->sitePath(), '/');
        $results = $this->index('uid')->getByValue($uid);
        $results = array_map(
            function ($i) {
                return $this->leafcutter->pages()->get($i->url());
            },
            $results
        );
        $results = array_filter($results);
        if ($results) {
            if (count($results) == 1) {
                return $this->leafcutter->pages()->get($results[0]->url());
            } else {
                $page = $this->leafcutter->pages()->error($url, 300);
                $page->meta('pages.related', new Collection($results));
                $page->setUrl($url);
                return $page;
            }
        }
        return null;
    }

    public function exists(string $name): bool
    {
        $name = $this->sanitizeName($name);
        return is_file($this->indexFile($name));
    }

    public function index(string $name, string $class = null): ?Index
    {
        $name = $this->sanitizeName($name);
        if ($class) {
            $this->setClass($name, $class);
        }
        $create = !$this->exists($name);
        if (!isset($this->indexes[$name])) {
            $class = @$this->classes[$name] ?? Index::class;
            $this->indexes[$name] = new $class($name, $this->pdo($name), $this->leafcutter);
            if ($create) {
                $this->indexes[$name]->create();
            }
        }
        return $this->indexes[$name];
    }

    public function setClass(string $name, string $class)
    {
        $name = $this->sanitizeName($name);
        // unset from cache if class is changed
        if (isset($this->indexes[$name]) && !($this->indexes[$name] instanceof $class)) {
            unset($this->indexes[$name]);
        }
        $this->classes[$name] = $class;
    }

    protected function sanitizeName(string $name): string
    {
        return preg_replace('/[^a-z0-9\-_]/', '_', strtolower($name));
    }

    protected function pdo(string $name): PDO
    {
        if (!isset($this->pdos[$name])) {
            $this->pdos[$name] = new PDO(
                'sqlite:' . $this->indexFile($name)
            );
        }
        return $this->pdos[$name];
    }

    protected function indexFile($name): string
    {
        return $this->leafcutter->config('directories.storage') . '/' . $name . '.index.sqlite';
    }
}
