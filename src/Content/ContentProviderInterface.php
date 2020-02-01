<?php
namespace Leafcutter\Content;

interface ContentProviderInterface
{
    public function files(string $path): array;
    public function directories(string $path): array;
    public function hash(string $path): string;
}
