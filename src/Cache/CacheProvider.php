<?php
namespace Leafcutter\Cache;

use Leafcutter\Leafcutter;
use Leafcutter\Pages\Page;
use Leafcutter\Response;

class CacheProvider implements CacheInterface
{
    private $leafcutter;
    protected $expiration = 60;
    protected $pool;
    protected $driver;

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->leafcutter->events()->addSubscriber($this);
        // set up Stash -- currently only supports single drivers
        switch ($leafcutter->config('cache.driver')) {
            case 'sqlite':
                $this->driver = new \Stash\Driver\Sqlite(
                    $leafcutter->config('cache.driver_sqlite_config')
                );
                break;
            case 'ephemeral';
                $this->driver = new \Stash\Driver\Ephemeral();
                break;
            default: //default is filesystem
                $this->driver = new \Stash\Driver\FileSystem(
                    $leafcutter->config('cache.driver_filesystem_config')
                );
                break;
        }
        $this->pool = new \Stash\Pool($this->driver);
        // set up internal namespaces
        $this->output = $this->namespace('cacheprovider_outputcache', $leafcutter->config('cache.ttl.output'));
        $this->assets = $this->namespace('cacheprovider_assetcache', $leafcutter->config('cache.ttl.assets'));
    }

    public function __destruct()
    {
        $this->pool->commit();
    }

    function namespace (string $name, $expiration = null): CacheInterface {
        return new CacheNamespace($name, $this, $expiration ?? $this->expiration);
    }

    public function get(string $key, callable $callback = null, int $expiration = null)
    {
        if ($callback && $this->pool->getItem($key)->isMiss()) {
            $this->set($key, $callback(), $expiration);
        }
        return $this->pool->getItem($key)->get();
    }

    public function set(string $key, $value, int $expiration = null)
    {
        $item = $this->pool->getItem($key);
        $item->lock();
        $item->set($value);
        $item->setTTL($expiration ?? $this->expiration);
        $this->pool->save($item);
    }

    public function onResponseURL(\Leafcutter\URL $url)
    {
        return $this->output->get($this->hashUrl($url));
    }

    public function onResponseReturn(Response $response)
    {
        if ($response->source() instanceof Page && $response->source()->dynamic()) {
            return;
        }
        $this->output->set($this->hashUrl($response->url()), $response);
    }

    public function onAssetURL(\Leafcutter\URL $url)
    {
        return $this->assets->get($this->hashUrl($url));
    }

    public function onAssetReturn($event)
    {
        $this->assets->set($this->hashUrl($event->url()), $event->asset());
    }

    protected function hashUrl(\Leafcutter\URL $url): string
    {
        return hash(
            'md5',
            serialize([
                $url->__toString(),
                $this->leafcutter->content()->hash(
                    $url->sitePath(), $url->siteNamespace()
                ),
            ])
        );
    }
}
