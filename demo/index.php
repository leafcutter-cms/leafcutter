<?php
namespace Leafcutter;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set("America/Denver");

//initialize configuration
$config = new Config\Config();
$config['directories.base'] = __DIR__;
$config->readDir(__DIR__ . '/config/', null, true);
$config->readFile(__DIR__ . '/config/env.yaml', null, true);

//attempt to guess URL
if (!$config['site.url']) {
    $url = new URL('/');
    $url->setHost($_SERVER['HTTP_HOST']);
    $url->setPath(preg_replace('/index\.php$/','',$_SERVER['SCRIPT_NAME']));
    $url->setPort($_SERVER['SERVER_PORT']);
    $config['site.url'] = $url->__toString();
}

//initialize logger
$logger = new Logger('leafcutter');
$logger->pushHandler(
    new StreamHandler(__DIR__ . '/debug.log', Logger::DEBUG)
);

//initialize URL site and context
URLFactory::beginSite($config['site.url']);
URLFactory::beginContext(); //calling without argument sets context to site

//normalize current URL
URLFactory::normalizeCurrent();

//initialize CMS context
Leafcutter::beginContext($config, $logger);
$leafcutter = Leafcutter::get();
$leafcutter->content()->addDirectory(__DIR__ . '/content');

//build response from URL
$response = $leafcutter->buildResponse(URLFactory::current());

//render response
$response->renderHeaders();
$response->renderContent();
