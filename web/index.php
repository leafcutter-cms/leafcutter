<?php
namespace Leafcutter;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

require(__DIR__.'/../vendor/autoload.php');

date_default_timezone_set('America/Denver');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = new Config\Config();
$config->readDir(__DIR__.'/../config');
$config['base_dir'] = realpath(__DIR__.'/..');

$leafcutter = new Leafcutter($config);
$leafcutter->logger()->pushHandler(
    new StreamHandler(
        __DIR__.'/../leafcutter.log',
        Logger::ERROR
    )
);
$leafcutter->cache()->setCache(new ChainAdapter([
    new ArrayAdapter,
    new FilesystemAdapter('cache', 60, __DIR__.'/../cache')
]));

$leafcutter->themes()->loadTheme('leafcutter');
// $leafcutter->themes()->loadTheme('leafcutter_core');
$leafcutter->content()->addPrimaryDirectory(__DIR__.'/../content');

$request = Request::createFromGlobals();
$response = $leafcutter->handleRequest($request);
$leafcutter->outputResponse($response);
