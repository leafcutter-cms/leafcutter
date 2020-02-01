<?php
namespace Leafcutter\Templates;

use Leafcutter\Leafcutter;
use Leafcutter\Pages\PageEvent;
use Leafcutter\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;

class TemplateProvider
{
    use \Leafcutter\Common\SourceDirectoriesTrait;

    private $leafcutter;
    protected $arrayLoader;
    protected $loader;
    protected $twig;
    protected $templates = [];
    protected $filters = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->addDirectory(__DIR__ . '/templates');
        $this->leafcutter->events()->addSubscriber($this);
        $this->leafcutter->events()->dispatchAll('onTemplateProviderReady', $this);
    }

    public function html_injection(string $name): string
    {
        ob_start();
        echo "<!-- onTemplateInjection_$name -->" . PHP_EOL;
        $this->leafcutter->events()->dispatchAll('onTemplateInjection_' . $name, null);
        $out = ob_get_contents();
        ob_end_clean();
        return $out;
    }

    public function onTemplateProviderReady(TemplateProvider $provider)
    {
        // link filter
        $provider->addFilter(
            'link',
            function ($item) {
                if (\is_string($item)) {
                    $item = $this->leafcutter->find($item);
                }
                if (\method_exists($item, 'link')) {
                    return $item->link();
                }
                if (\method_exists($item, 'url')) {
                    $url = $item->url();
                    $name = \method_exists($item, 'name') ? $item->name() : $url;
                    $name = \htmlspecialchars($name);
                    return "<a href='$url'>$name</a>";
                }
                return $item;
            },
            ['is_safe' => ['html']]
        );
    }

    public function addFilter(string $name, ?callable $fn, array $options = [])
    {
        $this->filters[$name] = [$fn, $options];
        $this->filters = array_filter($this->filters);
        $this->twig = null;
    }

    public function onPageReady(PageEvent $event)
    {
        $page = $event->page();
        $content = $page->content();
        $name = 'page_' . $page->hash();
        $this->addOverride($name, $page->content());
        $page->setContent($this->apply(
            $name,
            [
                'page' => clone $page,
            ]
        ));
    }

    protected function sourceDirectoriesChanged()
    {
        $this->loader = null;
        $this->twig = null;
    }

    public function apply(string $name, array $parameters = []): string
    {
        if (!$this->exists($name)) {
            throw new \Exception("Template $name does not exist", 1);
        }
        $parameters = array_replace_recursive($this->defaultParameters(), $parameters);
        return $this->twig()->render($name, $parameters);
    }

    public function onResponseReady(Response $response)
    {
        $template = $response->template();
        if (!$template) {
            return;
        }
        $content = $response->content();
        $response->setText($this->apply(
            $template,
            [
                'page_content' => $content,
                'response' => clone $response,
                'page' => $response->source() ? clone $response->source() : null,
            ]
        ));
    }

    protected function defaultParameters(): array
    {
        return [
            'site' => $this->leafcutter->config('templates.site'),
            'theme' => $this->leafcutter->theme(),
            'pages' => $this->leafcutter->pages(),
            'assets' => $this->leafcutter->assets(),
            'images' => $this->leafcutter->images(),
            'templates' => $this,
        ];
    }

    public function exists(string $name): bool
    {
        return $this->loader()->exists($name);
    }

    protected function twig(): Environment
    {
        if ($this->twig === null) {
            $this->twig = $this->prepareTwig();
        }
        return $this->twig;
    }

    public function addOverride(string $name, string $template): void
    {
        $this->templates[$name] = $template;
        $this->arrayLoader = null;
        $this->loader = null;
        $this->twig = null;
    }

    public function removeOverride(string $name): void
    {
        unset($this->templates[$name]);
        $this->arrayLoader = null;
        $this->loader = null;
        $this->twig = null;
    }

    protected function prepareTwig(): Environment
    {
        $twig = new Environment(
            $this->loader(),
            $this->leafcutter->config('templates.twig_environment')
        );
        foreach ($this->filters as $name => list($fn, $options)) {
            $twig->addFilter(new \Twig\TwigFilter(
                $name,
                $fn,
                $options
            ));
        }
        return $twig;
    }

    protected function loader(): LoaderInterface
    {
        if ($this->loader === null) {
            $this->loader = $this->prepareLoader();
            $this->twig = null;
        }
        return $this->loader;
    }

    protected function arrayLoader(): ArrayLoader
    {
        if ($this->arrayLoader === null) {
            $this->arrayLoader = $this->prepareArrayLoader();
            $this->loader = null;
        }
        return $this->arrayLoader;
    }

    protected function prepareArrayLoader(): ArrayLoader
    {
        return new ArrayLoader($this->templates);
    }

    protected function prepareLoader(): LoaderInterface
    {
        $loaders = [
            $this->arrayLoader(),
            new FilesystemLoader($this->sourceDirectories()),
        ];
        return new ChainLoader($loaders);
    }
}
