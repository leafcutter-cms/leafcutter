<?php
namespace Leafcutter\StaticPages;

use Leafcutter\Common\Filesystem;
use Leafcutter\Leafcutter;
use Leafcutter\Pages\PageInterface;
use Leafcutter\Response;
use Leafcutter\URL;

class StaticPageProvider
{
    private $leafcutter;

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $leafcutter->events()->addSubscriber($this);
    }

    public function onResponseURL_namespace_staticAsyncCheck($url): ?Response
    {
        $rUrl = new URL('@/' . $url->sitePath());
        $currentHash = $this->leafcutter->content()->hash($rUrl->sitePath(), $rUrl->siteNamespace());
        $response = new Response();
        $response->setMime('application/javascript');
        $response->setTemplate(null);
        if ($this->needsRebuild($rUrl)) {
            if (is_file($this->urlSavePath($rUrl)) && !$this->leafcutter->config('statics.enabled')) {
                $response->setText('console.log("static page cache disabled, deleting this file");');
                unlink($this->urlSavePath($rUrl));
                return $response;
            }
            $this->leafcutter->buildResponse($rUrl, false);
            $response->setText('console.log("doing background rebuild");');
            $response->doAfter(function () use ($rUrl) {
                $this->leafcutter->buildResponse($rUrl, false);
            });
        } else {
            $response->setText('console.log("no rebuild required");');
        }
        return $response;
    }

    protected function needsRebuild($url)
    {
        $file = $this->urlSavePath($url);
        if (is_file($file) && !$this->leafcutter->config('statics.enabled')) {
            return true;
        }
        if (!is_file($file)) {
            return true;
        }
        $content = file_get_contents($file);
        if (!preg_match('@<!--staticPageMeta{{{(.+?)}}}-->@ms', $content, $matches)) {
            return true;
        }
        $meta = json_decode($matches[1], true);
        if (!$meta) {
            return true;
        }
        $ttl = $this->leafcutter->config('statics.ttl');
        if ($meta['time'] + $ttl < time()) {
            return true;
        }
        if ($meta['hash'] != $this->leafcutter->content()->hash($url->sitePath(), $url->siteNamespace())) {
            return true;
        }
        return false;
    }

    public function onResponseReturn($response)
    {
        if (!$this->leafcutter->config('statics.enabled')) {
            return;
        }
        if ($path = $this->savePath($response)) {
            $url = $response->source()->url();
            $content = $response->content();
            $scriptURL = new URL('@/@staticAsyncCheck/' . $response->source()->url()->sitePath());
            $meta = json_encode([
                'hash' => $this->leafcutter->content()->hash($url->sitePath(), $url->siteNamespace()),
                'time' => time(),
            ]);
            $script = <<<EOS

<!--staticPageMeta{{{{$meta}}}}-->
<script src='$scriptURL' async defer></script>

EOS;
            $content = str_replace('<head>', "<head>$script", $content, $matches);
            if ($matches != 1) {
                return;
            }
            $fs = new Filesystem;
            $fs->put($content, $path, true);
        }
    }

    protected function savePath(Response $response): ?string
    {
        if ($response->dynamic()) {
            return null;
        }
        if ($response->status() != 200) {
            return null;
        }
        if (!($response->source() instanceof PageInterface)) {
            return null;
        }
        if ($response->source()->url()->query()) {
            return null;
        }
        return $this->urlSavePath($response->source()->url());
    }

    protected function urlSavePath(URL $url): string
    {
        $path = $url->siteFullPath();
        if ($path == '' || substr($path, -1) == '/') {
            $path .= 'index.html';
        }
        return $this->leafcutter->config('statics.directory') . $path;
    }
}
