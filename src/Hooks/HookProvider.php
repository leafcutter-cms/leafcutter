<?php
namespace Leafcutter\Hooks;

use Leafcutter\Leafcutter;

class HookProvider
{
    protected $hooks = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
    }

    public function addSubscriber($subscriber)
    {
        $methods = array_filter(
            get_class_methods($subscriber),
            function ($e) {
                return preg_match('/^on([A-Z]+[a-z_]*[a-z])+$/', $e);
            }
        );
        foreach ($methods as $method) {
            $this->hooks[$method][] = [$subscriber,$method];
        }
    }

    public function dispatchFirst(string $method, $subject)
    {
        $this->leafcutter->logger()->debug('HookProvider: '.$method.' (dispatchFirst)');
        foreach (array_reverse($this->hooks[$method]??[]) as $fn) {
            if ($result = call_user_func($fn, $subject, $this->leafcutter)) {
                return $result;
            }
        }
        return null;
    }

    public function dispatchAll(string $method, $subject)
    {
        $this->leafcutter->logger()->debug('HookProvider: '.$method.' (dispatchAll)');
        foreach ($this->hooks[$method]??[] as $fn) {
            $subject = call_user_func($fn, $subject, $this->leafcutter);
        }
        return $subject;
    }

    public function dispatchEvent(string $method, $event)
    {
        $this->leafcutter->logger()->debug('HookProvider: '.$method.' (dispatchEvent)');
        foreach ($this->hooks[$method]??[] as $fn) {
            call_user_func($fn, $event, $this->leafcutter);
        }
        return $event;
    }
}