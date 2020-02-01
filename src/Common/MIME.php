<?php
namespace Leafcutter\Common;

/**
 * Class for matching MIME types and file extensions.
 *
 * Uses the database file from jshttp/mime-db, which is then compiled down into
 * the file MIME_db.txt, which is what is actually read by this class at runtime.
 *
 * See: https://github.com/jshttp/mime-db
 */
class MIME
{
    /**
     * Provide an extension regex, and return the first MIME matching that
     * extension in the database.
     *
     * @param string $query_regex
     * @return string|null
     */
    public static function mime_regex(string $query_regex): ?string
    {
        static $mimes = [];
        if (!isset($mime[$query_regex])) {
            $mimes[$query_regex] = static::query(function ($mime, $exts) use ($query_regex) {
                foreach ($exts as $ext) {
                    if (preg_match($query_regex, $ext)) {
                        return $mime;
                    }
                }
                return null;
            });
        }
        return $mimes[$query_regex];
    }

    /**
     * Provide a MIME regex, and return the first extension matching that
     * MIME in the database.
     *
     * @param string $query_regex
     * @return string|null
     */
    public static function ext_regex(string $query_regex): ?string
    {
        static $exts = [];
        if (!isset($exts[$query_regex])) {
            $exts[$query_regex] = static::query(function ($mime, $exts) use ($query_regex) {
                if (preg_match($query_regex, $mime)) {
                    return \reset($exts);
                } else {
                    return null;
                }
            });
        }
        return $exts[$query_mime];
    }

    /**
     * Provide an extension, and return the first MIME matching that extension
     * in the database.
     *
     * @param string $query_ext
     * @return string|null
     */
    public static function mime(string $query_ext): ?string
    {
        static $mimes = [];
        if (!isset($mime[$query_ext])) {
            $mimes[$query_ext] = static::query(function ($mime, $exts) use ($query_ext) {
                if (in_array($query_ext, $exts)) {
                    return $mime;
                } else {
                    return null;
                }
            });
        }
        return $mimes[$query_ext];
    }

    /**
     * Provide a MIME string, and return the first extension matching that
     * MIME in the database.
     *
     * @param string $query_mime
     * @return string|null
     */
    public static function ext(string $query_mime): ?string
    {
        static $exts = [];
        if (!isset($exts[$query_mime])) {
            $exts[$query_mime] = static::query(function ($mime, $exts) use ($query_mime) {
                if ($mime == $query_mime) {
                    return \reset($exts);
                } else {
                    return null;
                }
            });
        }
        return $exts[$query_mime];
    }

    /**
     * Provide a function that will be passed a MIME and array of extensions
     * for every entry in the database. Will return the first non-null value
     * returned by that function.
     *
     * @param callable $fn
     * @return void
     */
    public static function query(callable $fn)
    {
        $out = null;
        $db = fopen(__DIR__ . '/MIME_db.txt', 'r');
        while (($line = fgets($db)) !== false && $out === null) {
            $exts = explode('|', trim($line));
            $mime = \array_shift($exts);
            $out = $fn($mime, $exts);
        }
        fclose($db);
        return $out;
    }

    /**
     * Call this method to compile the contents -f MIME_db.json into a fresh
     * copy of MIME_db.txt. This shouldn't need to be done very often, but
     * should be considered as part of the release cycle.
     *
     * @return void
     */
    public static function compile()
    {
        $db = json_decode(\file_get_contents(__DIR__ . '/MIME_db.json'), true);
        $out = fopen(__DIR__ . '/MIME_db.txt', 'w');
        foreach ($db as $mime => $info) {
            if (@$info['extensions']) {
                fputs($out, strtolower("$mime|" . implode("|", $info['extensions']) . "\n"));
            }
        }
        fclose($out);
    }
}
