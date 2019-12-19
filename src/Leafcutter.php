<?php
namespace Leafcutter;

use Leafcutter\Common\Url;
use Leafcutter\Common\UrlInterface;

class Leafcutter
{
    protected $config;
    protected $logger;
    protected $content;
    protected $pages;
    protected $markdown;
    protected $hooks;
    protected $assets;
    protected $themes;
    protected $templates;
    protected $confHash = '00000000';

    public function __construct(Config\Config $config)
    {
        $this->config = $config;
        $this->cache = new Cache\CacheProvider($this);
        $this->logger = new \Monolog\Logger('leafcutter');
        $this->markdown = new Markdown\MarkdownProvider();
        $this->hooks = new Hooks\HookProvider($this);
        $this->content = new Content\ContentProvider($this);
        $this->pages = new Content\Pages\PageProvider($this);
        $this->assets = new Content\Assets\AssetProvider($this);
        $this->dom = new DOM\DOMProvider($this);
        $this->themes = new Themes\ThemeProvider($this);
        $this->templates = new Templates\TemplateProvider($this);
        $this->images = new Content\Images\ImageProvider($this);
        $this->mutateHash($this->config);
        $this->mutateHash(filemtime(__DIR__));
    }

    public function mutateHash($input)
    {
        $this->confHash = hash('crc32', $this->confHash.serialize($input));
    }

    public function hash() : string
    {
        return $this->confHash;
    }

    public function images() : Content\Images\ImageProvider
    {
        return $this->images;
    }

    public function cache(string $namespace=null, int $ttl=60, array $tags=[]) : Cache\CacheInterface
    {
        if (!$namespace) {
            return $this->cache;
        } else {
            return $this->cache->workspace($namespace, $ttl, $tags);
        }
    }

    public function themes() : Themes\ThemeProvider
    {
        return $this->themes;
    }

    public function templates() : Templates\TemplateProvider
    {
        return $this->templates;
    }

    public function dom() : DOM\DOMProvider
    {
        return $this->dom;
    }

    public function config(string $key)
    {
        return $this->config[$key];
    }

    public function assets() : Content\Assets\AssetProvider
    {
        return $this->assets;
    }

    public function markdown() : Markdown\MarkdownProvider
    {
        return $this->markdown;
    }

    public function pages() : Content\Pages\PageProvider
    {
        return $this->pages;
    }

    public function content() : Content\ContentProvider
    {
        return $this->content;
    }

    public function logger() : \Monolog\Logger
    {
        return $this->logger;
    }

    public function hooks() : Hooks\HookProvider
    {
        return $this->hooks;
    }

    public function get($url, $context=null)
    {
        //strip base URL
        if (strpos($url, $this->getBase()) === 0) {
            $url = substr($url, strlen($this->getBase()));
        }
        return $this->pages()->get($url, $context)
            ?? $this->assets()->get($url, $context);
    }

    public function handleRequest(Request $request) : Response
    {
        $hash = $this->content()->hash($request->getFullContext());
        return $this->cache->get(
            'handleRequest.'.hash('crc32', serialize([$request,$hash])),
            function () use ($request) {
                $response = Response::createFrom($request);
                try {
                    if (!($page = $this->pages()->get($request->getPath()))) {
                        $response->setStatus(404);
                        $page = $this->pages()->getErrorPage(404, "Page not found.", $request->getContext());
                    }
                } catch (\Throwable $th) {
                    $this->logger()->error('Leafcutter: handleRequest: Exception: '.$th->getMessage());
                    $response->setStatus(500);
                    $page = $this->pages()->getErrorPage(500, "Exception while handling request.", $request->getContext());
                }
                // call hooks when page is ready
                $page = $this->hooks()->dispatchAll('onResponsePageReady', $page);
                // put page content into template
                $content = $this->templates()->applyToPage($page);
                $content = $this->dom()->html($content);
                $response->setContent($content);
                return $response;
            },
            $this->config('cache.ttl.handle_request')
        );
    }

    public function outputResponse(Response $response)
    {
        echo $response->getContent();
    }

    public function getBase() : string
    {
        return $this->base ?? $this->generateBase();
    }

    public function normalizeUrl(string $path, $context='/') : ?Url
    {
        $ocontext = $context;
        if (!($context instanceof UrlInterface)) {
            $context = preg_replace('@/[^/]+$@', '/', $context);
            $context = Url::createFromString($context);
        }
        try {
            $this->logger()->debug("normalizeUrl: $path, $ocontext");
            $url = Url::createFromString($path, $context);
            $this->logger()->debug("normalizeUrl: $path, $ocontext => $url");
        } catch (\Throwable $th) {
            $this->logger()->notice("normalizeUrl: failed: $path, $ocontext: ".$th->getMessage());
            throw $th;
        }
        return $url;
    }

    public function prepareJS(string $js) : string
    {
        $this->logger->debug('Leafcutter: prepareJS: '.strlen($js).' bytes');
        //minify if enabled, and return
        if ($this->config('js.minify')) {
            $js = \JShrink\Minifier::minify($js);
        }
        return $js;
    }

    public function prepareCSS(string $css, string $context="/") : string
    {
        $this->logger->debug('Leafcutter: prepareCSS: $context: '.strlen($css).' bytes');
        //resolve variables in CSS
        $css = $this->themes()->variables()->resolve($css, '${', '}');
        //resolve @import URLs
        $css = \preg_replace_callback(
            "/@import (url)?([\"']?)([^\"']+)([\"']?)( ([^;]+))?;/",
            function ($matches) use ($context) {
                // quotes must match or it's malformed
                if ($matches[2] != $matches[4]) {
                    return $matches[0];
                }
                //get the parts neeeded
                $url = $matches[3];
                $mediaQuery = @$matches[6];
                //get url from matches
                if ($asset = $this->assets->get($url, $context)) {
                    if ($asset->isEmpty()) {
                        return '/* '.$url.' is empty, import skipped */';
                    } else {
                        return '@import url('.$asset->getOutputUrl().') '.$mediaQuery.';';
                    }
                } else {
                    return $matches[0];
                }
            },
            $css
        );
        //resolve url() URLs
        $css = \preg_replace_callback(
            "/url\(([\"']?)([^\"'\)]+)([\"']?)\)/",
            function ($matches) use ($context) {
                // quotes must match or it's malformed
                if ($matches[1] != $matches[3]) {
                    return $matches[0];
                }
                //get url from matches
                if ($asset = $this->assets->get($matches[2], $context)) {
                    return 'url('.$asset->getOutputUrl().')';
                } else {
                    return $matches[0];
                }
            },
            $css
        );
        //minify if enabled, and return
        if ($this->config('css.minify')) {
            $css = $this->cssMin()->run($css);
        }
        return $css;
    }

    protected function generateBase() : string
    {
        $request = Request::createFromGlobals();
        return $request->getBase();
    }

    protected static function cssMin()
    {
        static $cssMin = null;
        if ($cssMin === null) {
            $cssMin = new \tubalmartin\CssMin\Minifier;
            $cssMin->setMemoryLimit('256M');
            $cssMin->setMaxExecutionTime(120);
            $cssMin->setPcreBacktrackLimit(3000000);
            $cssMin->setPcreRecursionLimit(150000);
            $cssMin->keepSourceMapComment(false);
            $cssMin->setLinebreakPosition(1);
        }
        return $cssMin;
    }
}
