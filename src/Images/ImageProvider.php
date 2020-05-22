<?php
namespace Leafcutter\Images;

use Imagine;
use Leafcutter\Assets\AssetFileEvent;
use Leafcutter\Common\Filesystem;
use Leafcutter\DOM\DOMEvent;
use Leafcutter\Leafcutter;
use Leafcutter\Response;
use Leafcutter\URL;

class ImageProvider
{
    const OUTPUT_FORMATS = [
        'jpg', 'gif', 'png', 'wbmp', 'xbm', 'webp', 'bmp', 'ico',
    ];

    private $leafcutter;
    private $saveOptions = [
        'jpeg_quality' => 90,
        'png_compression_level' => 9,
        'webp_quality' => 90,
    ];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->leafcutter->events()->addSubscriber($this);
    }

    /**
     * An event listener to specifically intercept requests for favicon.ico,
     * and build a response that redirects to a public asset URL.
     *
     * @param URL $url
     * @return Response|null
     */
    public function onResponseURL(URL $url): ?Response
    {
        if ($url->siteFullPath() == 'favicon.ico') {
            if ($icons = $this->search("favicon.*")) {
                $icon = $icons->tail();
                $asset = $icon->query(['format' => 'ico', 'ico_sizes' => '16,32']);
                $response = new Response();
                $response->setTemplate(null);
                $response->redirect($asset->publicUrl());
                return $response;
            }
        }
        return null;
    }

    public function onDOMElement_img(DOMEvent $event)
    {
        $this->leafcutter->dom()->prepareLinkAttribute($event, 'src', false);
    }

    public function search($search, array $query = [], string $namespace = null): Gallery
    {
        $images = [];
        $url = new URL($search);
        foreach ($this->leafcutter->content()->files($url->sitePath(), $namespace ?? $url->siteNamespace()) as $file) {
            $url = $file->url();
            $url->setQuery($query);
            $images["$url"] = $this->get($url);
        }
        $images = array_filter($images);
        return new Gallery($images);
    }

    public function get(URL $url): ?Image
    {
        $path = $url->sitePath();
        $namespace = $url->siteNamespace();
        $file = $this->leafcutter->content()->files($path, $namespace);
        if ($file) {
            return new Image($url, $this->leafcutter);
        } else {
            return null;
        }
    }

    public function generate(string $source, string $dest, array $query, $overwrite = false)
    {
        if (!$overwrite && is_file($dest)) {
            return;
        }
        //set up tools, try to allocate extra memory
        ini_set('memory_limit', '1024M');
        $fs = new Filesystem();
        $temp = $fs->tempFile($query['format']);
        // first crop/fit, so that we're working on a smaller image
        // from here on out if possible
        if (@$query['crop']) {
            $query['crop'] = explode('x', $query['crop']);
            $image = $this->openCrop($source, $query['crop'][0], $query['crop'][1]);
        } elseif (@$query['fit']) {
            $query['fit'] = explode('x', $query['fit']);
            $image = $this->openFit($source, $query['fit'][0], $query['fit'][1]);
        } else {
            $image = $this->open($source);
        }
        // grayscale
        if (@$query['grayscale']) {
            $image->effects()->grayscale();
        }
        // colorize
        if (@$query['colorize']) {
            $image->effects()->colorize(
                $image->palette()->color($query['colorize'])
            );
        }
        // blur
        if (@$query['blur']) {
            $image->effects()->blur($query['blur']);
        }
        // outputting temp files
        if ($query['format'] == 'ico') {
            // special icon handling
            $temp_png = $fs->tempFile('png');
            $image->save($temp_png, $this->saveOptions);
        } else {
            // general handling
            $image->save($temp, $this->saveOptions);
        }
        // special icon handling
        if ($query['format'] == 'ico') {
            $sizes = array_map(function ($e) {
                return [$e, $e];
            }, $query['ico_sizes']);
            if (count($sizes) == 1) {
                $sizes = $sizes[0];
            }
            $ico_lib = new \PHP_ICO($temp_png, $sizes);
            $ico_lib->save_ico($temp);
            \unlink($temp_png);
        }

        // move temp file to final destination
        $fs->move($temp, $dest, true);
    }

    protected function buildAsset(AssetFileEvent $event): ?ImageAsset
    {
        $url = $event->url();
        $query = $this->normalizeQuery($event->path(), $url->query());
        $url->setQuery($query);
        $source = $this->buildMaxResolutionSource($event->path());
        $asset = new ImageAsset($url, $source);
        //explicitly set filename
        $filename = $asset->filename();
        $filename = preg_replace('@\..+?$@', '.' . $query['format'], $filename);
        $asset->setFilename($filename);
        return $asset;
    }

    protected function buildMaxResolutionSource(string $source): string
    {
        $hash = hash_file('md5', $source);
        $hash = preg_replace("/^(.{1})(.{2})(.{2})/", "$1/$2/$3/", $hash) . '/';
        $dest = $this->leafcutter->config('assets.output.directory') . $hash . basename($source) . '-maxresolution.png';
        $this->generate($source, $dest, $this->normalizeQuery($dest, $this->leafcutter->config("images.presets.maxresolution")));
        return $dest;
    }

    protected function normalizeQuery(string $source, array $query): array
    {
        $validQuery = [];

        // only one of fit/crop is allowed
        if (isset($query['crop']) && preg_match('/^([1-9][0-9]*)(x([1-9][0-9]*))?$/', $query['crop'], $matches)) {
            $validQuery['crop'] = implode('x', [
                intval($matches[1]),
                intval(@$matches[3] ? $matches[3] : ($matches[1])),
            ]);
            unset($validQuery['fit']);
        } elseif (isset($query['fit']) && preg_match('/^([1-9][0-9]*)(x([1-9][0-9]*))?$/', $query['fit'], $matches)) {
            $validQuery['fit'] = implode('x', [
                intval($matches[1]),
                intval(@$matches[3] ? $matches[3] : ($matches[1])),
            ]);
        }
        if (isset($validQuery['crop']) && isset($validQuery['fit'])) {
            unset($validQuery['fit']);
        }

        // option to set format
        preg_match('@\.([a-z0-9]+)$@', $source, $matches);
        $query['format'] = $query['format'] ?? @$matches[1] ?? 'png';
        if (in_array(@$query['format'], static::OUTPUT_FORMATS)) {
            $validQuery['format'] = $query['format'];
        }

        // special icon rules
        if (@$validQuery['format'] == 'ico') {
            unset($validQuery['fit']);
            unset($validQuery['crop']);
            if (!@$query['ico_sizes']) {
                $validQuery['ico_sizes'] = [32];
            } else {
                $validQuery['ico_sizes'] = explode(',', @$query['ico_sizes'] ?? '32');
                $validQuery['ico_sizes'] = array_map('\intval', $validQuery['ico_sizes']);
                asort($validQuery['ico_sizes']);
                $validQuery['ico_sizes'] = $validQuery['ico_sizes'] ?? [32];
            }
            $validQuery['crop'] = implode('x', [end($validQuery['ico_sizes']), end($validQuery['ico_sizes'])]);
        }

        // option to grayscale
        $validQuery['grayscale'] = (@$query['grayscale'] == 'true');

        // option to colorize
        if (isset($query['colorize']) && preg_match('/^#([0-9a-f]{3,3}){1,2}$/i', $query['colorize'])) {
            $validQuery['colorize'] = $query['colorize'];
        }

        // option to blur
        $validQuery['blur'] = intval(@$query['blur']);

        //return valid query only
        return $validQuery;
    }

    protected function open($source)
    {
        return $this->imagine()->open($source);
    }

    protected function openCrop($source, int $width, int $height)
    {
        $size = new Imagine\Image\Box($width, $height);
        $mode = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
        return $this->open($source)->thumbnail($size, $mode);
    }

    protected function openFit($source, int $width, int $height)
    {
        $size = new Imagine\Image\Box($width, $height);
        $mode = Imagine\Image\ImageInterface::THUMBNAIL_INSET;
        return $this->open($source)->thumbnail($size, $mode);
    }

    protected function imagine()
    {
        static $imagine;
        if (!$imagine) {
            switch ($this->leafcutter->config('images.driver')) {
                case 'imagick':
                    $imagine = new Imagine\Imagick\Imagine();
                    break;
                case 'gmagick':
                    $imagine = new Imagine\Gmagick\Imagine();
                    break;
                default:
                    $imagine = new Imagine\Gd\Imagine();
                    break;
            }
        }
        return $imagine;
    }

    public function onAssetFile_png(AssetFileEvent $event): ?ImageAsset
    {
        return $this->buildAsset($event);
    }

    public function onAssetFile_jpg(AssetFileEvent $event): ?ImageAsset
    {
        return $this->buildAsset($event);
    }

    public function onAssetFile_jpeg(AssetFileEvent $event): ?ImageAsset
    {
        return $this->buildAsset($event);
    }

    public function onAssetFile_gif(AssetFileEvent $event): ?ImageAsset
    {
        return $this->buildAsset($event);
    }

    public function onAssetFile_wbmp(AssetFileEvent $event): ?ImageAsset
    {
        return $this->buildAsset($event);
    }

    public function onAssetFile_xbm(AssetFileEvent $event): ?ImageAsset
    {
        return $this->buildAsset($event);
    }

    public function onAssetFile_webp(AssetFileEvent $event): ?ImageAsset
    {
        return $this->buildAsset($event);
    }

    public function onAssetFile_bmp(AssetFileEvent $event): ?ImageAsset
    {
        return $this->buildAsset($event);
    }
}
