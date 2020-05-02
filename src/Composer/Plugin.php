<?php
namespace Leafcutter\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    protected $composer;
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function postAutoloadDump($event)
    {
        var_dump('autoload event?');
    }

    public static function getSubscribedEvents()
    {
        return array(
            'post-autoload-dump' => 'postAutoloadDump',
        );
    }
}
