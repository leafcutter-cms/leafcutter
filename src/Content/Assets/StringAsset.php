<?php
namespace Leafcutter\Content\Assets;

use Leafcutter\Common\UrlInterface;
use Leafcutter\Leafcutter;
use Symfony\Component\Filesystem\Filesystem;

class StringAsset extends AbstractAsset
{
    public function __construct(UrlInterface $url, string $content='')
    {
        parent::__construct($url);
        $this->content = $content;
        $this->dateModified = time();
    }

    public function setOutputFile(string $file)
    {
        $this->outputFile = $file;
        if (!file_exists($this->outputFile)) {
            $filesystem = new Filesystem();
            $filesystem->dumpFile($this->outputFile, $this->content);
        }
    }

    public function getOutputFile() : string
    {
        return $this->outputFile;
    }

    public function getFilesize() : int
    {
        return strlen($this->content);
    }

    public function getContent() : string
    {
        return $this->content;
    }

    public function getHash() : string
    {
        return hash('crc32', $this->content);
    }
}
