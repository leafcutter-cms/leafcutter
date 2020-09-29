<?php
namespace Leafcutter\Indexer;

use Leafcutter\URL;

class IndexItem
{
    protected $url, $value, $sort, $data, $index;

    public function __construct(string $url, string $value, string $sort, array $data, AbstractIndex $index)
    {
        $this->url = $url;
        $this->value = $value;
        $this->sort = $sort;
        $this->data = $data;
        $this->index = $index;
    }

    public function url(): URL
    {
        return new URL('@/' . $this->url);
    }

    public function urlString(): string
    {
        return $this->url;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function sort(): string
    {
        return $this->sort;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function setData(array $data)
    {
        $this->data = $data;
    }

    public function save()
    {
        $this->index->item_save($this);
    }

    public function delete()
    {
        $this->index->item_delete($this);
    }
}
