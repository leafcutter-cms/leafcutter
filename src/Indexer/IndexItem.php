<?php
namespace Leafcutter\Indexer;

use Leafcutter\URL;

class IndexItem
{
    public function __construct(string $url, string $value, array $data, AbstractIndex $index)
    {
        $this->url = $url;
        $this->value = $value;
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
