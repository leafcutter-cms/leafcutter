<?php
namespace Leafcutter\Content\Images;

use Leafcutter\Common\UrlInterface;
use Leafcutter\Leafcutter;
use Leafcutter\Common\Collections\Collection;

class Gallery extends Collection
{
    protected $thumbnail;
    protected $full;

    public function __toString()
    {
        $out = '<div class="gallery leafcutterGallery" data-lightbox-group="'.$this->getHash().'">';
        foreach ($this as $img) {
            $out .= PHP_EOL.$img->thumbnail(
                $this->thumbnail,
                $this->full
            );
        }
        $out .= PHP_EOL.'</div>';
        return $out;
    }
}
