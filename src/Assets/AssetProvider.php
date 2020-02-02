<?php
namespace Leafcutter\Assets;

use Leafcutter\Common\Collection;
use Leafcutter\Leafcutter;
use Leafcutter\URL;
use Leafcutter\URLFactory;

class AssetProvider
{
    private $leafcutter;

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->leafcutter->events()->addSubscriber($this);
    }

    /**
     * Preprocesses CSS assets, to resolve URLs
     *
     * @param AssetEvent $event
     * @return void
     */
    public function onAssetReady_css(AssetEvent $event)
    {
        $css = $event->asset()->content();
        // resolve @import URLs
        $css = \preg_replace_callback(
            "/@import (url)?([\"']?)([^\"']+)([\"']?)( ([^;]+))?;/",
            function ($matches) {
                // quotes must match or it's malformed
                if ($matches[2] != $matches[4]) {
                    return $matches[0];
                }
                //get the parts neeeded
                $url = new URL($matches[3]);
                $mediaQuery = @$matches[6];
                //get url from matches
                if ($asset = $this->get($url)) {
                    return '@import url(' . $asset->publicUrl() . ') ' . $mediaQuery . ';';
                } else {
                    return $matches[0];
                }
            },
            $css
        );
        // resolve url() URLs
        $css = \preg_replace_callback(
            "/url\(([\"']?)([^\"'\)]+)([\"']?)\)/",
            function ($matches) {
                // quotes must match or it's malformed
                if ($matches[1] != $matches[3]) {
                    return $matches[0];
                }
                //get url from matches
                $url = new URL($matches[2]);
                if ($asset = $this->get($url)) {
                    return 'url(' . $asset->publicUrl() . ')';
                } else {
                    return $matches[0];
                }
            },
            $css
        );
        // make new asset if CSS is changed
        if ($css != $event->asset()->content()) {
            $event->setAsset(new StringAsset($event->url(), $css));
        }
    }

    /**
     * Compiles scss files into CSS, including resolving import paths
     * using Leafcutter's content provider. Also imports theme variables
     * from the theme provider.
     *
     * @param AssetFileEvent $event
     * @return AssetInterface|null
     */
    public function onAssetFile_scss(AssetFileEvent $event): ?AssetInterface
    {
        $css = \file_get_contents($event->path());
        $compiler = new \ScssPhp\ScssPhp\Compiler([]);
        //set up variables
        $compiler->setVariables(
            $this->leafcutter->theme()->variables()->list()
        );
        // set up import callback for getting import files
        $compiler->setImportPaths([]);
        $compiler->addImportPath(function ($path) {
            // try to include a raw scss file if possible
            $url = new URL($path);
            foreach ($this->leafcutter->content()->files($url->sitePath(), $url->siteNamespace()) as $file) {
                if (substr($file->path(), -5) == '.scss') {
                    return $file->path();
                }
            }
            // otherwise try to find CSS-ey files
            if ($asset = $this->get($url) && $asset->extension() == 'css') {
                return $asset->outputFile();
            }
        });
        // set context, compile and output
        URLFactory::beginContext($event->url());
        $css = $compiler->compile($css);
        URLFactory::endContext();
        return new StringAsset($event->url(), $css);
    }

    public function onAssetFile_UNMATCHED(AssetFileEvent $event): ?AssetInterface
    {
        $ext = $event->url()->extension();
        if ($this->leafcutter->config("assets.passthrough_extensions.$ext")) {
            return new FileAsset($event->url(), $event->path());
        }
        return null;
    }

    public function search(string $glob, string $namespace = null): Collection
    {
        $assets = [];
        foreach ($this->leafcutter->content()->files($glob, $namespace) as $file) {
            $url = $file->url();
            $assets["$url"] = $this->get($file->url());
        }
        $assets = array_filter($assets);
        return new Collection($assets);
    }

    public function getFromString(string $content, URL $url = null, string $extension = null): AssetInterface
    {
        if (!$url) {
            $url = new URL('@/@stringassets/' . hash('crc32', $content) . '.' . $extension);
        }
        $asset = new StringAsset($url, $content);
        return $this->finalize($asset, $url);
    }

    public function get(URL $url): ?AssetInterface
    {
        // skip non-site URLs
        if (!$url->inSite()) {
            return null;
        }
        // allow assetss to fully bypass entire return system
        if ($asset = $this->leafcutter->events()->dispatchFirst('onAssetURL', $url)) {
            return $asset;
        }
        // attempt to make a asset from content files
        $asset = null;
        $path = $this->searchPath($url->sitePath());
        $namespace = $url->siteNamespace();
        $files = $this->leafcutter->content()->files($path, $namespace);
        URLFactory::beginContext($url);
        foreach ($files as $file) {
            $asset = $this->leafcutter->events()->dispatchFirst(
                'onAssetFile_' . $file->extension(),
                new AssetFileEvent($file->path(), $url)
            ) ?? $this->leafcutter->events()->dispatchFirst(
                'onAssetFile_UNMATCHED',
                new AssetFileEvent($file->path(), $url)
            );
            if ($asset) {
                break;
            }
        }
        URLFactory::endContext();
        // return finalized
        return $this->finalize($asset, $url);
    }

    protected function finalize(?AssetInterface $asset, URL $url)
    {
        URLFactory::beginContext($url);
        // return asset after dispatching events
        if ($asset) {
            //set public URL and trigger pre-generation events
            $asset->setPublicUrl($this->generatePublicUrl($asset));
            $event = new AssetEvent($asset, $url);
            $this->leafcutter->events()->dispatchEvent(
                'onAssetReady_' . $asset->publicUrl()->extension(),
                $event
            );
            $this->leafcutter->events()->dispatchEvent(
                'onAssetReady',
                $event
            );
            //set output file and URL
            $event->asset()->setPublicUrl(
                $this->generatePublicUrl($asset)
            );
            $event->asset()->setOutputFile(
                $this->generateOutputPath($asset)
            );
            $this->leafcutter->events()->dispatchEvent('onAssetReturn', $event);
            URLFactory::endContext();
            return $event->asset();
        } else {
            URLFactory::endContext();
            return null;
        }
    }

    protected function generatePublicUrl(AssetInterface $asset): URL
    {
        $base = $this->leafcutter->config('assets.output.url');
        $path = $this->hashPath($asset) . $asset->filename();
        return new URL($base . $path);
    }

    protected function generateOutputPath(AssetInterface $asset): string
    {
        $base = $this->leafcutter->config('assets.output.directory');
        $path = $this->hashPath($asset) . $asset->filename();
        return $base . $path;
    }

    protected function hashPath(AssetInterface $asset): string
    {
        $path = preg_replace("/^(.{1})(.{2})(.{2})/", "$1/$2/", $asset->hash()) . '/';
        return $path;
    }

    protected function searchPath(string $path): string
    {
        if (preg_match('@\.([a-z0-9]+)$@', $path, $matches)) {
            $extension = $matches[1];
            if ($alts = $this->leafcutter->config("assets.equivalent_extensions.$extension")) {
                array_unshift($alts, $extension);
                $path = substr($path, 0, strlen($path) - strlen($extension)) . '{' . implode(',', $alts) . '}';
            }
        }
        return $path;
    }
}
