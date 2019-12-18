<?php
namespace Leafcutter\Common\Collections;

class Collection implements CollectionInterface
{
    protected $items = [];
    protected $sorted = false;
    protected $sortBy = ['getName','getDateModified'];
    protected $descending = false;
    protected $position = 0;

    public function __construct(array $items=[])
    {
        array_map([$this,'add'], $items);
    }

    public function getHash() : string
    {
        $hash = [
            get_called_class(),
            $this->sortBy,
            $this->descending
        ];
        foreach ($this->items as $item) {
            $hash[] = $item->getHash();
        }
        sort($hash);
        return hash('crc32', serialize($hash));
    }

    public function add(CollectableInterface $item) : void
    {
        if (!$this->contains($item)) {
            $this->items[] = $item;
            $this->sorted = false;
        }
    }

    public function sortBy($sortBy, $descending=false) : CollectionInterface
    {
        // if only a single sortBy is given, turn it into an array
        if (!is_array($sortBy) || is_callable($sortBy)) {
            $sortBy = [$sortBy];
        }
        // if sortBy is changed, set sorted to false
        if ($this->sortBy != $sortBy) {
            $this->sortBy = $sortBy;
            $this->sorted = false;
        }
        // if descending is changed, but items are already sorted, just reverse them
        if ($this->descending != $descending) {
            $this->descending = $descending;
            if ($this->sorted) {
                $this->items = array_reverse($this->items);
            }
        }
        // fluent interface
        return $this;
    }

    public function contains(CollectableInterface $item) : bool
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
            [$this,'itemcmp']
        );
        if ($this->descending) {
            $this->items = array_reverse($this->items);
        }
        $this->sorted = true;
    }

    protected function itemcmp($a, $b)
    {
        //TODO: compare items by various attributes, using valcmp
        foreach ($this->sortBy as $name) {
            if ($r = $this->valcmp($this->getVal($a, $name), $this->getVal($b, $name))) {
                return $r;
            }
        }
        return 0;
    }

    protected function getVal($item, $name)
    {
        if (method_exists($item, $name)) {
            return $item->$name();
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
        array_map([$this,'add'], $items);
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
