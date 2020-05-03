<?php
namespace Leafcutter\Addons;

use Leafcutter\Leafcutter;

abstract class AbstractAddon implements AddonInterface
{
    protected $leafcutter;
    const DEFAULT_CONFIG = [];

    abstract public function activate(): void;

    abstract public function getEventSubscribers(): array;

    abstract public static function name(): string;

    abstract public static function provides(): array;

    abstract public static function requires(): array;

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
    }

    public function getDefaultConfig(): array
    {
        return static::DEFAULT_CONFIG;
    }

    public function config(string $key)
    {
        return $this->leafcutter->config("addons.config." . $this->name() . ".$key");
    }
}
