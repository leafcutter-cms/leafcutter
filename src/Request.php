<?php
namespace Leafcutter;

class Request extends Common\Url
{

    public static function createFromGlobals() : Request
    {
        $r = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        $string = $r->getPathInfo();
        if ($args = $args ?? $_GET) {
            $string .= '?'.http_build_query($args);
        }
        $url = Request::createFromString($string);
        $url->setBase($r->getSchemeAndHttpHost().$r->getBasePath());
        return $url;
    }

}
