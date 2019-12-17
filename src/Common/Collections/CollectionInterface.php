<?php
namespace Leafcutter\Common\Collections;

interface CollectionInterface extends \Iterator, \Countable
{
    public function __construct(array $items);
    public function add(CollectableInterface $item) : void;
    public function sortBy($sortBy, $descending=false) : CollectionInterface;
    public function contains(CollectableInterface $item) : bool;
    public function getHash() : string;
}
