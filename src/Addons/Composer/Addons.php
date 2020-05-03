<?php
namespace Leafcutter\Addons\Composer;

class Addons
{
    public static function addons(): array
    {
        if (!file_exists(__DIR__ . '/addons.txt')) {
            return [];
        }
        return array_filter(preg_split('/[\r\n]+/', file_get_contents(__DIR__ . '/addons.txt')));
    }
}
