<?php
namespace Leafcutter\Assets;

use Flatrr\SelfReferencingFlatArray;
use Leafcutter\Common\MIME;
use Leafcutter\URL;

abstract class AbstractAsset implements AssetInterface
{
    protected $url, $publicUrl, $filename, $meta, $outputFile;

    public function __construct(URL $url)
    {
        $this->url = $url;
        $this->meta = new SelfReferencingFlatArray([
            'date.generated' => time(),
        ]);
    }

    public function meta(string $key, $value = null)
    {
        if ($value !== null) {
            $this->meta[$key] = $value;
            if (substr($key, 0, 5) == 'date.') {
                foreach ($this->meta['date'] as $k => $v) {
                    $this->meta["date.$k"] = intval($v) ?? strtotime($v);
                }
            }
        }
        return $this->meta[$key];
    }

    public function setFilename(string $filename)
    {
        $this->filename = $filename;
    }

    public function name(): string
    {
        if ($this->meta('name')) {
            return $this->meta('name');
        }
        if ($this->meta('title')) {
            return $this->meta('title');
        }
        return $this->generateName();
    }

    public function title(): string
    {
        if ($this->meta('title')) {
            return $this->meta('title');
        }
        if ($this->meta('name')) {
            return $this->meta('name');
        }
        return $this->generateName();
    }

    protected function generateName()
    {
        return $this->filename() ?? 'Untitled file';
    }

    public function filename(): string
    {
        if ($this->filename) {
            return $this->filename;
        } elseif ($this->publicUrl) {
            return $this->publicUrl->pathFile();
        } else {
            return $this->url->pathFile();
        }
    }

    public function extension(): ?string
    {
        if (preg_match('@\.([a-z0-9]+)$@', $this->filename(), $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function mime(): ?string
    {
        if ($ext = $this->extension()) {
            return MIME::mime($ext);
        }
        return null;
    }

    public function url(): URL
    {
        return clone $this->url;
    }

    public function setUrl(URL $url)
    {
        $this->url = clone $url;
    }

    public function publicUrl(): URL
    {
        return clone $this->publicUrl;
    }

    public function setPublicUrl(URL $url)
    {
        $this->publicUrl = clone $url;
    }

    public function hash(): string
    {
        return hash('md5', serialize([
            $this->content(),
        ]));
    }

    public function setOutputFile(string $path)
    {
        $this->outputFile = $path;
    }

    public function outputFile(): ?string
    {
        return $this->outputFile;
    }
}
