<?php
namespace Leafcutter\Common;

interface UrlInterface
{
    public static function createFrom(UrlInterface $url) : UrlInterface;

    public function __toString();
    public function getHash() : string;

    public function setBase(string $base);
    public function setPrefix(string $prefix);
    public function setContext(string $context);
    public function setFilename(string $filename);
    public function setArgs(array $args);
    public function setFragment(string $fragment);

    public function getBase() : string;
    public function getPrefix($decode=true) : string;
    public function getContext($decode=true) : string;
    public function getFilename($decode=true) : string;
    public function getArgs() : array;
    public function getQueryString() : string;
    public function getFragment($decode=true) : string;
}
