<?php
/**
 * This class contains code taken from PackageVersions, which was useful for
 * loading up a list of installed packages and their configs.
 * https://github.com/Ocramius/PackageVersions
 *
 * Copyright (c) 2016 Marco Pivetta
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
namespace Leafcutter\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Symfony\Component\Filesystem\Filesystem;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    public const ROOT_PACKAGE_NAME = 'unknown/root-package@UNKNOWN';
    protected $composer;
    protected $io;
    protected $found = null;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'post-autoload-dump' => 'findThemesAndAddons'
        );
    }

    public function findThemesAndAddons()
    {
        $fs = new Filesystem();
        // empty out adjacent themes directory
        $fs->remove(glob(__DIR__ . '/themes/*', GLOB_ONLYDIR));
        // check for things that need updating
        echo "Checking for Leafcutter addons/themes" . PHP_EOL;
        $packageData = self::getPackageData();
        $packageData[] = self::getThisPackageData();
        $addons = [];
        foreach ($packageData as $p) {
            if (@$this->found[$p['name']]) {
                continue;
            }
            $this->found[$p['name']] = true;
            // list addons in text file for later registration
            if ($p['type'] == 'leafcutter-addon') {
                echo "registering addon: {$p['name']}" . PHP_EOL;
                $addons[] = $p['extra']['leafcutter-addon'];
            }
            // copy themes into the adjacent themes directory
            if ($p['type'] == 'leafcutter-theme') {
                echo "installing theme: {$p['name']}" . PHP_EOL;
                $src = self::getPackageDirectory($p['name']);
                $dest = __DIR__ . '/themes/' . basename($p['name']);
                $fs->mirror($src, $dest);
            }
        }
        file_put_contents(__DIR__ . '/addons.txt', implode(PHP_EOL, $addons));
    }

    private static function getThisPackageData(): array
    {
        $checkedPaths = [
            // The top-level project's ./composer.json
            getcwd() . '/composer.json',
            __DIR__ . '/../../../../composer.json',
        ];

        $packageData = [];
        foreach ($checkedPaths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            $packageData = json_decode(file_get_contents($path), true);
        }

        if ($packageData !== []) {
            return $packageData;
        }

        throw new \Exception(sprintf(
            'Leafcutter could not locate your `composer.json` file '
            . 'location. This is assumed to be in %s. If you customized your composer vendor directory and ran composer '
            . 'installation with --no-scripts or if you deployed without the required composer files, then you are on '
            . 'your own, and we can\'t really help you.',
            json_encode($checkedPaths)
        ));
    }

    private static function getPackageDirectory(string $name): ?string
    {
        $checkedPaths = [
            // The top-level project's vendor dir
            getcwd() . '/vendor/',
            __DIR__ . '/../../../../../vendor/',
            // This package's vendor dir
            __DIR__ . '/../../vendor/',
        ];
        foreach ($checkedPaths as $dir) {
            if (is_dir($dir . $name)) {
                return $dir . $name;
            }
        }
        return null;
    }

    private static function getPackageData(): array
    {
        $checkedPaths = [
            // The top-level project's ./vendor/composer/installed.json
            getcwd() . '/vendor/composer/installed.json',
            __DIR__ . '/../../../../composer/installed.json',
            // The top-level project's ./composer.lock
            getcwd() . '/composer.lock',
            __DIR__ . '/../../../../../composer.lock',
            // This package's composer.lock
            __DIR__ . '/../../composer.lock',
        ];

        $packageData = [];
        foreach ($checkedPaths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            $data = json_decode(file_get_contents($path), true);
            switch (basename($path)) {
                case 'installed.json':
                    $packageData[] = $data;
                    break;
                case 'composer.lock':
                    $packageData[] = $data['packages'] + ($data['packages-dev'] ?? []);
                    break;
                default:
                    // intentionally left blank
            }
        }

        if ($packageData !== []) {
            return array_merge(...$packageData);
        }

        throw new \Exception(sprintf(
            'Leafcutter could not locate the `vendor/composer/installed.json` or your `composer.lock` '
            . 'location. This is assumed to be in %s. If you customized your composer vendor directory and ran composer '
            . 'installation with --no-scripts or if you deployed without the required composer files, then you are on '
            . 'your own, and we can\'t really help you.',
            json_encode($checkedPaths)
        ));
    }
}
