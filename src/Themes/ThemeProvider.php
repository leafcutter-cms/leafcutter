<?php
namespace Leafcutter\Themes;

use Flatrr\Config\Config;
use Leafcutter\Assets\AssetEvent;
use Leafcutter\Assets\AssetFileEvent;
use Leafcutter\Assets\AssetInterface;
use Leafcutter\Assets\StringAsset;
use Leafcutter\Leafcutter;
use Leafcutter\URL;
use Leafcutter\URLFactory;
use MatthiasMullie\Minify;

class ThemeProvider
{
    protected $leafcutter;
    protected $directories = [];
    protected $assets = [];
    protected $mediaAliases = [
        'blocking' => 'all',
        'library' => 'all',
        'page' => 'all',
        'site' => 'all',
        'theme' => 'all',
    ];
    protected $cssMedias = [
        'library', //loads first, basically where anything external should go
        'blocking', //loads first after library, used to get first in line for inlining
        'theme', //theme
        'all', //media queries are fine, but it's better to keep medias in separate files
        'screen',
        'print',
        'speech',
        'site', //things that are site-specific, may include media queries
        'page', //things that are page-specific, may include media queries
    ];
    protected $jsMedias = [
        'library', //loads first
        'theme', //only exists to give a section before "site" to be used by themes
        'all', //generic location
        'site', //site-specific
        'page', //page-specific
    ];
    protected $inlined = 0;
    protected $loadedThemes = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->variables = new ThemeVariables($leafcutter);
        $this->addDirectory(__DIR__ . '/themes');
        $leafcutter->events()->addSubscriber($this);
    }

    /**
     * Preprocesses CSS assets
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
                if ($asset = $this->leafcutter->assets()->get($url)) {
                    return 'url(' . $asset->publicUrl() . ')';
                } else {
                    return $matches[0];
                }
            },
            $css
        );
        // minify if configured to do so
        if ($this->leafcutter->config('theme.css.minify')) {
            $minifier = new Minify\CSS($css);
            $css = $minifier->minify();
        }
        // make new asset if CSS is changed
        if ($css != $event->asset()->content()) {
            $event->setAsset(new StringAsset($event->url(), $css));
        }
    }

    /**
     * Preprocesses JS assets
     *
     * @param AssetEvent $event
     * @return void
     */
    public function onAssetReady_js(AssetEvent $event)
    {
        $js = $event->asset()->content();
        // minify if configured to do so
        if ($this->leafcutter->config('theme.js.minify')) {
            $minifier = new Minify\JS($js);
            $js = $minifier->minify();
        }
        // make new asset if JS is changed
        if ($js != $event->asset()->content()) {
            $event->setAsset(new StringAsset($event->url(), $js));
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

    public function loadTheme(string $name)
    {
        $name = preg_replace('/[^a-z0-9\-_]/', '', $name);
        foreach ($this->directories as $dir) {
            $dir = "$dir/$name";
            $yaml = "$dir/theme.yaml";
            if (is_file($yaml)) {
                $this->doLoadTheme($dir, $yaml);
            }
        }
    }

    protected function doLoadTheme($dir, $yaml)
    {
        $themeName = basename($dir);
        if (in_array($dir, $this->loadedThemes)) {
            return;
        }
        $this->loadedThemes[] = $dir;
        $config = new Config([
            'theme.prefix' => "/~themes/$themeName/",
        ]);
        $config->readFile($yaml);
        //set up advanced files (these don't get prefixed by theme name)
        foreach ($config['advanced'] ?? [] as $k => $f) {
            $f['name'] = $k;
            $this->addAsset(
                $f['type'],
                $f['url'],
                $f['name'],
                @$f['integrity'],
                boolval(@$f['async']),
                $f['media']
            );
        }
        //set up internal CSS files
        foreach ($config['css'] ?? [] as $media => $files) {
            foreach ($files as $file) {
                $url = $file;
                if (substr($url, 0, 1) != '/') {
                    $url = $config['theme.prefix'] . $url;
                }
                $name = "theme: $themeName: $media: $file";
                $this->addAsset(
                    'css',
                    $url, //url
                    $name, //name
                    null,
                    false,
                    $media
                );
            }
        }
        //set up internal JS files
        foreach ($config['js'] ?? [] as $media => $files) {
            foreach ($files as $file) {
                $url = $file;
                if (substr($url, 0, 1) != '/') {
                    $url = $config['theme.prefix'] . $url;
                }
                $name = "theme: $themeName: $media: $file";
                $this->addAsset(
                    'js',
                    $url,
                    $name,
                    null,
                    false,
                    $media
                );
            }
        }
    }

    public function addDirectory(string $dir)
    {
        if ($dir = realpath($dir)) {
            //skip already-added directories
            if (in_array($dir, $this->directories)) {
                return;
            }
            //add new dir to front
            array_unshift($this->directories, $dir);
            //add directory to "themes" namespace
            $this->leafcutter->content()->addDirectory($dir, 'themes');
        }
    }

    public function variables(): ThemeVariables
    {
        return $this->variables;
    }

    public function onResponseContentReady($response)
    {
        // load _site CSS and JS files files
        $context = $response->url()->siteFullPath();
        // load root _site files
        if ($asset = $this->leafcutter->assets()->get(new URL("/_site.css"))) {
            $this->addCss($asset->hash(), $asset, 'site');
        }
        if ($asset = $this->leafcutter->assets()->get(new URL("/_site.js"))) {
            $this->addJs($asset->hash(), $asset, 'site');
        }
        // load all _site files for parent directories
        $context = explode('/', $context);
        $check = '';
        foreach ($context as $c) {
            $check .= "$c/";
            if ($asset = $this->leafcutter->assets()->get(new URL("/{$check}_site.css"))) {
                $this->addCss($asset->hash(), $asset, 'site');
            }
            if ($asset = $this->leafcutter->assets()->get(new URL("/{$check}_site.js"))) {
                $this->addJs($asset->hash(), $asset, 'site');
            }
        }
        // load _page CSS and JS files
        $context = $response->url()->siteFullPath();
        $context = preg_replace('@[^/]+$@', '', $context);
        if ($asset = $this->leafcutter->assets()->get(new URL("_page.css"))) {
            $this->addCss($asset->hash(), $asset, 'page');
        }
        if ($asset = $this->leafcutter->assets()->get(new URL("_page.js"))) {
            $this->addJs($asset->hash(), $asset, 'page');
        }
    }

    public function onTemplateInjection_head()
    {
        echo $this->getHeadHtml() . PHP_EOL;
    }

    public function getHeadHtml(): string
    {
        //resolve asset objects wherever possible
        array_walk(
            $this->assets,
            function (&$v, $k) {
                if (is_string($v['source'])) {
                    $v['source'] = $this->leafcutter->assets()->get(new URL($v['source'])) ?? $v['source'];
                }
            }
        );
        //produce output
        $this->inlined = 0;
        $html = [];
        foreach ($this->cssMedias as $m) {
            $html[] = $this->cssHtml($this->filter('css', $m));
        }
        //javascript may either load manually, or with an inline loader
        if ($this->leafcutter->config('theme.js.inline_loader')) {
            // build an inline script to asynchronously load all the scripts, but in the correct order
            $html[] = $this->jsLoader($this->filter('js'));
        } else {
            // load individual scripts as HTML, in a very normal way
            foreach ($this->jsMedias as $m) {
                $html[] = $this->jsHtml($this->filter('js', $m, false));
                $html[] = $this->jsHtml($this->filter('js', $m, true));
            }
        }
        return implode(PHP_EOL, array_filter($html));
    }

    protected function jsLoader($arr)
    {
        $script = file_get_contents(__DIR__ . '/loader.js');
        $outside = [];
        $ordered = [];
        $async = [];
        foreach ($arr as $name => $e) {
            if ($e['source'] instanceof AssetInterface) {
                if ($e['async']) {
                    $async[] = $e['source']->publicUrl()->__toString();
                } else {
                    $ordered[] = $e['source']->publicUrl()->__toString();
                }
            } else {
                $outside[$name] = $e['source'];
            }
        }
        //set up script with JSON lists of files to be loaded
        $script = str_replace('/*$ordered*/', json_encode($ordered), $script);
        $script = str_replace('/*$async*/', json_encode($async), $script);
        $asset = $this->leafcutter->assets()->getFromString($script, null, 'js');
        return $this->jsHtml($outside) . PHP_EOL . "<script>" . $asset->content() . "</script>";
    }

    protected function filter(string $type, string $media = null, bool $async = null)
    {
        return array_filter(
            $this->assets,
            function ($e) use ($type, $async, $media) {
                return
                    $e['type'] == $type
                    && ($async === null || $e['async'] == $async)
                    && ($media === null || strpos($e['media'], $media) === 0)
                ;
            }
        );
    }

    protected function cssHtml($arr): string
    {
        $arr = array_filter($arr);
        if ($this->leafcutter->config('theme.css.bundle')) {
            $arr = $this->bundle_assets($arr, 'css');
        }
        $arr = array_filter($arr);
        array_walk(
            $arr,
            function (&$e, $k) {
                $media = $this->mediaAliases[$e['media']] ?? $e['media'];
                if ($media == 'all') {
                    $media = '';
                } else {
                    $media = ' media="' . $media . '"';
                }
                if ($e['source'] instanceof AssetInterface) {
                    # Note that libraries aren't inlined, because theoretically they should stay very consistent across multiple pages
                    if ($e['media'] != 'library' && $this->inlined + $e['source']->size() <= $this->leafcutter->config('theme.css.max_inlined')) {
                        $this->inlined += $e['source']->size();
                        $e = '<style type="text/css"' . $media . '>' . PHP_EOL
                        . $e['source']->content()
                            . PHP_EOL . "</style>";
                        return;
                    }
                    $source = $e['source']->publicUrl();
                } else {
                    $source = $e['source'];
                }
                $e = '<link rel="stylesheet" href="' . $source . '" type="text/css"'
                    . $media
                    . ($e['crossorigin'] ? ' crossorigin="' . $e['crossorigin'] . '"' : '')
                    . ($e['integrity'] ? ' integrity="' . $e['integrity'] . '"' : '')
                    . ' />';
            }
        );
        return implode(PHP_EOL, $arr);
    }

    protected function jsHtml($arr): string
    {
        $arr = array_filter($arr);
        if ($this->leafcutter->config('theme.js.bundle')) {
            $arr = $this->bundle_assets($arr, 'js');
        }
        $arr = array_filter($arr);
        $arr = array_filter($arr);
        array_walk(
            $arr,
            function (&$e, $k) {
                if ($e['source'] instanceof AssetInterface) {
                    $source = $e['source']->publicUrl();
                } else {
                    $source = $e['source'];
                }
                $e = '<script src="' . $source . '"'
                    . ($e['async'] ? ' async="true"' : '')
                    . ($e['crossorigin'] ? ' crossorigin="' . $e['crossorigin'] . '"' : '')
                    . ($e['integrity'] ? ' integrity="' . $e['integrity'] . '"' : '')
                    . '></script>';
            }
        );
        return implode(PHP_EOL, $arr);
    }

    protected function bundle_assets($arr, $ext)
    {
        $bundles = [];
        array_walk(
            $arr,
            function (&$e, $k) use (&$bundles) {
                if ($bundles && $e['source'] instanceof AssetInterface) {
                    $tail = end($bundles);
                    $key = key($bundles);
                    $lastSource = end($tail)['source'];
                    if ($lastSource instanceof AssetInterface) {
                        $bundles[$key][$k] = $e;
                    } else {
                        $bundles[] = [$k => $e];
                    }
                } else {
                    $bundles[] = [$k => $e];
                }
            }
        );
        $bundled = [];
        foreach ($bundles as $k => $b) {
            if (count($b) == 1) {
                //single-item bundles get passed through unchanged
                $bundled[key($b)] = reset($b);
            } else {
                $content = [];
                foreach ($b as $e) {
                    $content[] = '/*' . PHP_EOL . $e['source']->url() . PHP_EOL . '*/';
                    $content[] = $e['source']->content();
                    if ($ext == 'js') {
                        $content[] = ';';
                    }
                }
                $content = implode(PHP_EOL, $content);
                $filename = $e['media'] . '-' . hash('md5', $content) . '.' . $ext;
                $bundled["bundle $k: $filename"] = [
                    'source' => $this->leafcutter->assets()->getFromString($content, new URL('@/~themes/' . $filename)),
                    'crossorigin' => null,
                    'integrity' => null,
                    'type' => $e['type'],
                    'async' => $e['async'],
                    'media' => $e['media'],
                ];
            }
        }
        return $bundled;
    }

    public function addJs(string $name, $source, string $media = 'all', array $options = [])
    {
        $this->addAsset('js', $source, $name, @$options['integrity'], !!@$options['async'], $media);
    }

    public function addCss(string $name, $source, string $media = 'all', array $options = [])
    {
        $this->addAsset('css', $source, $name, @$options['integrity'], false, $media);
    }

    protected function addAsset($type, $source, string $name, ?string $integrity, bool $async, ?string $media)
    {
        $id = $type . '|' . $name;
        //unset if source is null/false/empty
        if (!$source) {
            unset($this->assets[$id]);
            return;
        }
        //special cases for if source is an Asset
        if ($source instanceof AssetInterface) {
            //unset if Asset is empty
            if (!trim($source->content())) {
                unset($this->assets[$id]);
                return;
            }
            $integrity = null;
        }
        //we have a valid source, add it in
        $this->assets[$id] = [
            'source' => $source,
            'crossorigin' => $integrity ? 'anonymous' : null,
            'integrity' => $integrity,
            'type' => $type,
            'async' => $async,
            'media' => $media,
        ];
    }
}
