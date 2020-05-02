<?php
namespace Leafcutter\Addons;

interface AddonInterface
{
    public static function name(): string;
    public static function provides(): array;
    public static function requires(): array;

    public function __construct(Leafcutter $leafcutter);
    public function getEventSubscribers(): array;
    public function getDefaultConfig(): array;
    public function config(string $key);
    public function load(): void;
}
