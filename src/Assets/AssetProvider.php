<?php
namespace Leafcutter\Assets;

use Leafcutter\Common\Collection;
use Leafcutter\DOM\DOMEvent;
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

    public function onDOMElement_link(DOMEvent $event)
    {
        $this->leafcutter->dom()->prepareLinkAttribute($event, 'href', false);
    }

    public function onDOMElement_source(DOMEvent $event)
    {
        $this->leafcutter->dom()->prepareLinkAttribute($event, 'src', false);
    }

    public function onDOMElement_script(DOMEvent $event)
    {
        $this->leafcutter->dom()->prepareLinkAttribute($event, 'src', false);
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
            $url = new URL('@/@stringassets/' . hash('md5', $content) . '.' . $extension);
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
        $path = '/' . preg_replace("/^(.{1})(.{2})(.{2})/", "$1/$2/$3/", $asset->hash()) . '/';
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
