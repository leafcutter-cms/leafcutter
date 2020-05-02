<?php
namespace Leafcutter\Config;

use Flatrr\Config\Config as BaseConfig;

/**
 * Config is responsible for loading config from
 */
class Config extends BaseConfig
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->readDir(__DIR__ . '/../../config');
    }

    public function hash(): string
    {
        return hash('md5', serialize($this->get()));
    }

    /**
     * Recursively read all yaml, yml, ini, and json files in a directory.
     *
     * @param string $dir directory to look in
     * @return void
     */
    public function readDir(string $dir)
    {
        $dir = realpath($dir);
        foreach (glob($dir . '/*.{yaml,yml,ini,json}', GLOB_BRACE) as $file) {
            $import = new BaseConfig();
            $import->readFile($file);
            $this->merge($import->get(null), null, true);
        }
    }
}
