<?php
namespace Leafcutter\Content;

use Leafcutter\Common\UrlInterface;
use Leafcutter\Leafcutter;
use Leafcutter\Common\SourceDirectoriesTrait;

/**
 * This class provides the main interface for searching for files
 * within the content directories. It provides a consistent scheme
 * for locating files by path/name (which may include globs).
 * This class is where the Page and Asset providers get their lists
 * of potential filesystem matches for requested paths/URLs.
 *
 * The primary purpose of this class is to abstract away the
 * mechanisms of having content available in multiple directories,
 * as well as the ways those files may have ordering/publishing
 * cues in their filenames.
 */
class ContentProvider
{
    use SourceDirectoriesTrait;

    protected $leafcutter;
    protected $cache;

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->addDirectory(__DIR__.'/content');
        $this->cache = $this->leafcutter->cache(
            'ContentProvider',
            $this->leafcutter->config('cache.ttl.content_provider')
        );
    }

    /**
     * Hash the file list and modification times of all files represented
     * by a glob, and all neighbors and children of the directory.
     *
     * @param string $glob
     * @param boolean $listed
     * @return string
     */
    public function hash(string $glob) : string
    {
        if (substr($glob, -1) == '/') {
            $glob .= '*';
        }
        return $this->cache->get(
            'list.'.hash('crc32', $glob),
            function () use ($glob) {
                $hash = '';
                foreach ($this->list($glob) as $path => $file) {
                    if (!file_exists($file)) {
                        continue;
                    }
                    $hash .= filemtime($file);
                    if (is_dir($file)) {
                        $hash .= $this->hash("$path/*");
                    }
                }
                return hash('crc32', $hash);
            }
        );
    }

    public function listDirs(string $glob, bool $listed=false) : array
    {
        return array_filter($this->list($glob, $listed), '\\is_dir');
    }

    public function list(string $glob, bool $listed=false) : array
    {
        return $this->cache->get(
            'list.'.hash('crc32', serialize([$glob,$listed])),
            function () use ($glob,$listed) {
                if (preg_match('@^/~([a-zA-Z0-9]+)/@', $glob, $matches)) {
                    $prefix = $matches[1];
                    $glob = substr($glob, strlen($prefix)+2);
                    $result = $this->leafcutter->hooks()->dispatchFirst(
                        'onPrefixedContentList_'.$prefix,
                        $this->pathToGlobs($glob)
                    );
                    return $result ?? [];
                }
                $list = $this->listFiles($this->pathToGlobs($glob));
                if ($listed) {
                    $list = array_filter(
                        $list,
                        [$this,'getListedFromFilename']
                    );
                }
                return $list;
            }
        );
    }

    public function getOrderFromFilename(string $file) : int
    {
        $filename = basename($file);
        if (preg_match('/^index\..+$/', $filename)) {
            $checkOn = basename(dirname($file));
        } else {
            $checkOn = $filename;
        }
        if (preg_match('/^([0-9]{1,3})\. /', $checkOn, $matches)) {
            return intval($matches[1]);
        } else {
            //because orders in filenames can only be 3 digits, 1000 is effectively infinity
            return 1000;
        }
    }

    public function getListedFromFilename(string $file) : bool
    {
        $filename = basename($file);
        if (preg_match('/^index\..+$/', $filename)) {
            $checkOn = basename(dirname($file));
        } else {
            $checkOn = $filename;
        }
        if (substr($checkOn, 0, 1) == '_') {
            return false;
        } else {
            return true;
        }
    }

    protected function listFiles(array $globs) : array
    {
        return $this->cache->get(
            'listFiles.'.hash('crc32', serialize($globs)),
            function () use ($globs) {
                $files = [];
                foreach ($this->sourceDirectories() as $dir) {
                    foreach ($globs as $glob) {
                        $glob = "$dir$glob";
                        foreach (glob($glob, GLOB_BRACE) as $match) {
                            $path = $this->normalizePath($match);
                            $files[$path] = @$files[$path] ?? $match;
                        }
                    }
                }
                return $files;
            }
        );
    }

    protected function normalizePath(string $path) : string
    {
        foreach ($this->sourceDirectories() as $dir) {
            if (strpos($path, $dir) === 0) {
                $path = substr($path, strlen($dir));
                break;
            }
        }
        if (substr($path, 0, 1) != '/') {
            $path = "/$path";
        }
        $path = preg_replace('@/(_|[0-9]{1,3}\. )@', '/', $path);
        return $path;
    }

    /**
     * Convert a path into an array of potential globs that are
     * allowed to match it.
     *
     * @param string $path
     * @return array[string]
     */
    protected function pathToGlobs(string $path) : array
    {
        $path = $this->normalizePath($path);
        $chunks = array_filter(explode('/', $path));
        $chunks = array_map(function ($chunk) {
            return $this->possibleGlobs($chunk);
        }, $chunks);
        $globs = array_map(
            function ($e) {
                return "/$e";
            },
            $this->stringPermutations($chunks)
        );
        return $globs;
    }

    /**
     * Convert an array of potential glob chunks into an array of all possible
     * string permutations of those chunks concatenated together.
     *
     * [[1,2],[3,4]] would return [13,14,23,24]
     *
     * @param array $chunks
     * @param array $current
     * @return array
     */
    protected function stringPermutations(array $chunks, $current=[]) : array
    {
        if (!count($current)) {
            $current = array_shift($chunks);
        }
        if (!count($chunks)) {
            return $current ?? [];
        }
        $out = [];
        foreach (array_shift($chunks)??[] as $a) {
            foreach ($current as $b) {
                $out[] = $b.'/'.$a;
            }
        }
        return $this->stringPermutations($chunks, $out);
    }

    /**
     * Convert a single chunk of a path into an array of potential globs.
     * This is where the non-exact-matches of a given chunk are set.
     *
     * @param string $chunk
     * @return array[string]
     */
    protected function possibleGlobs(string $chunk) : array
    {
        // strip parts of chunk that might bork things
        $chunk = preg_replace('/^(_|[0-9]{1,3}\. )/', '', $chunk);
        // * glob already matches anything, no need to make it more complex
        // index.* filenames don't get prefixes, just for simplicity
        if ($chunk == '*' || preg_match('/^index\..+$/', $chunk)) {
            return [$chunk];
        }
        // also match chunk prefixed by _ or ###. strings
        return [
            $chunk,
            '_'.$chunk,
            '{[0-9],[0-9][0-9],[0-9][0-9][0-9]}. '.$chunk,
        ];
    }
}
