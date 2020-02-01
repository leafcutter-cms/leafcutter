<?php
namespace Leafcutter\Assets;

use Leafcutter\Common\Filesystem;
use Leafcutter\URL;

class FileAsset extends AbstractAsset
{
    protected $file;

    public function __construct(URL $url, string $file)
    {
        parent::__construct($url);
        $this->file = $file;
    }

    public function content(): string
    {
        return file_get_contents($this->file);
    }

    public function size(): int
    {
        return filesize($this->file);
    }

    public function hash(): string
    {
        return hash_file('crc32', $this->file);
    }

    public function setOutputFile(string $path)
    {
        parent::setOutputFile($path);
        $fs = new Filesystem();
        $fs->copy($this->file, $path);
    }
}
