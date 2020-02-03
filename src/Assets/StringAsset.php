<?php
namespace Leafcutter\Assets;

use Leafcutter\Common\Filesystem;
use Leafcutter\URL;

class StringAsset extends AbstractAsset
{
    protected $content;

    public function __construct(URL $url, string $content)
    {
        parent::__construct($url);
        $this->content = $content;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function hash(): string
    {
        return hash('md5', $this->content);
    }

    public function size(): int
    {
        return strlen($this->content());
    }

    public function setOutputFile(string $path)
    {
        parent::setOutputFile($path);
        $fs = new Filesystem();
        $fs->put($this->content(), $path);
    }
}
