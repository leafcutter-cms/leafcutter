<?php
namespace Leafcutter\Assets;

use Leafcutter\URL;

interface AssetInterface
{
    public function content(): string;
    public function extension(): ?string;
    public function filename(): string;
    public function hash(): string;
    public function mime(): ?string;
    public function publicUrl(): URL;
    public function setFilename(string $filename);
    public function outputFile(): ?string;
    public function setOutputFile(string $path);
    public function setPublicUrl(URL $url);
    public function setUrl(URL $url);
    public function size(): int;
    public function url(): URL;
}
