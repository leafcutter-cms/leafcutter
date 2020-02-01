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
        return hash('crc32', serialize($hash));
    }

    public function files(string $path): array
    {
        $glob = $this->directory . '/' . $path;
        $files = [];
        foreach (glob($glob, GLOB_BRACE) as $file) {
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
        foreach (glob($glob, \GLOB_BRACE & \GLOB_ONLYDIR) as $file) {
            $file = realpath($file);
            if (is_dir($file)) {
                $files[] = new ContentDirectory($file, $this->pathToUrl($file . '/'));
            }
        }
        return $files;
    }

    protected function pathToUrl(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $file = substr($file, strlen($this->directory));
        return $file;
    }

    public function __construct(string $directory)
    {
        if (!is_dir($directory)) {
            throw new Exception("Can't use content directory $directory because it isn't a directory.");
        }
        if (!is_readable($directory)) {
            throw new Exception("Can't use content directory $directory because it isn't readable.");
        }
        $this->directory = realpath($directory);
    }
}
