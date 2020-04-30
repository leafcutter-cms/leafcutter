<?php
namespace Leafcutter\Plugins;

use Leafcutter\Leafcutter;

abstract class AbstractPlugin implements PluginInterface
{
    protected $leafcutter;
    const DEFAULT_CONFIG = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
    }

    public function load(): void
    {
        //does nothing;
    }

    public function getEventSubscribers(): array
    {
        return [$this];
    }

    public function getDefaultConfig(): array
    {
        return static::DEFAULT_CONFIG;
    }

    public function config(string $key)
    {
        return $this->leafcutter->config("plugins." . $this->name() . ".$key");
    }

    public static function name(): string
    {
        $class = get_called_class();
        $name = strtolower(implode('_', array_slice(explode('\\', $class), -2)));
        return $name;
    }

    public static function provides(): array
    {
        return [static::name()];
    }

    public static function requires(): array
    {
        return [];
    }
}
