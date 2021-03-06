<?php
namespace Leafcutter\Images;

use Leafcutter\Assets\AbstractAsset;
use Leafcutter\Leafcutter;
use Leafcutter\URL;

class ImageAsset extends AbstractAsset
{
    protected $source;
    protected $outputFile;

    public function __construct(URL $url, string $source)
    {
        parent::__construct($url);
        $this->source = $source;
        $this->meta('date.modified', filemtime($source));
    }

    public function __toString()
    {
        return '<img src="' . $this->publicUrl() . '" alt="' . $this->name() . '" />';
    }

    protected function generateName()
    {
        $name = $this->filename() ?? "Untitled image";
        $name = preg_replace('@\.[a-z]+$@', '', $name);
        $name = preg_replace('@[_\-\.]+@', ' ', $name);
        $name = ucfirst(trim($name));
        return $name;
    }

    public function setOutputFile(string $path)
    {
        $this->outputFile = $path;
        Leafcutter::get()->images()->generate(
            $this->source,
            $this->outputFile,
            $this->url()->query()
        );
    }

    public function hash(): string
    {
        return hash('md5', serialize([
            $this->url()->query(),
            basename($this->source),
            filesize($this->source),
            filemtime($this->source),
        ]));
    }

    public function content(): string
    {
        return \file_get_contents($this->outputFile);
    }

    public function size(): int
    {
        return \filesize($this->outputFile);
    }
}
