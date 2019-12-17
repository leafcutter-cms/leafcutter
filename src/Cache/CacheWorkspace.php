<?php
namespace Leafcutter\Cache;

class CacheWorkspace implements CacheInterface
{
    protected $cache;
    protected $namespace;
    protected $tags;
    protected $ttl;

    public function __construct(CacheProvider $cache, string $namespace, int $ttl=60, array $tags=[])
    {
        $this->cache = $cache;
        $this->namespace = $namespace;
        $this->tags = $tags;
        $this->ttl = $ttl;
    }

    /**
     * Passes through to parent cache, with key prefixed, as well as the
     * tags and ttl defaulting to the defaults defined for this workspace.
     *
     * @param string $key
     * @param callable $callback
     * @param array $tags
     * @param integer $ttl
     * @return void
     */
    public function get(string $key, callable $callback, int $ttl=null, array $tags=[])
    {
        if ($ttl === null) {
            $ttl = $this->ttl;
        }
        return $this->cache->get(
            $this->namespace.'.'.$key,
            $callback,
            $ttl,
            array_unique($tags+$this->tags)
        );
    }

    /**
     * Passes through to parent cache, with the key prefixed.
     *
     * @param string $key
     * @return void
     */
    public function delete(string $key)
    {
        $this->cache->delete($this->namespace.'.'.$key);
    }

    /**
     * Passes through to parent cache. Is capable of invalidating
     * cache items outside what was created by this workspace.
     *
     * @param array $tags
     * @return void
     */
    public function invalidateTags(array $tags)
    {
        $this->cache->invalidateTags($tags);
    }
}
