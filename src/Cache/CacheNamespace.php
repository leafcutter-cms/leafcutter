<?php
namespace Leafcutter\Cache;

use Leafcutter\Leafcutter;

class CacheNamespace implements CacheInterface
{
    protected $name, $parent, $expiration;

    public function __construct(string $name, CacheInterface $parent, int $expiration = null)
    {
        $this->name = strtolower($name);
        $this->parent = $parent;
        $this->expiration = $expiration;
    }

    public function get(string $key, callable $callback = null, int $expiration = null)
    {
        return $this->parent->get($this->transformKey($key), $callback, $expiration ?? $this->expiration);
    }

    public function set(string $key, $value, int $expiration = null)
    {
        $this->parent->set($this->transformKey($key), $value, $expiration ?? $this->expiration);
    }

    protected function transformKey(string $key): string
    {
        return $this->name . '/' . $key;
    }
}
