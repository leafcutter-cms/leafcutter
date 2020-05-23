<?php
namespace Leafcutter;

use Leafcutter\Pages\PageInterface;

class Response
{
    const ALLOWED_STATUS = [200, 300, 301, 302, 307, 308, 400, 401, 403, 404, 500, 503];
    private $status = 200;
    private $text = '';
    private $source;
    private $headers = [];
    private $template = 'default.twig';
    private $dynamic = false;
    private $after = [];
    private $url;

    public function setURL(URL $url)
    {
        $this->url = $url;
    }

    public function url(): URL
    {
        return $this->url;
    }

    public function renderHeaders()
    {
        http_response_code($this->status);
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
    }

    public function doAfter(callable $fn)
    {
        $this->after[] = $fn;
    }

    public function setMime(string $mime)
    {
        $this->header('content-type', $mime . '; charset=utf-8');
    }

    public function dynamic(): bool
    {
        return $this->dynamic;
    }

    public function setDynamic(bool $dynamic)
    {
        $this->dynamic = $dynamic;
    }

    public function template(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template)
    {
        $this->template = $template;
    }

    public function content()
    {
        return $this->text();

    }

    public function renderContent()
    {
        echo $this->content();
        if ($this->after) {
            \ignore_user_abort(false);
            if (\function_exists('\fastcgi_finish_request')) {
                \fastcgi_finish_request();
                $this->callDoAfter();
            } else {
                \register_shutdown_function(function () {
                    $this->callDoAfter();
                });
                exit();
            }
        }
    }

    protected function callDoAfter()
    {
        foreach ($this->after as $fn) {
            $fn();
        }
    }

    public function redirect(string $url, int $status = 307)
    {
        $this->setStatus($status);
        $this->header('Location', $url);
    }

    public function header(string $name, string $value = null)
    {
        $this->headers[$name] = $value;
    }

    public function source()
    {
        return $this->source;
    }

    public function page() : ?PageInterface
    {
        if ($this->source instanceof PageInterface) {
            return $this->source;
        }else {
            return null;
        }
    }

    public function setSource($source)
    {
        $this->source = $source;
        if (method_exists($source, 'dynamic')) {
            $this->setDynamic($source->dynamic());
        }
        if (method_exists($source, 'template')) {
            $this->setTemplate($source->template());
        }
        if (method_exists($source, 'url')) {
            $this->setURL($source->url());
        }
    }

    public function setStatus(int $status)
    {
        $this->status = $status;
    }

    public function setText(?string $text)
    {
        $this->text = $text;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function text(): ?string
    {
        return $this->text;
    }
}
