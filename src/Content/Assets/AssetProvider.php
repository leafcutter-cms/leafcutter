<?php
namespace Leafcutter\Content\Assets;

use Leafcutter\Leafcutter;

class AssetProvider
{
    protected $leafcutter;
    protected $systemBuilder;
    protected $cache;

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->systemBuilder = new SystemAssetBuilder($this->leafcutter);
        $this->leafcutter->hooks()->addSubscriber($this->systemBuilder);
        $this->cache = $this->leafcutter->cache(
            'AssetProvider',
            $this->leafcutter->config('cache.ttl.asset_provider')
        );
    }

    public function allowedExtensions()
    {
        return array_keys(
            array_filter($this->leafcutter->config('assets.allowed_extensions'))
        );
    }

    public function get(string $path, $context='/') : ?AssetInterface
    {
        return $this->cache->get(
            'get.'.hash('crc32', serialize([$path,$context])),
            function () use ($path,$context) {
                $this->leafcutter->logger()->debug("AssetProvider: get: $path: $context");
                try {
                    // normalize URL/context, and the resulting path
                    $url = $this->leafcutter->normalizeUrl($path, $context);
                } catch (\Throwable $th) {
                    return null;
                }
                // hooks by prefix
                if ($prefix = $url->getPrefix()) {
                    if ($asset = $this->leafcutter->hooks()->dispatchFirst('onPrefixedAssetGet_'.$prefix, $url)) {
                        return $this->finalizeAsset($asset);
                    }
                }
                // general hook
                if ($prefix = $url->getPrefix()) {
                    if ($asset = $this->leafcutter->hooks()->dispatchFirst('onAssetGet', $url)) {
                        return $this->finalizeAsset($asset);
                    }
                }
                // look in normal filesystem
                $path = $url->getFullPath();
                if ($hits = $this->list($path, $url->getArgs())) {
                    return reset($hits);
                } else {
                    return null;
                }
            }
        );
    }

    public function list(string $glob, array $args=[]) : array
    {
        return $this->cache->get(
            'list.'.hash('crc32', serialize([$glob,$args])),
            function () use ($glob,$args) {
                $this->leafcutter->logger()->debug("AssetProvider: list: $glob: ".http_build_query($args));
                $glob = $this->normalizeGlob($glob);
                // build an array keyed by normalized paths, each containing an array of potential files
                $files = [];
                $matches = $this->leafcutter->content()->list($glob);
                foreach ($matches as $path => $file) {
                    if (!isset($files[$path]) && is_file($file)) {
                        $files[$path] = $file;
                    }
                }
                // turn the array of paths and candidate files into an array of built assets
                array_walk(
                    $files,
                    function (&$value, $path) use ($args) {
                        $value = $this->getFromFile($path, $value, $args);
                    }
                );
                // return the filtered output
                return array_filter($files);
            }
        );
    }

    public function getFromFile(string $path, string $candidate, array $args=[]) : ?AssetInterface
    {
        return $this->cache->get(
            'getFromFile.'.hash('crc32', serialize([$path,$candidate,$args])),
            function () use ($path,$candidate,$args) {
                $url = $this->leafcutter->normalizeUrl($path);
                $url->setArgs($args);
                $this->leafcutter->logger()->debug("AssetProvider: getFromFile: $path: $url: ".$candidate);
                $ext = strtolower(preg_replace('@^.+\.([a-zA-Z0-9]+)$@', '$1', $candidate));
                if ($ext) {
                    $asset = $this->leafcutter->hooks()->dispatchFirst('onAssetFile_'.$ext, [$url,$candidate])
                        ?? $this->leafcutter->hooks()->dispatchFirst('onAssetFile_unmatched', [$url,$candidate]);
                    if ($asset) {
                        return $this->finalizeAsset($asset);
                    }
                }
                return null;
            }
        );
    }

    public function getFromString(string $path, string $content) : AssetInterface
    {
        return $this->cache->get(
            'getFromString.'.hash('crc32', serialize([$path,$content])),
            function () use ($path,$content) {
                $url = $this->leafcutter->normalizeUrl($path);
                $this->leafcutter->logger()->debug("AssetProvider: getFromString: $url: ".strlen($content)." bytes");
                $asset = new StringAsset($url, $content);
                return $this->finalizeAsset($asset);
            }
        );
    }

    protected function finalizeAsset(AssetInterface $asset) : AssetInterface
    {
        $out = $asset;
        // do internal preparation/minification/whatnot of CSS and JS
        if ($out->getExtension() == 'css') {
            $css = $this->leafcutter->prepareCSS($out->getContent(), $out->getUrl()->getFullContext());
            $out = new StringAsset($out->getUrl(), $css);
            $out->setExtension('css');
            $out->setDateModified($asset->getDateModified());
        }
        if ($out->getExtension() == 'js') {
            $js = $this->leafcutter->prepareJS($out->getContent(), $out->getUrl()->getFullContext());
            $out = new StringAsset($out->getUrl(), $js);
            $out->setExtension('js');
            $out->setDateModified($asset->getDateModified());
        }
        // run hooks to allow further modification of assets
        $out = $this->leafcutter->hooks()->dispatchAll('onAssetReady', $out);
        // figure out output context/file, and finalize the asset
        $context = $this->outputContext($out);
        $outputFile = $this->outputFile($out);
        $ext = strtolower(preg_replace('/^.*\./', '', $outputFile));
        if (!in_array($ext, $this->allowedExtensions())) {
            throw new \Exception("Asset's output file isn't an allowed file extension. Tried to use $ext");
        }
        $out->setOutputContext($context);
        $out->setOutputBaseUrl(
            $this->leafcutter->config('assets.output_base') ?? $this->leafcutter->getBase()
        );
        $out->setOutputFile($outputFile);
        return $out;
    }

    protected function outputHashDirectory(AssetInterface $asset)
    {
        $hash = hash('crc32', $asset->getHash().hash('crc32', get_class($asset)));
        $hash = preg_replace('@^(..)(..)(..)(.+)$@', '$1/$2/$3/$4/', $hash);
        return $hash;
    }

    protected function outputFile(AssetInterface $asset)
    {
        return $this->leafcutter->config('assets.output_directory').$this->outputHashDirectory($asset).$asset->getFilename();
    }

    protected function outputContext(AssetInterface $asset) : string
    {
        return $this->leafcutter->config('assets.output_context').$this->outputHashDirectory($asset);
    }

    protected function normalizeGlob(string $path) : string
    {
        $n = $path;
        $this->leafcutter->logger()->debug("AssetProvider: normalizeGlob: $path => $n");
        return $n;
    }
}
