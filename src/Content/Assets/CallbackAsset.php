<?php
namespace Leafcutter\Content\Assets;

use Leafcutter\Common\UrlInterface;
use Leafcutter\Leafcutter;

class CallbackAsset extends AbstractAsset
{
    public function __construct(UrlInterface $url, string $hash=null, callable $callback_create=null, callable $callback_exists=null)
    {
        parent::__construct($url);
        $this->hash = $hash;
        $this->callback_create = $callback_create;
        $this->callback_exists = $callback_exists;
        $this->dateModified = time();
    }

    public function getOutputFile() : string
    {
        return $this->outputFile;
    }

    public function getFilesize() : int
    {
        return filesize($this->getOutputFile());
    }

    public function setOutputFile(string $file)
    {
        $this->outputFile = $file;
        if (!file_exists($file)) {
            call_user_func($this->callback_create, $this);
        }elseif ($this->callback_exists) {
            call_user_func($this->callback_exists, $this);
        }
        unset($this->callback_create);
        unset($this->callback_exists);
    }

    public function getSize() : int
    {
        return filesize($this->outputFile);
    }

    public function getContent() : string
    {
        return file_get_contents($this->outputFile);
    }

    public function getHash() : string
    {
        return $this->hash;
    }
}
