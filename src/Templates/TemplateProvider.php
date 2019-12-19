<?php
namespace Leafcutter\Templates;

use Leafcutter\Leafcutter;
use Leafcutter\Content\Pages\PageInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;

class TemplateProvider
{
    use \Leafcutter\Common\SourceDirectoriesTrait;
    
    protected $leafcutter;
    protected $arrayLoader;
    protected $loader;
    protected $twig;
    protected $templates = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->addDirectory(__DIR__.'/templates');
    }

    protected function sourceDirectoriesChanged()
    {
        $this->loader = null;
        $this->twig = null;
    }

    public function execute($content, $parameters=[]) : string
    {
        $template = 'applyToPageContent.'.hash('crc32', $content);
        $this->addOverride($template, $content);
        //apply template
        return $this->apply($template, $parameters);
    }

    public function applyToPage(PageInterface $page, string $template = null, $parameters = []) : string
    {
        list($page, $template, $parameters) = $this->leafcutter->hooks()->dispatchAll('onTemplatePage', [$page,$template,$parameters]);
        //pick template
        $template = $page->getTemplate();
        if (!$template || !$this->exists($template)) {
            $template = 'default.twig';
        }
        //get string version of content
        $parameters['content'] = $page->getContent(true);
        $parameters['page'] = $page;
        //apply template
        return $this->apply($template, $parameters);
    }

    public function apply(string $name, array $parameters = []) : string
    {
        if (!$this->exists($name)) {
            throw new \Exception("Template $name does not exist", 1);
        }
        $parameters = array_replace_recursive($this->defaultParameters(), $parameters);
        try {
            return $this->twig()->render($name, $parameters);
        } catch (\Throwable $th) {
            $this->leafcutter->logger()->error('TemplateProvider: apply: Exception: '.$th->getMessage());
            return "[an error occurred while applying template: <code>".$th->getMessage()."</code>]";
        }
    }

    protected function defaultParameters() : array
    {
        return [
            'site' => $this->leafcutter->config('site'),
            'themes' => $this->leafcutter->themes(),
            'pages' => $this->leafcutter->pages(),
            'assets' => $this->leafcutter->assets(),
            'images' => $this->leafcutter->images(),
        ];
    }

    public function exists(string $name) : bool
    {
        return $this->loader()->exists($name);
    }

    protected function twig() : Environment
    {
        if ($this->twig === null) {
            $this->twig = $this->prepareTwig();
        }
        return $this->twig;
    }

    public function addOverride(string $name, string $template) : void
    {
        $this->templates[$name] = $template;
        $this->arrayLoader = null;
        $this->loader = null;
        $this->twig = null;
    }

    public function removeOverride(string $name) : void
    {
        unset($this->templates[$name]);
        $this->arrayLoader = null;
        $this->loader = null;
        $this->twig = null;
    }

    protected function prepareTwig() : Environment
    {
        return new Environment(
            $this->loader(),
            $this->leafcutter->config('twig_environment')
        );
    }

    protected function loader() : LoaderInterface
    {
        if ($this->loader === null) {
            $this->loader = $this->prepareLoader();
            $this->twig = null;
        }
        return $this->loader;
    }

    protected function arrayLoader() : ArrayLoader
    {
        if ($this->arrayLoader === null) {
            $this->arrayLoader = $this->prepareArrayLoader();
            $this->loader = null;
        }
        return $this->arrayLoader;
    }

    protected function prepareArrayLoader() : ArrayLoader
    {
        return new ArrayLoader($this->templates);
    }

    protected function prepareLoader() : LoaderInterface
    {
        $loaders = [
            $this->arrayLoader(),
            new FilesystemLoader($this->sourceDirectories())
        ];
        return new ChainLoader($loaders);
    }
}
