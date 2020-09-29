<?php
namespace Leafcutter\Indexer;

class IndexValue
{
    protected $value, $count, $index;

    public function __construct(string $value, int $count, AbstractIndex $index)
    {
        $this->value = $value;
        $this->count = $count;
        $this->index = $index;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function count(): int
    {
        return $this->count;
    }
}
