<?php
namespace Leafcutter;

// The base logger is a Monolog Logger
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Any Symfony cache adapters should work
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

// nothing fancy here, just uses Composer's autoloaders
require(__DIR__.'/../vendor/autoload.php');

// just makes debugging easier when using this example as dev environment
date_default_timezone_set('America/Denver');
error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
Instantiate a config object and read in everything it
can find in a given directory.
*/
$config = new Config\Config();
$config->readDir(__DIR__.'/config');
$config['base_dir'] = realpath(__DIR__);

/*
Leafcutter needs to be instantiated from a config object
*/
$leafcutter = new Leafcutter($config);

/*
A good first thing to do after instantiating Leafcutter is
to set up a logger. This example just logs error-level and
above to a file.
*/
$leafcutter->logger()->pushHandler(
    new StreamHandler(
        __DIR__.'/leafcutter.log',
        Logger::DEBUG
    )
);

/*
This example uses a chain of an Array and Filesystem cache
adapters. If your server supports APCu, no configuration
should be necessary to use it.
*/
$leafcutter->cache()->setCache(new ChainAdapter([
    new ArrayAdapter,
    new FilesystemAdapter('cache', 60, __DIR__.'/cache')
]));

/*
The bare-minimum site-specific configuration is pretty much
just loading a theme and specifying a primary content directory.
*/
$leafcutter->themes()->loadTheme('leafcutter');
$leafcutter->content()->addPrimaryDirectory(__DIR__.'/content');

/*
Create a Request object from the current globals, handle it with
Leafcutter to create a Response, and use Leafcutter to output
that Response to the browser.
*/
$request = Request::createFromGlobals();
$response = $leafcutter->handleRequest($request);
$leafcutter->outputResponse($response);
