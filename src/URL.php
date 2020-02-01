<?php
namespace Leafcutter;

class URL
{
    protected $scheme;
    protected $host;
    protected $port;
    protected $path;
    protected $query = [];
    protected $fragment;

    /**
     * Construct a URL from a given string. String may be relative or absolute,
     * and may include a host/protocol or not.
     *
     * If a relative URL is given, it will be interpreted relative to the current
     * URLFactory context.
     *
     * If an absolute URL is given without a domain, it will be interpreted
     * relative to the current URLFactory site context.
     *
     * @param string $string
     * @param URL $context
     */
    public function __construct(string $string, URL $context = null)
    {
        // set context
        if ($context) {
            URLFactory::beginContext($context);
        }
        try {
            // allow @ctx or @ prefixes for context/site, respectively
            $string = preg_replace('/^@\//', URLFactory::site(), $string);
            $string = preg_replace('/^@ctx\//', URLFactory::context(), $string);
            // built-in parser is good
            $parsed = parse_url($string);
            // set scheme
            if (@$parsed['scheme']) {
                $this->setScheme($parsed['scheme']);
            }
            // set host
            if (@$parsed['host']) {
                $this->setHost($parsed['host']);
            }
            // set port
            if (@$parsed['port']) {
                $this->setPort($parsed['port']);
            }
            // path, query, fragment are now straightforward
            $this->setPath(@$parsed['path'] ?? '/');
            if (@$parsed['query']) {
                parse_str($parsed['query'], $query);
                $this->setQuery($query);
            }
            $this->setFragment($parsed['fragment'] ?? '');
        } catch (\Throwable $th) {
            // catch errors and end context before rethrowing them
            if ($context) {
                URLFactory::endContext();
            }
            throw $th;
        }
        // end context
        if ($context) {
            URLFactory::endContext();
        }
    }

    /**
     * Get the full URL as an encoded string ready to use in HTML.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->schemeString()
        . $this->hostString()
        . $this->portString()
        . $this->pathString()
        . $this->queryString()
        . $this->fragmentString()
        ;
    }

    /**
     * Get just the file extension of the URL's path
     *
     * @return string|null
     */
    public function extension(): ?string
    {
        if ($file = $this->pathFile()) {
            if (preg_match('@\.([a-z0-9]+)$@', $file, $matches)) {
                return strtolower($matches[1]);
            }
        }
        return null;
    }

    /**
     * Set just the file extension of the URL's path, if it
     * already exists.
     *
     * @param string $extension
     * @return void
     */
    public function setExtension(string $extension)
    {
        $this->path = preg_replace('@\.[a-z0-9]+$@', ".$extension", $this->path);
    }

    /**
     * Sort of sloppy tool for urlencoding, but avoiding double-encoding.
     * It's probably not the best method at the moment, but it gets the job done.
     *
     * @param string $str
     * @return string
     */
    protected static function encode(string $str): string
    {
        return str_replace('+', '%20', urlencode(urldecode($str)));
    }

    /**
     * Whether or not this URL is within the URL currently available
     * from URLFactory::site()
     *
     * @return boolean
     */
    public function inSite(): bool
    {
        return
        ($site = URLFactory::site()) &&
        // $site->scheme() == $this->scheme() &&
        $site->host() == $this->host() &&
        $site->port() == $this->port() &&
        strpos($this->path(), $site->path()) === 0;
    }

    /**
     * Return the siteFullPath() witha ny leading namespace portion removed.
     *
     * @return string|null
     */
    public function sitePath(): ?string
    {
        if (($path = $this->siteFullPath()) && ($ns = $this->siteNamespace())) {
            return substr($path, strlen($ns) + 2);
        } else {
            return $this->siteFullPath();
        }
    }

    /**
     * Return the namespace portion of the current siteFullPath() value,
     * if available.
     *
     * @return string|null
     */
    public function siteNamespace(): ?string
    {
        if ($path = $this->siteFullPath()) {
            $ns = preg_replace('/^@([^\/]+)\/.*$/', '$1', $path);
            return $ns != $path ? $ns : null;
        } else {
            return null;
        }
    }

    /**
     * Set only the namespace portion of the site path
     *
     * @param string $namespace
     * @return void
     */
    public function setSiteNamespace(string $namespace)
    {
        $sitePath = $this->sitePath();
        $prePath = substr($this->path(), 0, strlen($this->path()) - strlen($this->siteFullPath()));
        $namespace = $namespace ? "@$namespace/" : "/";
        $this->setPath("$prePath$namespace$sitePath");
    }

    /**
     * The portion of this URL's path with any prefix path in the URL
     * available from URLFactory::site() removed. Return value will
     * include any @namespace directory.
     *
     * Returned value must not have a leading slash.
     *
     * Returns null if URL is not within the site, or if there is
     * no site URL currently set in URLFactory.
     *
     * @return string|null
     */
    public function siteFullPath(): ?string
    {
        if ($this->inSite()) {
            $site = URLFactory::site();
            return substr($this->path(), strlen($site->path()));
        }
        return null;
    }

    /**
     * Return the trailing filename portion of the path.
     *
     * @return string
     */
    public function pathFile(): string
    {
        return preg_replace('@^.*/@', '', $this->path);
    }

    /**
     * Return the leading path portion of the path.
     *
     * @return string
     */
    public function pathDirectory(): string
    {
        return preg_replace('@/[^/]+$@', '/', $this->path);
    }

    /**
     * Set/parse the path of this URL. Input will be normalized,
     * cleaned up, and context and .. traversals will be resolved.
     *
     * If anything goes horribly wrong with parsing the given path,
     * this method is where it will probably manifest.
     *
     * @param string $input
     * @return void
     */
    public function setPath(string $input)
    {
        // normalize and clean up path
        $path = preg_replace('@[\\/]+@', '/', $input); // normalize slashes, strip repeated slashes
        $path = preg_replace('@/index\.html$@', '/', $path); // strip trailing index.html
        $path = explode('/', $path); // explode path
        $path = array_filter($path, function ($e) {
            return $e !== '.';
        });
        // place path in context
        if (@$path[0] !== '') {
            $context = URLFactory::context();
            $pathDirectory = $context->pathDirectory();
            if ($pathDirectory !== '/') {
                $pathDirectory = substr($pathDirectory, 0, strlen($pathDirectory) - 1);
            }
            $path = array_merge(explode('/', $pathDirectory), $path);
        }
        // resolve traversals
        foreach ($path as $i => $e) {
            if ($e === '..') {
                $path[$i - 1] = $path[$i] = false;
            }
        }
        $path = array_filter($path, function ($e) {
            return $e !== false;
        });
        // implode back into path
        $path = implode('/', $path);
        $path = preg_replace('@/+@', '/', $path); // strip repeated slashes
        if (!$path) {
            $path = '/';
        }
        $this->path = $path;
    }

    /**
     * Get the scheme with :// added for building a full URL string.
     *
     * @return string
     */
    public function schemeString(): string
    {
        return $this->scheme() . '://';
    }

    /**
     * Get the host prepared for building a full URL string.
     *
     * @return string
     */
    public function hostString(): string
    {
        return $this->host();
    }

    /**
     * Get the port prepared as a string for building a full URL string,
     * with a : prepended. Returns an empty string if the specified port
     * is the default 80 or 443 for the scheme http or https, respectively.
     *
     * @return string
     */
    public function portString(): string
    {
        if ($this->scheme() === 'https' && $this->port() !== 443) {
            return ':' . $this->port();
        } elseif ($this->scheme() === 'http' && $this->port() !== 80) {
            return ':' . $this->port();
        } else {
            return '';
        }
    }

    /**
     * Get the path prepared for building a full URL string.
     *
     * @return string
     */
    public function pathString(): string
    {
        return str_replace('%2F', '/', self::encode($this->path()));
    }

    /**
     * Get the query string prepared for building a full URL string,
     * with a leading ? if necessary.
     *
     * @return string
     */
    public function queryString(): string
    {
        if ($this->query) {
            return '?' . http_build_query($this->query());
        } else {
            return '';
        }
    }

    /**
     * Get the fragment prepared for building a full URL string, with
     * a leading # if necessary.
     *
     * @return string
     */
    public function fragmentString(): string
    {
        if ($this->fragment) {
            return '#' . self::encode($this->fragment);
        } else {
            return '';
        }
    }

    /**
     * Get the URL scheme (http or https)
     *
     * @return string
     */
    public function scheme(): string
    {
        if (!$this->scheme) {
            if ($site = URLFactory::site()) {
                return $site->scheme();
            }
        }
        return $this->scheme ?? 'http';
    }

    /**
     * Get the URL host/domain
     *
     * @return string
     */
    public function host(): string
    {
        if (!$this->host) {
            if ($site = URLFactory::site()) {
                return $site->host();
            } else {
                throw new Exception("No site to get host from");
            }
        }
        return $this->host;
    }

    /**
     * Get the URL port
     *
     * @return integer
     */
    public function port(): int
    {
        if (!$this->port) {
            if ($site = URLFactory::site()) {
                return $site->port();
            } else {
                throw new Exception("No site to get port from");
            }
        }
        return $this->port;
    }

    /**
     * Get the URL path
     *
     * @return string
     */
    public function path(): string
    {
        return urldecode($this->path);
    }

    /**
     * Get the URL GET query as an array
     *
     * @return array
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * Get the URL fragment
     *
     * @return string
     */
    public function fragment(): string
    {
        return $this->fragment;
    }

    /**
     * Set scheme (http or https)
     *
     * @param string $scheme
     * @return void
     */
    public function setScheme(string $scheme)
    {
        $this->scheme = $scheme;
    }

    /**
     * Set host/domain
     *
     * @param string $host
     * @return void
     */
    public function setHost(string $host)
    {
        $this->host = $host;
    }

    /**
     * Set port
     *
     * @param integer $port
     * @return void
     */
    public function setPort(int $port)
    {
        $this->port = $port;
    }

    /**
     * Set GET query as an array, which will be normalized by sorting
     * input by key.
     *
     * @param array $query
     * @return void
     */
    public function setQuery(array $query)
    {
        ksort($query);
        $this->query = $query;
    }

    /**
     * Set fragment string
     *
     * @param string $fragment
     * @return void
     */
    public function setFragment(string $fragment)
    {
        $this->fragment = $fragment;
    }
}
