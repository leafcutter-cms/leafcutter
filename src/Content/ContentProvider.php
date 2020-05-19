<?php
namespace Leafcutter\Content;

use Leafcutter\Leafcutter;

class ContentProvider implements ContentProviderInterface
{
    private $leafcutter;
    private $providers = [];
    private $namespaces = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->addProvider(new ContentDirectoryProvider(__DIR__ . '/../../content/'));
    }

    /**
     * Produce a hash based on the files and modified dates (as a proxy for
     * content changes, because they're faster). This hash will be based on
     * the content of parents of the given path, as well as recursively
     * hashing all child paths.
     *
     * This main class handles all the recursion and tree traversal. Downstream
     * ContentProviderInterface implementations only need to hash the contents
     * of the path directly given (which means just hashing the date modified
     * for filesystem-based implementations).
     *
     * @param string $path
     * @param string $namespace
     * @return string
     */
    public function hash(string $path, string $namespace = null): string
    {
        $hash = [
            'namespace' => $namespace,
            'path' => $path,
            'leafcutter' => $this->leafcutter->hash(),
        ];
        // do basic hashing of this path and its parents
        $currentPath = explode('/', $path);
        do {
            foreach ($this->providers($namespace) as $provider) {
                $hash[] = $provider->hash(implode('/', $currentPath));
            }
        } while (array_pop($currentPath) !== null);
        // final hash and return
        return hash('md5', implode('', $hash));
    }

    public function addDirectory(string $directory, string $namespace = null)
    {
        $this->addProvider(new ContentDirectoryProvider($directory), $namespace);
    }

    protected function providers(string $namespace = null): array
    {
        if ($namespace) {
            return @$this->namespaces[$namespace] ?? [];
        } else {
            return $this->providers;
        }
    }

    public function files(string $path, string $namespace = null): array
    {
        $result = [];
        foreach ($this->providers($namespace) as $provider) {
            $result = array_merge(array_values($provider->files($path)), $result);
        }
        $result = $this->normalizeResultsArray($result, $namespace);
        if ($namespace) {
            $result = array_merge(array_values($this->files("~$namespace/$path")),$result);
        }
        return $result;
    }

    public function directories(string $path, string $namespace = null): array
    {
        $result = [];
        foreach ($this->providers($namespace) as $provider) {
            $result = array_merge(array_values($provider->directories($path)), $result);
        }
        $result = $this->normalizeResultsArray($result, $namespace);
        if ($namespace) {
            $result = array_merge(array_values($this->directories("~$namespace/$path")),$result);
        }
        return $result;
    }

    protected function normalizeResultsArray(array $array, ?string $namespace)
    {
        return array_map(
            function ($file) use ($namespace) {
                if ($namespace) {
                    $file->url()->setSiteNamespace($namespace);
                }
                return $file;
            },
            $array
        );
    }

    public function addProvider(ContentProviderInterface $provider, string $namespace = null)
    {
        if ($namespace) {
            if (!isset($this->namespaces[$namespace])) {
                $this->namespaces[$namespace] = [];
            }
            array_push($this->namespaces[$namespace], $provider);
        } else {
            array_push($this->providers, $provider);
        }
    }
}
