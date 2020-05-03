<?php
namespace Leafcutter\Content;

class ContentDirectoryProvider implements ContentProviderInterface
{
    protected $directory;

    public function hash(string $path): string
    {
        $hash = [];
        foreach ($this->files($path) as $content) {
            $hash[$content->path()] = filemtime($content->path());
        }
        foreach ($this->directories($path) as $content) {
            $hash[$content->path()] = filemtime($content->path());
        }
        return hash('md5', serialize($hash));
    }

    public function files(string $path): array
    {
        $glob = $this->directory . '/' . $path;
        $files = [];
        foreach ($this->glob($glob, GLOB_BRACE) as $file) {
            $file = realpath($file);
            if (is_file($file)) {
                $files[] = new ContentFile($file, $this->pathToUrl($file));
            }
        }
        return $files;
    }

    public function directories(string $path): array
    {
        $glob = $this->directory . '/' . $path;
        $files = [];
        foreach ($this->glob($glob, \GLOB_BRACE & \GLOB_ONLYDIR) as $file) {
            $file = realpath($file);
            if (is_dir($file)) {
                $files[] = new ContentDirectory($file, $this->pathToUrl($file . '/'));
            }
        }
        return $files;
    }

    protected function glob($pattern, $flags = 0, $traversePostOrder = false)
    {
        // Keep away the hassles of the rest if we don't use the wildcard anyway
        if (strpos($pattern, '/**/') === false) {
            return \glob($pattern, $flags);
        }

        $patternParts = explode('/**/', $pattern);

        // Get sub dirs
        $dirs = \glob(array_shift($patternParts) . '/*', GLOB_ONLYDIR | GLOB_NOSORT);

        // Get files for current dir
        $files = \glob($pattern, $flags);

        foreach ($dirs as $dir) {
            $subDirContent = $this->glob($dir . '/**/' . implode('/**/', $patternParts), $flags, $traversePostOrder);
            if (!$traversePostOrder) {
                $files = array_merge($files, $subDirContent);
            } else {
                $files = array_merge($subDirContent, $files);
            }
        }

        return $files;
    }

    protected function pathToUrl(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $file = substr($file, strlen($this->directory));
        return "@/$file";
    }

    public function __construct(string $directory)
    {
        if (!is_dir($directory)) {
            \throw new Exception("Can't use content directory $directory because it isn't a directory.");
        }
        if (!is_readable($directory)) {
            \throw new Exception("Can't use content directory $directory because it isn't readable.");
        }
        $this->directory = realpath($directory);
    }
}
