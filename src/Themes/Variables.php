<?php
namespace Leafcutter\Themes;

use Leafcutter\Leafcutter;

/**
 * Class to handle global site-wide variables that can be used in
 * CSS or HTML. Mostly used for things like colors.
 */
class Variables
{
    protected $leafcutter;
    protected $variables = [];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
    }

    public function list() : array
    {
        $variables = [];
        foreach ($this->leafcutter->config('theme_variables') as $name => $value) {
            $variables[$name] = $value;
        }
        foreach ($this->variables as $name => $value) {
            $variables[$name] = $value;
        }
        return $variables;
    }
    
    public function set(string $name, ?string $value)
    {
        $this->variables[$name] = $value;
    }

    public function get(string $name) : ?string
    {
        return $this->leafcutter->config("theme_variables.$name") ?? @$this->variables[$name];
    }

    public function resolve(string $content, $start='{{', $end='}}') : string
    {
        return preg_replace_callback(
            "/".preg_quote($start)."(.+?)".preg_quote($end)."/",
            function ($match) {
                return $this->get($match[1]);
            },
            $content
        );
    }
}
