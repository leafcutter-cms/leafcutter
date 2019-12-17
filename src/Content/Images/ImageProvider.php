<?php
namespace Leafcutter\Content\Images;

use Leafcutter\Common\UrlInterface;
use Leafcutter\Leafcutter;
use Leafcutter\Common\SourceDirectoriesTrait;

class ImageProvider
{
    protected $leafcutter;
    protected $imageBuilder;
    const INPUT_FORMATS = [
        'jpg','jpeg','gif','png','wbmp','xbm','webp','bmp'
    ];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->imageBuilder = new ImageAssetBuilder($this->leafcutter);
        $this->leafcutter->hooks()->addSubscriber($this->imageBuilder);
    }

    public function pageGallery($page) : Gallery
    {
        $glob = $page->getUrl()->getFullContext().'*';
        return $this->gallery($glob);
    }

    public function gallery(string $glob=null) : Gallery
    {
        if ($glob) {
            return new Gallery($this->list($glob));
        } else {
            return new Gallery;
        }
    }

    public function list(string $glob) : array
    {
        $glob = $this->normalizeGlob($glob);
        $files = array_keys($this->leafcutter->content()->list($glob));
        return array_map(
            function ($e) {
                $img = new Image($e, $this->leafcutter);
                return $img;
            },
            $files
        );
    }

    protected function normalizeGlob(string $glob) : string
    {
        $glob = preg_replace('/\.[^\/]+$/', '', $glob);
        $glob .= '.{'.implode(',', static::INPUT_FORMATS).'}';
        return $glob;
    }
}
