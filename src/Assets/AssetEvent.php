<?php
namespace Leafcutter\Assets;

use Leafcutter\URL;

class AssetEvent
{
    protected $asset, $url;

    public function __construct(AssetInterface $asset, URL $url)
    {
        $this->asset = $asset;
        $this->url = $url;
    }

    public function setAsset(AssetInterface $asset)
    {
        $this->asset = $asset;
    }

    public function asset(): AssetInterface
    {
        return $this->asset;
    }

    public function url(): URL
    {
        return clone $this->url;
    }
}
