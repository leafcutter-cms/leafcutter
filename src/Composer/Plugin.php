<?php
namespace Leafcutter\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    public static function onPostAutoloadDump($event)
    {
        var_dump('autoload event?');
    }

    public static function getSubscribedEvents()
    {
        return array(
            'post-autoload-dump' => 'postAutoloadDump'
        );
    }
}