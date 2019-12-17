<?php
namespace Leafcutter\Common;

class Url implements UrlInterface
{
    protected $base;
    protected $prefix;
    protected $context;
    protected $filename;
    protected $args;
    protected $fragment;

    public function __construct(string $base='', string $prefix='', string $context='/', string $filename='', array $args=[], string $fragment='')
    {
        $this->setBase($base);
        $this->setPrefix($prefix);
        $this->setContext($context);
        $this->setFilename($filename);
        $this->setFragment($fragment);
        $this->setArgs($args);
    }

    public function getFullContext()
    {
        return $this->getPrefix(false).$this->getContext(false);
    }

    public function getFullPath()
    {
        return $this->getPrefix(false).$this->getContext(false).$this->getFilename(false);
    }

    public static function createFromString(string $string, UrlInterface $from=null) : ?UrlInterface
    {
        $class = \get_called_class();
        if ($from) {
            $url = $class::createFrom($from);
        } else {
            $url = new $class;
        }
        $parts = parse_url($string);
        //$from's context only matters if there isn't a host in $string
        //and $string doesn't start with $from's base
        if ($from) {
            if (@$parts['host']) {
                if ($from->getBase() && strpos($string, $from->getBase()) !== 0) {
                    $string = substr($string, strlen($from->getBase()));
                }
            }
            if (substr($string, 0, 1) != '/') {
                $string = $from->getFullContext().$string;
            }
            $parts = parse_url($string);
        }
        //extract prefix from path
        if (preg_match('@^/~([a-zA-Z0-9]+)/@', $parts['path'], $matches)) {
            $parts['prefix'] = $matches[1];
            $parts['path'] = substr($parts['path'], strlen($parts['prefix'])+2);
        } else {
            $parts['prefix'] = '';
        }
        //extract filename from path
        if (substr($parts['path'], -1) == '/') {
            $parts['context'] = $parts['context'] ?? $parts['path'];
            $parts['filename'] = '';
        } else {
            $parts['context'] = $parts['context'] ?? dirname($parts['path']).'/';
            if ($parts['context'] == '\\/' || $parts['context'] == '//') {
                $parts['context'] = '/';
            }
            $parts['filename'] = basename($parts['path']);
        }
        //extract args
        $parts['args'] = [];
        if (@$parts['query']) {
            parse_str($parts['query'], $parts['args']);
        }
        //finish construction and return
        $url->setPrefix($parts['prefix']);
        $url->setContext($parts['context']);
        $url->setFilename($parts['filename']);
        $url->setArgs($parts['args']);
        $url->setFragment(@$parts['fragment']??'');
        return $url;
    }

    public static function createFrom(UrlInterface $url) : UrlInterface
    {
        $class = \get_called_class();
        $new = new $class;
        $new->setBase($url->getBase());
        $new->setPrefix($url->getPrefix());
        $new->setContext($url->getContext());
        $new->setFilename($url->getFilename());
        $new->setArgs($url->getArgs());
        $new->setFragment($url->getFragment());
        return $new;
    }

    public function getPath() : string
    {
        return $this->getContext().$this->getFilename();
    }

    public function __toString()
    {
        return $this->getBase().
            $this->getPrefix(false).
            $this->getContext(false).
            $this->getFilename(false).
            $this->getQueryString().
            $this->getFragment(false);
    }

    public function getHash() : string
    {
        return hash('crc32', serialize([
            $this->base,
            $this->prefix,
            $this->context,
            $this->filename,
            $this->args,
            $this->fragment
        ]));
    }

    public function setBase(string $base)
    {
        if ($base) {
            if (preg_match('@/$@', $base)) {
                throw new \Exception("URL base must not have a trailing slash (tried to use: $base)");
            }
            if (!filter_var($base, FILTER_VALIDATE_URL)) {
                throw new \Exception("URL base must be a valid URL (tried to use: $base)");
            }
        }
        $this->base = $base;
    }

    public function setPrefix(string $prefix)
    {
        if (preg_match('@[^A-Za-z0-9%]@', $prefix)) {
            throw new \Exception("Url prefix must only contain alphanumerics (tried to use: $prefix)");
        }
        $this->prefix = $prefix;
    }

    public function setContext(string $context)
    {
        if (!preg_match('@/$@', $context)) {
            throw new \Exception("URL context must have a trailing slash (tried to use: $context)");
        }
        if (!preg_match('@^/@', $context)) {
            throw new \Exception("URL context must have a leading slash (tried to use: $context)");
        }
        if (preg_match('@[^A-Za-z0-9\-_\./%]@', $context)) {
            throw new \Exception("Url context must only contain alphanumerics plus /, -, _, and . (tried to use: $context)");
        }
        if (strpos($context, '//') !== false) {
            throw new \Exception("Url context must not have two slashes in a row (tried to use: $context)");
        }
        $this->context = $context;
    }

    public function setFilename(string $filename)
    {
        if (preg_match('@[^A-Za-z0-9\-_\.%]@', $filename)) {
            throw new \Exception("Url filename must only contain alphanumerics plus -, _, and . (tried to use: $filename)");
        }
        $this->filename = $filename;
    }

    public function setArgs(array $args)
    {
        ksort($args);
        $this->args = $args;
    }

    public function setFragment(string $fragment)
    {
        if ($fragment != '') {
            if (preg_match('@[^A-Za-z0-9\-_\.~%]@', $fragment)) {
                throw new \Exception("Url fragment must only contain alphanumerics plus -, _, ~, and . (tried to use: $fragment)");
            }
        }
        $this->fragment = $fragment;
    }

    public function getBase() : string
    {
        return $this->base;
    }

    public function getPrefix($decode=true) : string
    {
        if ($decode) {
            return urldecode($this->prefix);
        } elseif ($this->prefix) {
            return '/~'.$this->prefix;
        } else {
            return '';
        }
    }

    public function getContext($decode=true) : string
    {
        if (!$decode) {
            return $this->context;
        } else {
            return urldecode($this->context);
        }
    }

    public function getFilename($decode=true) : string
    {
        if ($decode) {
            return urldecode($this->filename);
        } else {
            return $this->filename;
        }
    }

    public function getArgs() : array
    {
        return $this->args;
    }

    public function getQueryString() : string
    {
        if (!$this->args) {
            return '';
        } else {
            return '?'.http_build_query($this->args);
        }
    }

    public function getFragment($decode=true) : string
    {
        if ($decode) {
            return urldecode($this->fragment);
        } elseif ($this->fragment) {
            return '#'.$this->fragment;
        } else {
            return '';
        }
    }
}
