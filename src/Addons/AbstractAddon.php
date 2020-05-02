<?php
namespace Leafcutter\Addons;

abstract class AbstractAddon implements AddonInterface
{
    protected $leafcutter;
    const DEFAULT_CONFIG = [];

    abstract public function load(): void;

    abstract public function getEventSubscribers(): array;

    public static function provides(): array
    {
        return [];
    }

    public static function requires(): array
    {
        return [];
    }

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

    public static function name(): string
    {
        $class = get_called_class();
        $name = strtolower(implode('_', array_slice(explode('\\', $class), -2)));
        return $name;
    }
}
