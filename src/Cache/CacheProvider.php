<?php
namespace Leafcutter\Cache;

use Leafcutter\Leafcutter;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;

use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CacheProvider implements \Leafcutter\Cache\CacheInterface
{
    protected $cache;
    protected $tagAware;

    /**
     * With APCu available, good performance can be had without providing a
     * custom cache. If you're providing a ChainAdapter, make sure it has a
     * fast in-memory option at the front, like an ArrayLoader. Many calls
     * to Leafcutter's cache are assuming it can be used for in-request
     * memoizing, so it needs to work quickly for that.
     *
     * @param CacheInterface $cache
     */
    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        //set up default cache in APCu if supported, otherwise fall back to ArrayAdapter
        if (ApcuAdapter::isSupported()) {
            $cache = new ApcuAdapter();
        } else {
            $cache = new ArrayAdapter();
        }
        $this->setCache($cache);
    }

    /**
     * Set the cache provider
     *
     * @param CacheInterface $cache
     * @return void
     */
    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
        $this->tagAware = $this->cache instanceof TagAwareCacheInterface;
    }

    /**
     * Utility function that almost passes directly into a Symfony cache using the
     * contracts interface.
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
            $ttl = null;
        }
        $key.= '.'.$this->leafcutter->hash();
        return $this->cache->get($key, function (ItemInterface $item) use ($key,$callback,$tags,$ttl) {
            $value = call_user_func($callback);

            if (method_exists($value, 'getTtl') && $newTtl = $value->getTtl()) {
                $this->leafcutter->logger()->debug("Cache: Overriding TTL: $key=".get_class($value)." $ttl => $newTtl");
                $item->expiresAfter($newTtl);
            } else {
                $item->expiresAfter($ttl);
            }
            !$this->tagAware ?? $item->tag($tags);

            return $value;
        });
    }

    /**
     * Identical to Symfony cache delete()
     *
     * @param string $key
     * @return void
     */
    public function delete(string $key)
    {
        $this->cache->delete($key);
    }

    /**
     * Identical to Symfony cache invalidateTags()
     *
     * @param array $tags
     * @return void
     */
    public function invalidateTags(array $tags)
    {
        $this->cache->invalidateTags($tags);
    }

    /**
     * Identical to Symfony cache prune()
     *
     * @return void
     */
    public function prune()
    {
        $this->cache->prune();
    }

    /**
     * Retrieve a helper object that will operate on this cache using the same
     * interface, but with key names automatically prefixed, and can have
     * tags added to all items, and a different default TTL.
     *
     * @param string $namespace
     * @param array $tags
     * @param integer $ttl
     * @return CacheWorkspace
     */
    public function workspace(string $namespace, int $ttl=null, array $tags=[]) : CacheWorkspace
    {
        if ($ttl === null) {
            $ttl = 60;
        }
        return new CacheWorkspace($this, $namespace, $ttl, $tags);
    }
}
