<?php
namespace Leafcutter\Content\Assets;

use Leafcutter\Common\Collections\CollectableInterface;
use Leafcutter\Common\UrlInterface;

abstract class AbstractAsset implements AssetInterface
{
    protected $name;
    protected $url;
    protected $outputUrl;
    protected $dateModified;

    public function __construct(UrlInterface $url)
    {
        $this->setUrl($url);
        $this->setOutputUrl($url);
    }

    public function __toString()
    {
        return '<a href="'.$this->getOutputUrl().'">'.$this->getName().'</a>';
    }

    public function getDateModified() : ?int
    {
        return $this->dateModified;
    }

    public function setDateModified(int $date)
    {
        $this->dateModified = $date;
    }

    public function isEmpty() : bool
    {
        return !strlen(trim($this->getContent()));
    }

    public function setOutputBaseUrl(string $base)
    {
        $this->outputUrl->setBase($base);
    }

    public function setOutputContext(string $context)
    {
        $this->outputUrl->setContext($context);
    }

    public function getName() : string
    {
        return $this->name ?? preg_replace('/\.[^\.]+$/', '', $this->getFilename());
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function getFilename() : string
    {
        return $this->outputUrl->getFilename();
    }

    public function setFilename(string $name)
    {
        $url = $this->getOutputUrl();
        $url->setFilename($name);
        $this->setOutputUrl($url);
    }

    public function setExtension(string $ext)
    {
        $filename = preg_replace('/\.[^\.]+$/', ".$ext", $this->outputUrl->getFilename());
        if ($filename != $this->outputUrl->getFilename()) {
            $this->setFilename($filename);
        }
    }

    public function getExtension() : string
    {
        return strtolower(preg_replace('/^.*\./', '', $this->outputUrl->getFilename()));
    }

    public function getMime() : string
    {
        return $this->mimes()->getMimeType($this->getExtension());
    }

    public function setMime(string $mime)
    {
        if ($ext = $this->mimes()->getExtension($mime)) {
            $this->setExtension($ext);
        }
    }

    public function getUrl() : UrlInterface
    {
        return clone $this->url;
    }

    public function setUrl(UrlInterface $url)
    {
        $this->url = clone $url;
    }

    public function getOutputUrl() : UrlInterface
    {
        return clone $this->outputUrl;
    }

    public function setOutputUrl(UrlInterface $url)
    {
        if (strpos($url->getFilename(), '.') === false) {
            throw new \Exception("Asset external URL filename must include a file extension. Tried to use ".$url->getFilename());
        }
        if (substr($url->getFilename(), 0, 1) == '.') {
            throw new \Exception("Asset external URL can't be a dotfile. Tried to use ".$url->getFilename());
        }
        $this->outputUrl = clone $url;
        $this->outputUrl->setArgs([]);//no args allowed in external urls
        $this->outputUrl->setPrefix('');//no prefixes either
    }

    protected static function mimes()
    {
        static $mimes;
        $mimes = $mimes ?? new \Mimey\MimeTypes;
        return $mimes;
    }
}
