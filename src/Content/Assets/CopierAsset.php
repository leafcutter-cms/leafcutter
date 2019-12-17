<?php
namespace Leafcutter\Content\Assets;

use Leafcutter\Common\UrlInterface;
use Leafcutter\Leafcutter;
use Symfony\Component\Filesystem\Filesystem;

class CopierAsset extends AbstractAsset
{
    public function __construct(UrlInterface $url, string $sourceFile = null)
    {
        parent::__construct($url);
        $this->sourceFile = $sourceFile;
    }

    public function getDateModified() : ?int
    {
        return $this->dateModified ?? filemtime($this->sourceFile);
    }

    public function setOutputFile(string $file)
    {
        $this->outputFile = $file;
        if (!file_exists($this->outputFile)) {
            $filesystem = new Filesystem();
            // TODO: sort out symlinking of assets, needs to be configurable
            // if ($this->symlink()) {
            //     $filesystem->symlink($this->src, $this->getOutputFile(), true);
            // } else {
            $filesystem->copy($this->sourceFile, $this->outputFile);
            // }
        }
    }

    public function getFilesize() : int
    {
        return filesize($this->sourceFile);
    }

    public function getContent() : string
    {
        return file_get_contents($this->sourceFile);
    }

    public function getHash() : string
    {
        return hash_file('crc32', $this->sourceFile);
    }
}
