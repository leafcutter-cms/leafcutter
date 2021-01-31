<?php
namespace Leafcutter\Themes;

use Leafcutter\Assets\AssetEvent;
use Leafcutter\Assets\AssetFileEvent;
use Leafcutter\Assets\AssetInterface;
use Leafcutter\Assets\StringAsset;
use Leafcutter\Leafcutter;
use Leafcutter\Response;
use Leafcutter\URL;
use Leafcutter\URLFactory;
use MatthiasMullie\Minify;

class ThemeEvents
{
    protected $leafcutter;

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $leafcutter->events()->addSubscriber($this);
    }

    public function onLeafcutterConstructed()
    {
        foreach (array_filter($this->leafcutter->config('theme.activate')) as $package) {
            $this->leafcutter->theme()->activate($package);
        }
    }

    public function onTemplateInjection_head()
    {
        echo $this->leafcutter->theme()->getHeadHtml() . PHP_EOL;
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
                //skip data urls
                if (substr($matches[3], 0, 5) == 'data:') {
                    return $matches[0];
                }
                //get the parts neeeded
                $url = new URL($matches[3]);
                $mediaQuery = @$matches[6];
                //get url from matches
                if ($asset = $this->leafcutter->assets()->get($url)) {
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
                //skip data urls
                if (substr($matches[2], 0, 5) == 'data:') {
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
            $this->leafcutter->logger()->debug('SCSS import: ' . $path);
            // try to include a raw scss file if possible
            $url = new URL($path);
            if ($url->sitePath()) {
                foreach ($this->leafcutter->content()->files($url->sitePath(), $url->siteNamespace()) as $file) {
                    $this->leafcutter->logger()->debug('Possible file match: ' . $file->path());
                    if (substr($file->path(), -5) == '.scss') {
                        $this->leafcutter->logger()->debug('Matched: ' . $file->path());
                        return $file->path();
                    }
                }
            }
            // otherwise try to find CSS-ey files
            if ($asset = $this->leafcutter->assets()->get($url)) {
                $this->leafcutter->logger()->debug('Possible asset match: ' . $asset->publicUrl());
                if ($asset->extension() == 'css') {
                    $this->leafcutter->logger()->debug('Matched: ' . $asset->publicUrl());
                    return $asset->outputFile();
                }
            }
            // log failure
            $this->leafcutter->logger()->error('Failed to load SCSS import file ' . $path);
        });
        // set context, compile and output
        URLFactory::beginContext($event->url());
        $css = $compiler->compile($css);
        URLFactory::endContext();
        return new StringAsset($event->url(), $css);
    }

    /**
     * Load requested theme packages, page's _page CSS/JS files, and it plus all parents' _site files
     *
     * @param Response $response
     * @return void
     */
    public function onResponsePageSet(Response $response)
    {
        // load requested packages
        $page = $response->source();
        // package requirements from page meta
        $packages = $page->meta('theme_packages') ?? [];
        // scan for package requirements in content
        $response->setContent(preg_replace_callback(
            '<!-- theme_package:([a-z0-9\-\/]+) -->',
            function ($m) use (&$packages) {
                $packages[] = $m[1];
                return '';
            },
            $response->content()
        ));
        // load all packages
        foreach (array_unique($packages) as $name) {
            $this->leafcutter->theme()->activate($name);
        }
        // specific css/js from page meta
        URLFactory::beginContext($page->calledUrl());
        foreach ($page->meta('page_js') ?? [] as $url) {
            $url = new URL($url);
            $this->leafcutter->theme()->addJs('page_js_' . md5($url), $url, 'page');
        }
        foreach ($page->meta('page_css') ?? [] as $url) {
            $url = new URL($url);
            $this->leafcutter->theme()->addCss('page_css_' . md5($url), $url, 'page');
        }
        URLFactory::endContext();
        // set up URL context
        $context = $response->url()->siteFullPath();
        URLFactory::beginContext($context);
        // load root _site files
        if ($asset = $this->leafcutter->assets()->get(new URL("@/_site.css"))) {
            $this->leafcutter->theme()->addCss($asset->hash(), $asset, 'site');
        }
        if ($asset = $this->leafcutter->assets()->get(new URL("@/_site.js"))) {
            $this->leafcutter->theme()->addJs($asset->hash(), $asset, 'site');
        }
        // load all _site files for parent directories
        $context = explode('/', $context);
        $check = '';
        foreach ($context as $c) {
            $check .= "$c/";
            if ($asset = $this->leafcutter->assets()->get(new URL("@/{$check}_site.css"))) {
                $this->leafcutter->theme()->addCss($asset->hash(), $asset, 'site');
            }
            if ($asset = $this->leafcutter->assets()->get(new URL("@/{$check}_site.js"))) {
                $this->leafcutter->theme()->addJs($asset->hash(), $asset, 'site');
            }
        }
        // load _page CSS and JS files
        if ($asset = $this->leafcutter->assets()->get(new URL("./_page.css"))) {
            $this->leafcutter->theme()->addCss($asset->hash(), $asset, 'page');
        }
        if ($asset = $this->leafcutter->assets()->get(new URL("./_page.js"))) {
            $this->leafcutter->theme()->addJs($asset->hash(), $asset, 'page');
        }
        // end context
        URLFactory::endContext();
    }
}
