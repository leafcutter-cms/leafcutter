<?php
namespace Leafcutter\Events;

use Leafcutter\Leafcutter;

class EventProvider
{
    private $leafcutter;
    private $subscribers = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
    }

    public function addSubscriber($subscriber)
    {
        $methods = array_filter(
            get_class_methods($subscriber),
            function ($e) {
                return preg_match('/^on([A-Z]+[a-z_]*[a-zA-Z])+$/', $e);
            }
        );
        foreach ($methods as $method) {
            $this->subscribers[$method][] = [$subscriber, $method];
        }
    }

    public function dispatchFirst(string $method, $subject)
    {
        foreach (array_reverse($this->subscribers[$method] ?? []) as $fn) {
            if ($result = call_user_func($fn, $subject, $this->leafcutter)) {
                return $result;
            }
        }
        return null;
    }

    public function dispatchAll(string $method, $subject)
    {
        foreach ($this->subscribers[$method] ?? [] as $fn) {
            $subject = call_user_func($fn, $subject, $this->leafcutter);
        }
        return $subject;
    }

    public function dispatchEvent(string $method, $event)
    {
        foreach ($this->subscribers[$method] ?? [] as $fn) {
            call_user_func($fn, $event, $this->leafcutter);
        }
        return $event;
    }
}
