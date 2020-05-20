<?php
namespace Leafcutter\Composer;

class Themes
{
    public static function dir(): ?string
    {
        if (glob(__DIR__ . '/themes/', GLOB_ONLYDIR)) {
            return __DIR__ . '/themes/';
        } else {
            return null;
        }
    }
}
