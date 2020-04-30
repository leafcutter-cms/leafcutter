<?php
namespace Leafcutter\Plugins;

use Leafcutter\Leafcutter;

class PluginProvider
{
    private $leafcutter;
    private $plugins = [];
    private $provides = [];
    private $classes = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
    }

    public function register(string $class): string
    {
        // throw exception for invalid classes
        if (!in_array(PluginInterface::class,class_implements($class))) {
            throw new \Exception("Can't register $class because it isn't a plugin");
        }
        // return name without doing anything if plugin with this name is already loaded
        $name = $class::name();
        if (isset($this->plugins[$name])) {
            return $name;
        }
        // register class and provides list
        $this->classes[$name] = $class;
        $this->provides[$name] = $class::provides();
        return $name;
    }

    public function get(string $name): ?PluginInterface
    {
        return @$this->plugins[$name];
    }

    public function load(string $class): string
    {
        // get name
        $name = $class::name();
        // see if plugin is already loaded
        if (isset($this->plugins[$name])) {
            return $name;
        }
        // get class from registered list if found
        $class = $this->classes[$class] ?? $class;
        // register class
        $this->register($class);
        // try to load requirements
        foreach ($class::requires() as $req) {
            $found = null;
            foreach (array_reverse($this->provides) as $depName => $provides) {
                if (in_array($req, $provides)) {
                    $found = $depName;
                    break;
                }
            }
            $found = $found?? @$this->classes[$req];
            if ($found) {
                $this->load($found);
            } else {
                throw new \Exception("Couldn't load plugin requirement. $class requires \"$req\"");
            }
        }
        // add plugin to internal list
        $this->plugins[$name] = new $class($this->leafcutter);
        // merge in default config
        $this->leafcutter->config()->merge($this->plugins[$name]->getDefaultConfig(), "plugins.$name");
        // call plugin load method
        $this->plugins[$name]->load();
        // set up event subscribers
        foreach ($this->plugins[$name]->getEventSubscribers() as $subscriber) {
            $this->leafcutter->events()->addSubscriber($subscriber);
        }
        // return name
        return $name;
    }
}
