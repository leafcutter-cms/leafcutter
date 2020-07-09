<?php
namespace Leafcutter;

class URLFactory
{
    private static $context = [];
    private static $site = [];

    /**
     * Compare a given URL to the actual current URL, and return true if a
     * normalization redirect should be done.
     *
     * Doesn't compare scheme by default, because that's not super reliable,
     * especially if there are proxies involved.
     *
     * Can be used to normalize the actual current URL to any given URL, if
     * $current isn't given it will be pulled using current()
     *
     * @param URL $current
     * @param boolean $useScheme
     * @param boolean $fixSlashes
     * @return boolean whether a redirect should be done
     */
    public static function normalizeCurrent(URL $current = null, $useScheme = false, $fixSlashes = true): bool
    {
        // get computed current URL, including fixing trailing slashes if necessary
        $current = ($current ?? static::current());
        if ($fixSlashes && $current->path() != 'favicon.ico' && !preg_match('@(/|\.html)$@', $current->path())) {
            $current->setPath($current->path() . '/');
        }
        $currentCmp = $current;
        // get actual current URL
        $actual = $actualCmp = static::currentActual();
        // strip scheme if not needed
        if (!$useScheme) {
            $currentCmp = preg_replace('/^https?:/', ':', $current);
            $actualCmp = preg_replace('/^https?:/', ':', $actual);
        }
        // do comparison
        return $currentCmp !== $actualCmp;
    }

    /**
     * Get the current actual URL, as well as possible.
     *
     * @return string
     */
    public static function currentActual(): string
    {
        $protocol = @$_SERVER['HTTPS'] === 'on' ? "https" : "http";
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * Begin a site context, which will be used by URLs to determine the
     * site-specific parsings, like sitePath() or inSite(). Site contexts
     * are a stack, so that different sites can be used non-sequentially.
     *
     * Specified base path must include a trailing slash.
     *
     * @param string $base
     * @return void
     */
    public static function beginSite(string $base)
    {
        static::$site[] = new URL($base);
    }

    /**
     * Get the current site context's base URL.
     *
     * @return URL|null
     */
    public static function site(): ?URL
    {
        if (static::$site) {
            return clone end(static::$site);
        } else {
            return null;
        }
    }

    /**
     * End/discard the current site context.
     *
     * @return void
     */
    public static function endSite()
    {
        array_pop(static::$site);
    }

    /**
     * Begin a new context, to be used when parsing relative URLs. Given
     * context may include a trailing filename, query, and/or fragment, but
     * only the directory portion of the path will actually be used for
     * parsing relative URLs from strings.
     *
     * Contexts are a stack, so the can be started/stopped and used in a
     * non-sequential manner.
     *
     * @param string|URL $context
     * @return void
     */
    public static function beginContext($context = null)
    {
        if (!$context) {
            $context = static::site();
        }
        static::$context[] = static::normalize($context);
    }

    /**
     * Get the current context URL.
     *
     * @return URL|null
     */
    public static function context(): ?URL
    {
        if (static::$context) {
            return clone end(static::$context);
        } else {
            return static::site();
        }
    }

    /**
     * End/discard the current context.
     *
     * @return void
     */
    public static function endContext()
    {
        array_pop(static::$context);
    }

    /**
     * Transform non-URL inputs into URLs.
     *
     * @param string|URL $input
     * @return URL
     */
    public static function normalize($input): URL
    {
        if ($input instanceof URL) {
            $input = clone $input;
            return $input;
        } else {
            return static::normalize(new URL($input));
        }
    }

    /**
     * Get the current URL.
     *
     * @return URL
     */
    public static function current(): URL
    {
        static $current;
        if (!$current) {
            $current = new URL($_SERVER['REQUEST_URI']);
            $current->setQuery($_GET);
        }
        return clone $current;
    }

}
