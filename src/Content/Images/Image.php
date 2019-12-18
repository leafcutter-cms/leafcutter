<?php
namespace Leafcutter\Content\Images;

use Leafcutter\Common\UrlInterface;
use Leafcutter\Leafcutter;
use Leafcutter\Common\Collections\CollectableInterface;

class Image implements CollectableInterface
{
    protected $path;
    protected $thumb = null;
    protected $leafcutter;

    public function __construct(string $path, Leafcutter $leafcutter)
    {
        $this->path = $path;
        $this->leafcutter = $leafcutter;
    }

    public function getDateModified() : int
    {
        return $this->default()->getDateModified();
    }

    public function getName() : string
    {
        return $this->default()->getName();
    }

    public function getHash() : string
    {
        return hash('crc32', $this->path);
    }

    public function __toString()
    {
        $string = $this->default()->__toString();
        return $string;
    }

    public function thumbnail($thumbnail='thumbnail', $full='full', $alternate=null)
    {
        if (!is_array($thumbnail)) {
            $thumbnail = $this->leafcutter->config("images.presets.$thumbnail") ?? $this->leafcutter->config('images.presets.thumbnail');
        }
        if (!is_array($full)) {
            $full = $this->leafcutter->config("images.presets.$full") ?? $this->leafcutter->config('images.presets.full');
        }
        $thumbnail = $this->args($thumbnail);
        $full = $this->args($full);
        return '<a href="'.$full->getUrl().'" class="thumbnail leafcutterThumbnail" target="lightbox">'.$thumbnail.'</a>';
    }

    public function default() : ImageAsset
    {
        return $this->preset('default');
    }

    public function crop(int $width, int $height=null) : ImageAsset
    {
        $height = $height ?? $width;
        return $this->args([
            'crop' => "{$height}x{$width}"
        ]);
    }

    public function fit(int $width, int $height=null) : ImageAsset
    {
        $height = $height ?? $width;
        return $this->args([
            'fit' => "{$height}x{$width}"
        ]);
    }

    public function args(array $args) : ImageAsset
    {
        $path = $this->path.'?'.http_build_query($args);
        return $this->leafcutter->assets()->get($path);
    }

    public function preset(string $preset='default') : ImageAsset
    {
        $args = $this->leafcutter->config("images.presets.$preset") ?? $this->leafcutter->config('images.presets.default');
        $path = $this->path.'?'.http_build_query($args);
        return $this->leafcutter->assets()->get($path);
    }
}
