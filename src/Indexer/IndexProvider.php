<?php
namespace Leafcutter\Indexer;

use Leafcutter\Leafcutter;
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

    public function onLeafcutterConstructed(Leafcutter $leafcutter)
    {
        $this->index('uid', UIDIndex::class);
    }

    public function exists(string $name): bool
    {
        $name = $this->sanitizeName($name);
        return is_file($this->indexFile($name));
    }

    public function index(string $name, string $class): ?AbstractIndex
    {
        $name = $this->sanitizeName($name);
        if ($class) {
            $this->setClass($name, $class);
        }
        $create = !$this->exists($name);
        if (!isset($this->indexes[$name])) {
            $class = $this->classes[$name];
            $this->indexes[$name] = new $class($name, $this->pdo($name), $this->leafcutter);
            if ($create) {
                $this->indexes[$name]->create();
            }
        }
        return $this->indexes[$name];
    }

    protected function setClass(string $name, string $class)
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
