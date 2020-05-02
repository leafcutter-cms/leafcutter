<?php
namespace Leafcutter\Composer;

class ComposerAddons
{
    public static function addons(): array
    {
        if (!file_exists(__DIR__ . '/addons.txt')) {
            return [];
        }
        return array_filter(preg_split('/[\r\n]+/', file_get_contents(__DIR__ . '/addons.txt')));
    }
}
