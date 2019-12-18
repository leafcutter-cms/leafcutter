<?php
namespace Leafcutter\Content\Images;

class ImageAsset extends \Leafcutter\Content\Assets\CallbackAsset
{
    public function __toString()
    {
        return '<img src="'.$this->getUrl().'" alt="'.$this->getName().'" />';
    }
}
