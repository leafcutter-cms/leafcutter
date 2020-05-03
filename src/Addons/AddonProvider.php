<?php
namespace Leafcutter\Addons;

use Leafcutter\Leafcutter;

class AddonProvider
{
    private $leafcutter;
    private $addons = [];
    private $provides = [];
    private $interfaces = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        // register any addons from Composer
        foreach (Composer\Addons::addons() as $class) {
            $this->leafcutter->logger()->debug("AddonProvider: Addon registered from Composer: $class");
            $this->register($class);
        }
        // register any addons from config
        foreach ($this->leafcutter->config('addons.register') ?? [] as $class) {
            $this->leafcutter->logger()->debug("AddonProvider: Addon registered from config: $class");
            $this->register($class);
        }
        // load addons from config
        foreach ($this->leafcutter->config('addons.activate') ?? [] as $name) {
            $this->leafcutter->logger()->debug("AddonProvider: Addon loaded from config: $name");
            $this->activate($name);
        }
    }

    public function requireInterface(string $name, string $interface)
    {
        $this->interfaces[$name][] = $interface;
        $this->interfaces[$name] = array_unique($this->interfaces[$name]);
    }

    public function register(string $class): string
    {
        $this->leafcutter->logger()->debug('AddonProvider: register: ' . $class);
        // throw exception for invalid classes
        if (!in_array(AddonInterface::class, class_implements($class))) {
            $this->leafcutter->logger()->error('AddonProvider: tried to register invalid class: ' . $class);
            throw new \Exception("Can't register $class because it isn't a valid Leafcutter Addon");
        }
        // return name without doing anything if Addon with this name is already loaded
        $name = $class::name();
        if (isset($this->addons[$name])) {
            return $name;
        }
        // register class and provides list
        $this->provides[$name] = $class;
        foreach ($class::provides() as $n) {
            $this->provides[$n] = $class;
        }
        return $name;
    }

    public function get(string $name): ?AddonInterface
    {
        return @$this->addons[$name];
    }

    public function activate(string $name)
    {
        $this->leafcutter->logger()->debug('AddonProvider: activate: ' . $name);
        // try to locate class from provides list
        $class = @$this->provides[$name] ?? $name;
        // get name
        $name = $class::name();
        $names = $class::provides();
        $names[] = $name;
        // register class
        $this->register($class);
        // verify mandatory interfaces
        foreach ($names as $n) {
            foreach ($this->interfaces[$n] ?? [] as $interface) {
                if (!in_array($interface, class_implements($class))) {
                    throw new \Exception("Addons named or providing \"$n\" must implement $interface");
                }
            }
        }
        // try to activate requirements
        foreach ($class::requires() as $req) {
            $found = null;
            foreach (array_reverse($this->provides) as $depName => $provides) {
                if (in_array($req, $provides)) {
                    $found = $depName;
                    break;
                }
            }
            if ($found) {
                $this->activate($found);
            } else {
                throw new \Exception("Couldn't activate addon requirement. $class requires \"$req\"");
            }
        }
        // add Addon to internal list by its own name and all names it provides
        foreach ($names as $n) {
            $this->addons[$n] = new $class($this->leafcutter);
        }
        // merge in default config
        $this->leafcutter->config()->merge($this->addons[$name]->getDefaultConfig(), "addons.config.$name");
        // call Addon activate method
        $this->addons[$name]->activate();
        // set up event subscribers
        foreach ($this->addons[$name]->getEventSubscribers() as $subscriber) {
            $this->leafcutter->events()->addSubscriber($subscriber);
        }
    }
}
