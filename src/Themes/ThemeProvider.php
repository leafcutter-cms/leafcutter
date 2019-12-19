<?php
namespace Leafcutter\Themes;

use Leafcutter\Leafcutter;
use Flatrr\Config\Config;
use Symfony\Component\Finder\Finder;
use Leafcutter\Content\Assets\AssetInterface;

class ThemeProvider
{
    use \Leafcutter\Common\SourceDirectoriesTrait;

    protected $leafcutter;
    protected $assets = [];
    protected $mediaAliases = [
        'blocking' => 'all',
        'library' => 'all',
        'page' => 'all',
        'site' => 'all',
        'theme' => 'all',
    ];
    protected $cssMedias = [
        'library',//loads first, basically where anything external should go
        'blocking',//loads first after library, used to get first in line for inlining
        'theme',//theme
        'all',//media queries are fine, but it's better to keep medias in separate files
        'screen',
        'print',
        'speech',
        'site',//things that are site-specific, may include media queries
        'page'//things that are page-specific, may include media queries
    ];
    protected $jsMedias = [
        'library',//loads first
        'theme',//only exists to give a section before "site" to be used by themes
        'all',//generic location
        'site',//site-specific
        'page'//page-specific
    ];
    protected $inlined = 0;
    protected $loadedThemes = [];
    protected $bodyClasses = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->variables = new Variables($leafcutter);
        $this->addDirectory(__DIR__.'/themes');
        $this->leafcutter->hooks()->addSubscriber($this);
    }

    public function variables() : Variables
    {
        return $this->variables;
    }

    public function getBodyClass() : string
    {
        $classes = $this->bodyClasses;
        if ($this->variables()->get('body_class')) {
            $classes[] = $this->variables()->get('body_class');
        }
        return implode(' ', $classes);
    }

    public function loadTheme(string $name)
    {
        $this->leafcutter->logger()->debug('Theme: loadTheme '.$name);
        $name = preg_replace('/[^a-z0-9\-_]/', '', $name);
        foreach ($this->sourceDirectories() as $dir) {
            $dir = "$dir/$name";
            $yaml = "$dir/theme.yaml";
            if (is_file($yaml)) {
                $this->doLoadTheme($dir, $yaml);
            }
        }
    }

    public function onPrefixedContentList_themes($globs)
    {
        $files = [];
        foreach ($this->sourceDirectories() as $dir) {
            foreach ($globs as $glob) {
                $glob = "$dir$glob";
                foreach (glob($glob, GLOB_BRACE) as $match) {
                    $path = $this->normalizePath($match);
                    $files[$path] = @$files[$path] ?? $match;
                }
            }
        }
        return $files;
    }

    protected function normalizePath(string $path) : string
    {
        foreach ($this->sourceDirectories() as $dir) {
            if (strpos($path, $dir) === 0) {
                $path = substr($path, strlen($dir));
                break;
            }
        }
        if (substr($path, 0, 1) != '/') {
            $path = "/$path";
        }
        $path = preg_replace('@/(_|[0-9]{1,3}\. )@', '/', $path);
        return "/~themes$path";
    }

    public function onPrefixedAssetGet_themes($url)
    {
        foreach ($this->sourceDirectories() as $dir) {
            $file = $dir.$url->getPath();
            if (is_file($file)) {
                return $this->leafcutter->assets()->getFromFile(
                    $url->getFullPath(),
                    $file,
                    $url->getArgs()
                );
            }
        }
        return null;
    }

    protected function doLoadTheme($dir, $yaml)
    {
        $themeName = basename($dir);
        if (in_array($dir, $this->loadedThemes)) {
            $this->leafcutter->logger()->notice('Theme: already loaded '.$themeName);
            return;
        }
        $this->leafcutter->logger()->debug('Theme: loading '.$themeName);
        $this->loadedThemes[] = $dir;
        $config = new Config([
            'theme.prefix' => "/~themes/$themeName/"
        ]);
        $config->readFile($yaml);
        //set up advanced files (these don't get prefixed by theme name)
        foreach ($config['advanced']??[] as $k => $f) {
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
        foreach ($config['css']??[] as $media => $files) {
            foreach ($files as $file) {
                $url = $file;
                if (substr($url, 0, 1) != '/') {
                    $url = $config['theme.prefix'].$url;
                }
                $name = "theme: $themeName: $media: $file";
                $this->addAsset(
                    'css',
                    $url,//url
                    $name,//name
                    null,
                    false,
                    $media
                );
            }
        }
        //set up internal JS files
        foreach ($config['js']??[] as $media => $files) {
            foreach ($files as $file) {
                $url = $file;
                if (substr($url, 0, 1) != '/') {
                    $url = $config['theme.prefix'].$url;
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

    public function addJs(string $name, $source, string $media='all', array $options=[])
    {
        $this->leafcutter->logger()->debug('Theme: addJs '.$name);
        $this->addAsset('js', $source, $name, @$options['integrity'], !!@$options['async'], $media);
    }

    public function addCss(string $name, $source, string $media='all', array $options=[])
    {
        $this->leafcutter->logger()->debug('Theme: addCss '.$name);
        $this->addAsset('css', $source, $name, @$options['integrity'], false, $media);
    }

    protected function addAsset($type, $source, string $name, ?string $integrity, bool $async, ?string $media)
    {
        $id = $type.'|'.$name;
        if ($source instanceof AssetInterface) {
            $this->leafcutter->logger()->debug('Theme: adding Asset: '.$id.': '.get_class($source));
        } else {
            $this->leafcutter->logger()->debug('Theme: adding asset: '.$id.': '.$source);
        }
        //unset if source is null/false/empty
        if (!$source) {
            unset($this->assets[$id]);
            return;
        }
        //special cases for if source is an Asset
        if ($source instanceof AssetInterface) {
            //unset if Asset is empty
            if ($source->isEmpty()) {
                $this->leafcutter->logger()->notice('Theme: skipping empty Asset: '.$id.': '.get_class($source));
                unset($this->assets[$id]);
                return;
            }
            $integrity = null;
        }
        //we have a valid source, add it in
        $this->assets[$id] = [
            'source' => $source,
            'crossorigin' => $integrity?'anonymous':null,
            'integrity' => $integrity,
            'type' => $type,
            'async' => $async,
            'media' => $media
        ];
    }

    public function getHeadHtml() : string
    {
        $this->leafcutter->logger()->debug('Theme: getHeadHtml');
        //resolve asset objects wherever possible
        array_walk(
            $this->assets,
            function (&$v, $k) {
                if (is_string($v['source'])) {
                    $v['source'] = $this->leafcutter->assets()->get($v['source'])??$v['source'];
                }
            }
        );
        //produce output
        $this->inlined = 0;
        $html = [];
        foreach ($this->cssMedias as $m) {
            $html[] = $this->cssHtml($this->filter('css', $m));
        }
        foreach ($this->jsMedias as $m) {
            $html[] = $this->jsHtml($this->filter('js', $m, false));
            $html[] = $this->jsHtml($this->filter('js', $m, true));
        }
        return implode(PHP_EOL, array_filter($html));
    }

    protected function filter(string $type, string $media=null, bool $async=null)
    {
        return array_filter(
            $this->assets,
            function ($e) use ($type,$async,$media) {
                return
                    $e['type'] == $type
                    && ($async === null || $e['async'] == $async)
                    && ($media === null || strpos($e['media'], $media) === 0)
                    ;
            }
        );
    }

    protected function cssHtml($arr) : string
    {
        $arr = array_filter($arr);
        if ($this->leafcutter->config('css.bundle')) {
            $arr = $this->bundle_assets($arr, 'css');
        }
        $arr = array_filter($arr);
        array_walk(
            $arr,
            function (&$e, $k) {
                $media = $this->mediaAliases[$e['media']]??$e['media'];
                if ($media == 'all') {
                    $media = '';
                } else {
                    $media = ' media="'.$media.'"';
                }
                if ($e['source'] instanceof AssetInterface) {
                    if ($e['media'] != 'library' && $this->inlined+$e['source']->getFilesize() <= $this->leafcutter->config('css.max_inlined')) {
                        $this->inlined += $e['source']->getFilesize();
                        $e = '<style type="text/css"'.$media.'>'.PHP_EOL
                            .$e['source']->getContent()
                            .PHP_EOL."</style>";
                        return;
                    }
                    $source = $e['source']->getOutputUrl();
                } else {
                    $source = $e['source'];
                }
                $e = '<link rel="stylesheet" href="'.$source.'" type="text/css"'
                    .$media
                    .($e['crossorigin']?' crossorigin="'.$e['crossorigin'].'"':'')
                    .($e['integrity']?' integrity="'.$e['integrity'].'"':'')
                    .' />';
            }
        );
        return implode(PHP_EOL, $arr);
    }

    protected function jsHtml($arr) : string
    {
        $arr = array_filter($arr);
        if ($this->leafcutter->config('js.bundle')) {
            $arr = $this->bundle_assets($arr, 'js');
        }
        $arr = array_filter($arr);
        $arr = array_filter($arr);
        array_walk(
            $arr,
            function (&$e, $k) {
                if ($e['source'] instanceof AssetInterface) {
                    $source = $e['source']->getOutputUrl();
                } else {
                    $source = $e['source'];
                }
                $e = '<script src="'.$source.'"'
                    .($e['async']?' async="true"':'')
                    .($e['crossorigin']?' crossorigin="'.$e['crossorigin'].'"':'')
                    .($e['integrity']?' integrity="'.$e['integrity'].'"':'')
                    .'></script>';
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
                    $content[] = '/*'.PHP_EOL.$e['source']->getUrl().PHP_EOL.'*/';
                    $content[] = $e['source']->getContent();
                    if ($ext == 'js') {
                        $content[] = ';';
                    }
                }
                $content = implode(PHP_EOL, $content);
                $filename = $e['media'].'-'.hash('crc32', $content).'.'.$ext;
                $bundled["bundle $k: $filename"] = [
                    'source' => $this->leafcutter->assets()->getFromString('/~themes/'.$filename, $content),
                    'crossorigin' => null,
                    'integrity' => null,
                    'type' => $e['type'],
                    'async' => $e['async'],
                    'media' => $e['media']
                ];
            }
        }
        return $bundled;
    }
}
