<?php
namespace Leafcutter\Cache;

use Leafcutter\Leafcutter;

interface CacheInterface
{
    public function get(string $key, callable $callback = null, int $expiration = null);
    public function set(string $key, $value, int $expiration = null);
}
