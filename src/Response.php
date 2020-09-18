<?php
namespace Leafcutter;

use Leafcutter\Pages\PageInterface;

class Response
{
    private $status = 200;
    private $content = '';
    private $source;
    private $headers = [];
    private $template = 'default.twig';
    private $dynamic = false;
    private $after = [];
    private $url;
    private $mime = 'text/html';
    private $charset = 'utf-8';

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
        header('content-type: '.$this->mime.'; charset='.$this->charset);
    }

    public function doAfter(callable $fn)
    {
        $this->after[] = $fn;
    }

    public function setMime(string $mime)
    {
        $this->mime = $mime;
    }

    public function setCharset(string $charset)
    {
        $this->charset = $charset;
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
        return $this->content;
    }

    public function setContent(string $content)
    {
        $this->content = $content;
    }

    public function renderContent()
    {
        echo $this->content();
        if ($this->after) {
            ignore_user_abort(false);
            if (function_exists('\fastcgi_finish_request')) {
                fastcgi_finish_request();
                $this->callDoAfter();
            } else {
                register_shutdown_function(function () {
                    $this->callDoAfter();
                });
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

    public function status(): int
    {
        return $this->status;
    }
}
