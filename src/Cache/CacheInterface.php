<?php
namespace Leafcutter\Cache;

interface CacheInterface
{
    public function get(string $key, callable $callback, int $ttl=null, array $tags=[]);
    public function delete(string $key);
    public function invalidateTags(array $tags);
}
