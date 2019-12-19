<?php
namespace Leafcutter\Content\Pages;

use Flatrr\SelfReferencingFlatArray;
use Leafcutter\Common\UrlInterface;
use Symfony\Component\Yaml\Yaml;

class Page implements PageInterface
{
    protected $meta;
    protected $content;
    protected $url;
    protected $order = 1000;

    public function __construct(string $content, UrlInterface $url)
    {
        $this->meta = new SelfReferencingFlatArray;
        $this->setContent($content);
        $this->setUrl($url);
    }

    public function getMeta() : SelfReferencingFlatArray
    {
        return $this->meta;
    }

    public function getOrder($order) : int
    {
        return $this->order;
    }

    public function setOrder(int $order)
    {
        $this->order = $order;
    }

    public function getHash() : string
    {
        return hash('crc32', get_called_class().$this->url->getHash());
    }

    public function __toString()
    {
        return '<a href="'.$this->getUrl().'">'.$this->getName().'</a>';
    }

    public function setDateModified(int $date)
    {
        $this->meta['date_modified'] = $date;
    }

    public function getDateModified() : ?int
    {
        return $this->meta['date_modified'];
    }

    public function getChildren() : array
    {
        // TODO: allow pulling additional children from meta
        return [];
    }

    public function setTemplate(string $template)
    {
        $this->meta['template'] = $template;
    }

    public function getTemplate() : ?string
    {
        return $this->meta['template'];
    }

    public function setName(string $name)
    {
        $this->meta['name'] = $name;
    }

    public function getName() : string
    {
        return $this->meta['name'] ?? 'Untitled page';
    }

    public function setUrl(UrlInterface $url)
    {
        $this->url = clone $url;
    }

    public function getUrl() : UrlInterface
    {
        return clone $this->url;
    }

    public function getLink(string $name=null) : string
    {
        return "<a href=\"".$this->getUrl()."\">".($name??$this->getName())."</a>";
    }

    public function getRawContent()
    {
        return $this->content;
    }

    public function getContent($wrap=false) : string
    {
        if (is_callable($this->content)) {
            $this->setContent(\call_user_func($this->content));
        }
        if (!$wrap) {
            return $this->content;
        } else {
            $idClass = preg_replace('@[^a-zA-Z0-9]+@', '_', "pageContent_".$this->url->getFullPath());
            return "<div class=\"pageContent $idClass\" data-context-path=\"".$this->url->getContext()."\">"
                .PHP_EOL.$this->content
                .PHP_EOL."</div>";
        }
    }

    public function setContent($content)
    {
        if (is_string($content)) {
            $content = preg_replace_callback('/<!--@meta(.+?)-->/ms', function ($match) {
                try {
                    $meta = Yaml::parse($match[1]);
                    if (@$meta['date_modified']) {
                        $meta['date_modified'] = strtotime($meta['date_modified']);
                    }
                    if (@$meta['date_created']) {
                        $meta['date_created'] = strtotime($meta['date_created']);
                    }
                    $this->meta->merge($meta, null, true);
                } catch (\Throwable $th) {
                    // throw $th;
                }
                return '';
            }, $content);
            if (!$this->meta['name'] && preg_match('@<h1>(.+?)</h1>@', $content, $matches)) {
                $this->meta['name'] = trim(strip_tags($matches[1]));
            }
        }
        $this->content = $content;
    }
}
