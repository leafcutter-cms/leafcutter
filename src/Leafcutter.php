<?php
namespace Leafcutter;

use Monolog\Logger;

class Leafcutter
{
    private static $instances = [];
    private $config, $events, $cache, $content, $pages, $assets, $images, $templates, $theme, $dom, $statics;

    private function __construct(Config\Config $config = null, Logger $logger = null)
    {
        $this->logger = $logger ?? new Logger('leafcutter');
        $this->config = $config ?? new Config\Config();
        $this->events = new Events\EventProvider($this);
        $this->cache = new Cache\CacheProvider($this);
        $this->content = new Content\ContentProvider($this);
        $this->pages = new Pages\PageProvider($this);
        $this->assets = new Assets\AssetProvider($this);
        $this->images = new Images\ImageProvider($this);
        $this->templates = new Templates\TemplateProvider($this);
        $this->theme = new Themes\ThemeProvider($this);
        $this->dom = new DOM\DOMProvider($this);
        $this->addons = new Addons\AddonProvider($this);
    }

    public function logger(): Logger
    {
        return $this->logger;
    }

    public function theme(): Themes\ThemeProvider
    {
        return $this->theme;
    }

    public function addons(): Addons\AddonProvider
    {
        return $this->addons;
    }

    public function addon(string $name): ?Addons\AddonInterface
    {
        return $this->addons()->get($name);
    }

    public function find(string $path)
    {
        try {
            $url = new URL($path);
            return $this->pages()->get($url) ?? $this->assets()->get($url);
        } catch (\Throwable $th) {
            return null;
        }
    }

    public function buildResponse(URL $url, $normalizationRedirect = true): Response
    {
        // check for responses from events
        $response =
        $url->siteNamespace() ? $this->events()->dispatchFirst('onResponseURL_namespace_' . $url->siteNamespace(), $url) : null ??
        $this->events()->dispatchFirst('onResponseURL', $url);
        if ($response) {
            $response->setURL($url);
        }
        // try to build response from page
        $page = null;
        if (!$response) {
            $response = new Response();
            $response->setURL($url);
            $page = $this->pages()->get($url) ?? $this->events()->dispatchFirst('onResponsePageURL', $url);
            if ($page && $normalizationRedirect) {
                URLFactory::normalizeCurrent($page->url());
            }
            if (!$page) {
                $page = $this->pages()->error($url, 404);
                $response->setStatus(404);
            }
            if ($page) {
                $response->setText($page->content());
            } else {
                $response->setStatus(404);
                $response->setText('<!doctype html><html><body><h1>404 not found</h1><p>Additionally, no error page could be located.</p></body></html>');
            }
        }
        // dispatch final events and return
        $this->events()->dispatchEvent('onResponseContentReady', $response);
        if ($page) {
            $this->events()->dispatchEvent('onResponsePageReady', $page);
            $response->setSource($page);
        }
        $this->events()->dispatchEvent('onResponseReady', $response);
        $this->events()->dispatchEvent('onResponseReturn', $response);
        return $response;
    }

    public function cache(): Cache\CacheProvider
    {
        return $this->cache;
    }

    public function images(): Images\ImageProvider
    {
        return $this->images;
    }

    public function dom(): DOM\DOMProvider
    {
        return $this->dom;
    }

    public function assets(): Assets\AssetProvider
    {
        return $this->assets;
    }

    public function templates(): Templates\TemplateProvider
    {
        return $this->templates;
    }

    public function config(string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key];
    }

    public function events(): Events\EventProvider
    {
        return $this->events;
    }

    public function content(): Content\ContentProvider
    {
        return $this->content;
    }

    public function pages(): Pages\PageProvider
    {
        return $this->pages;
    }

    public function hash(): string
    {
        return hash('md5', filemtime(__DIR__) . $this->config->hash());
    }

    /**
     * Begin a new context either by optionally providing a Config object
     * or existing Leafcutter object.
     *
     * @param Leafcutter|Config $specified
     * @param Logger $logger
     * @return Leafcutter
     */
    public static function beginContext($specified = null, Logger $logger = null): Leafcutter
    {
        if ($specified instanceof Leafcutter) {
            self::$instances[] = $specified;
        } elseif ($specified instanceof Config\Config) {
            self::$instances[] = new Leafcutter($specified, $logger);
        } else {
            self::$instances[] = new Leafcutter(null, $logger);
        }
        return self::get();
    }

    public static function get(): Leafcutter
    {
        return end(self::$instances);
    }

    public static function endContext()
    {
        array_pop(self::$instances);
    }
}
