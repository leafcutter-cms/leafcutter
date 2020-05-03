<?php
namespace Leafcutter\Addons;

use Leafcutter\Leafcutter;

interface AddonInterface
{
    public static function name(): string;
    public static function provides(): array;
    public static function requires(): array;

    public function __construct(Leafcutter $leafcutter);
    public function getEventSubscribers(): array;
    public function getDefaultConfig(): array;
    public function config(string $key);
    public function activate(): void;
}
