<?php
namespace Leafcutter\Config;

/**
 * Config is responsible for loading config from
 */
class Config extends \Flatrr\Config\Config
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->readDir(__DIR__ . '/../../config/');
    }

    public function hash(): string
    {
        return hash('md5', serialize($this->get()));
    }
}
