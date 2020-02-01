<?php
namespace Leafcutter\Common;

class Collection implements \Iterator, \Countable
{
    protected $items = [];
    protected $items_sorted = [];
    protected $sorted = false;
    protected $sortBy = [];
    protected $position = 0;

    public function __construct(array $items = [])
    {
        array_map([$this, 'add'], $items);
    }

    public function getHash(): string
    {
        $hash = [
            get_called_class(),
        ];
        foreach ($this->items as $item) {
            if (\method_exists($item, 'hash')) {
                $hash[] = $item->hash();
            }
        }
        sort($hash);
        return hash('crc32', serialize($hash));
    }

    public function tail()
    {
        $this->doSort();
        return end($this->items);
    }

    public function add($item): void
    {
        $this->items[] = $item;
        $this->sorted = false;
    }

    public function resetSort(): Collection
    {
        $this->sortBy = [];
        $this->sorted = false;
        return $this;
    }

    public function sortBy($sorter, $descending = false): Collection
    {
        array_unshift($this->sortBy, [$sorter, $descending]);
        // set sorted to false
        $this->sorted = false;
        // fluent interface
        return $this;
    }

    public function contains($item): bool
    {
        foreach ($this->items as $e) {
            if ($e == $item) {
                return true;
            }
        }
        return false;
    }

    protected function doSort()
    {
        if ($this->sorted) {
            return;
        }
        $this->items_sorted = array_filter(
            $this->items,
            function ($e) {
                return !$this->getVal($e, 'unlisted');
            }
        );
        usort(
            $this->items_sorted,
            [$this, 'itemcmp']
        );
        $this->sorted = true;
    }

    protected function itemcmp($a, $b)
    {
        foreach ($this->sortBy as list($name, $descending)) {
            if ($r = $this->valcmp($this->getVal($a, $name), $this->getVal($b, $name))) {
                return $descending ? -$r : $r;
            }
        }
        return 0;
    }

    protected function getVal($item, $name)
    {
        if (method_exists($item, $name)) {
            return $item->$name();
        } elseif (method_exists($item, 'meta')) {
            return $item->meta($name);
        } else {
            return @$item->$name;
        }
    }

    protected static function valcmp($a, $b)
    {
        if (is_string($a) && is_string($b)) {
            return strcasecmp(trim($a), trim($b));
        }
        if ($a == $b) {
            return 0;
        }
        if ($a == INF || $b == -INF) {
            return 1;
        }
        if ($b == INF || $a == -INF) {
            return -1;
        }
        if ($a > $b) {
            return 1;
        }
        if ($b > $a) {
            return -1;
        }
        return 0;
    }

    public function count()
    {
        $this->doSort();
        return count($this->items_sorted);
    }

    public function import(array $items)
    {
        array_map([$this, 'add'], $items);
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function current()
    {
        $this->doSort();
        return $this->items_sorted[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        ++$this->position;
    }

    public function valid()
    {
        $this->doSort();
        return isset($this->items_sorted[$this->position]);
    }
}
