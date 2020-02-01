<?php
namespace Leafcutter\Common;

class Collection implements \Iterator, \Countable
{
    protected $items = [];
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
        if (!$this->contains($item)) {
            $this->items[] = $item;
            $this->sorted = false;
        }
    }

    public function resetSort(): Collection
    {
        $this->sortBy = [];
        $this->sorted = true;
        return $this;
    }

    public function sortBy($sorter, $descending = false): Collection
    {
        $oldSort = $this->sortBy;
        array_unshift($this->sortBy, [$sorter, $descending]);
        // if sortBy is changed, set sorted to false
        if ($this->sortBy != $oldSort) {
            $this->sorted = false;
        }
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
        usort(
            $this->items,
            [$this, 'itemcmp']
        );
        $this->sorted = true;
    }

    protected function itemcmp($a, $b)
    {
        //TODO: compare items by various attributes, using valcmp
        foreach ($this->sortBy as list($name, $descending)) {
            if ($r = $this->valcmp($this->getVal($a, $name), $this->getVal($b, $name))) {
                if ($descending) {
                    return -$r;
                } else {
                    return $r;
                }
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
        return count($this->items);
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
        return $this->items[$this->position];
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
        return isset($this->items[$this->position]);
    }
}
