<?php
namespace Leafcutter\Addons;

use Leafcutter\Leafcutter;

class AddonProvider
{
    private $leafcutter;
    private $addons = [];
    private $provides = [];
    private $classes = [];
    private $interfaces = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
    }

    public function requireInterface(string $name, string $interface)
    {
        $this->interfaces[$name][] = $interface;
        $this->interfaces[$name] = array_unique($this->interfaces[$name]);
    }

    public function register(string $class): string
    {
        // throw exception for invalid classes
        if (!in_array(AddonInterface::class, class_implements($class))) {
            throw new \Exception("Can't register $class because it isn't a valid Addon");
        }
        // return name without doing anything if Addon with this name is already loaded
        $name = $class::name();
        if (isset($this->addons[$name])) {
            return $name;
        }
        // register class and provides list
        $this->classes[$name] = $class;
        $this->provides[$name] = $class::provides();
        return $name;
    }

    public function get(string $name): ?AddonInterface
    {
        return @$this->addons[$name];
    }

    public function load(string $class): string
    {
        // get name
        $name = $class::name();
        // see if Addon is already loaded
        if (isset($this->addons[$name])) {
            return $name;
        }
        // get class from registered list if found
        $class = $this->classes[$class] ?? $class;
        // register class
        $this->register($class);
        // verify mandatory interfaces
        foreach ($this->interfaces[$name]??[] as $interface) {
            if (!in_array($interface,class_implements($interface))) {
                throw new \Exception("Addons named \"$name\" must implement $interface");
            }
        }
        // try to load requirements
        foreach ($class::requires() as $req) {
            $found = null;
            foreach (array_reverse($this->provides) as $depName => $provides) {
                if (in_array($req, $provides)) {
                    $found = $depName;
                    break;
                }
            }
            $found = $found ?? @$this->classes[$req];
            if ($found) {
                $this->load($found);
            } else {
                throw new \Exception("Couldn't load addon requirement. $class requires \"$req\"");
            }
        }
        // add Addon to internal list
        $this->addons[$name] = new $class($this->leafcutter);
        // merge in default config
        $this->leafcutter->config()->merge($this->addons[$name]->getDefaultConfig(), "addons.config.$name");
        // call Addon load method
        $this->addons[$name]->load();
        // set up event subscribers
        foreach ($this->addons[$name]->getEventSubscribers() as $subscriber) {
            $this->leafcutter->events()->addSubscriber($subscriber);
        }
        // return name
        return $name;
    }
}
