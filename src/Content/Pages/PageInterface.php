<?php
namespace Leafcutter\Content\Pages;

use Flatrr\SelfReferencingFlatArray;
use Leafcutter\Common\Collections\CollectableInterface;
use Leafcutter\Common\UrlInterface;

interface PageInterface extends CollectableInterface
{
    public function __construct(string $content, UrlInterface $url);

    public function getName() : string;
    public function setName(string $name);
    public function getMeta() : SelfReferencingFlatArray;

    public function getDateModified() : ?int;
    public function setDateModified(int $date);

    public function getUrl() : UrlInterface;
    public function setUrl(UrlInterface $url);

    public function getRawContent();
    public function getContent($unwrap=false) : string;
    public function setContent($content);
    public function getChildren() : array;

    public function getTemplate() : ?string;
    public function setTemplate(string $template);
}
