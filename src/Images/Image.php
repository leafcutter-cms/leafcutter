<?php
namespace Leafcutter\Images;

use Leafcutter\Leafcutter;
use Leafcutter\URL;

class Image
{
    protected $url;
    protected $file;
    protected $thumb = null;
    protected $leafcutter;
    protected $meta = ['date' => []];

    public function __construct(URL $url, string $file, Leafcutter $leafcutter)
    {
        if (!file_exists($file)) {
            throw new \Exception("Error creating Image from non-existent file: " . $file, 1);
        }
        $this->url = $url;
        $this->file = $file;
        $this->leafcutter = $leafcutter;
        // extract some metadata from file
        $this->meta('date.modified', filemtime($this->file));
    }

    public function __toString()
    {
        $string = $this->default()->__toString();
        return $string;
    }

    public function meta(string $key, $value = null)
    {
        if ($value !== null) {
            $this->meta[$key] = $value;
            $this->parseDatesInMeta();
        }
        return @$this->meta[$key];
    }

    protected function parseDatesInMeta()
    {
        foreach ($this->meta['date'] as $k => $v) {
            $this->meta["date.$k"] = is_int($v) ? intval($v) : strtotime($v);
        }
    }

    public function name()
    {
        return $this->generateName();
    }

    public function title()
    {
        return $this->generateName();
    }

    protected function generateName()
    {
        $name = $this->url->pathFile() ?? "Untitled image";
        $name = preg_replace('@\.[a-z]+$@', '', $name);
        $name = preg_replace('@[_\-\.]+@', ' ', $name);
        $name = ucfirst(trim($name));
        return $name;
    }

    public function thumbnail($thumbnail = 'thumbnail', $full = 'full', $alternate = null)
    {
        if (!is_array($thumbnail)) {
            $thumbnail = $this->leafcutter->config("images.presets.$thumbnail") ?? $this->leafcutter->config('images.presets.thumbnail');
        }
        if (!is_array($full)) {
            $full = $this->leafcutter->config("images.presets.$full") ?? $this->leafcutter->config('images.presets.full');
        }
        $thumbnail = $this->query($thumbnail);
        $full = $this->query($full);
        return '<a href="' . $full->publicUrl() . '" class="thumbnail leafcutterThumbnail" target="lightbox">' . $thumbnail . '</a>';
    }

    function default(): ImageAsset {
        return $this->preset('default');
    }

    public function crop(int $width, int $height = null): ImageAsset
    {
        $height = $height ?? $width;
        return $this->query([
            'crop' => "{$height}x{$width}",
        ]);
    }

    public function fit(int $width, int $height = null): ImageAsset
    {
        $height = $height ?? $width;
        return $this->query([
            'fit' => "{$height}x{$width}",
        ]);
    }

    public function query(array $query): ImageAsset
    {
        $url = clone $this->url;
        $url->setQuery($query);
        return $this->leafcutter->assets()->get($url);
    }

    public function preset(string $preset = 'default'): ImageAsset
    {
        $query = $this->leafcutter->config("images.presets.$preset") ?? $this->leafcutter->config('images.presets.default');
        return $this->query($query);
    }
}
