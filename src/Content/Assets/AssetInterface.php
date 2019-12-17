<?php
namespace Leafcutter\Content\Assets;

use Leafcutter\Common\Collections\CollectableInterface;
use Leafcutter\Common\UrlInterface;
use Leafcutter\Leafcutter;

interface AssetInterface extends CollectableInterface
{
    public function __construct(UrlInterface $url);

    public function getName() : string;
    public function setName(string $name);

    public function getFilename() : string;
    public function setFilename(string $name);

    public function getDateModified() : ?int;
    public function setDateModified(int $date);

    public function getMime() : string;
    public function setMime(string $mime);

    public function getFilesize() : int;
    public function isEmpty() : bool;

    public function getUrl() : UrlInterface;
    public function setUrl(UrlInterface $url);

    public function getOutputUrl() : UrlInterface;
    public function setOutputUrl(UrlInterface $url);

    public function setOutputFile(string $file);
    public function setOutputContext(string $context);
    public function setOutputBaseUrl(string $base);

    public function getContent() : string;
    public function getHash() : string;
}
