<?php
namespace Leafcutter\Content\Images;

use Imagine;
use Leafcutter\Common\UrlInterface;
use Leafcutter\Leafcutter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This class holds the event hooks for building image assets
 */
class ImageAssetBuilder
{
    protected $leafcutter;
    protected $imagine;

    const OUTPUT_FORMATS = [
        'jpg','gif','png','wbmp','xbm','webp','bmp','ico'
    ];
    protected $saveOptions = [
        'jpeg_quality' => 80,
        'png_compression_level' => 9,
        'webp_quality' => 80
    ];

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
    }

    protected function buildImage($candidate, $url)
    {
        $this->leafcutter->logger()->debug("ImageAssetBuilder: buildImage: $url: $candidate");
        $url = $this->normalizeImageUrl($url);
        $asset = new ImageAsset(
            $url,
            hash('crc32', serialize([
                $url, hash_file('crc32', $candidate)
            ])),
            function ($asset) use ($candidate) {
                return $this->buildImageFile($asset, $candidate);
            }
        );
        $asset->setExtension($url->getArgs()['format']??$asset->getExtension());
        return $asset;
    }

    /**
     * Transform the args in a URL to only include the ones that will be used to
     * build the output image.
     *
     * @param UrlInterface $args
     * @return UrlInterface
     */
    protected function normalizeImageUrl(UrlInterface $url) : UrlInterface
    {
        $args = $url->getArgs();
        $validArgs = [];

        // only one of fit/crop is allowed
        if (isset($args['crop']) && preg_match('/^([1-9][0-9]*)(x([1-9][0-9]*))?$/', $args['crop'], $matches)) {
            $validArgs['crop'] = implode('x', [
                intval($matches[1]),
                intval(@$matches[3]?$matches[3]:($matches[1]))
            ]);
            unset($validArgs['fit']);
        } elseif (isset($args['fit']) && preg_match('/^([1-9][0-9]*)(x([1-9][0-9]*))?$/', $args['fit'], $matches)) {
            $validArgs['fit'] = implode('x', [
                intval($matches[1]),
                intval(@$matches[3]?$matches[3]:($matches[1]))
            ]);
        }
        if (isset($validArgs['crop']) && isset($validArgs['fit'])) {
            unset($validArgs['fit']);
        }
        
        // option to set format
        if (in_array(@$args['format'], static::OUTPUT_FORMATS)) {
            $validArgs['format'] = $this->outputFormat = $args['format'];
        }

        // special icon rules
        if (@$validArgs['format'] == 'ico') {
            unset($validArgs['fit']);
            unset($validArgs['crop']);
            if (!@$args['ico_sizes']) {
                $validArgs['ico_sizes'] = [32];
            } else {
                $validArgs['ico_sizes'] = explode(',', @$args['ico_sizes']??'32');
                $validArgs['ico_sizes'] = array_map('\intval', $validArgs['ico_sizes']);
                asort($validArgs['ico_sizes']);
                $validArgs['ico_sizes'] = $validArgs['ico_sizes'] ?? [32];
            }
            $validArgs['crop'] = implode('x', [end($validArgs['ico_sizes']),end($validArgs['ico_sizes'])]);
        }

        // option to grayscale
        $validArgs['grayscale'] = (@$args['grayscale'] == 'true');

        // option to colorize
        if (isset($args['colorize']) && preg_match('/^#([0-9a-f]{3,3}){1,2}$/i', $args['colorize'])) {
            $validArgs['colorize'] = $args['colorize'];
        }

        // option to blur
        $validArgs['blur'] = intval(@$args['blur']);

        //set args and return
        $url->setArgs($validArgs);
        return $url;
    }

    public function buildImageFile($asset, $src)
    {
        //try to get an extra 10 seconds per image, and plenty of memory
        set_time_limit(ini_get('max_execution_time')+10);
        ini_set('memory_limit','1024M');

        $dest = $asset->getOutputFile();

        // get what we need from asset
        $args = $asset->getUrl()->getArgs();
    
        // first crop/fit, so that we're working on a smaller image
        // from here on out if possible
        if (@$args['crop']) {
            $args['crop'] = explode('x', $args['crop']);
            $image = $this->openCrop($src, $args['crop'][0], $args['crop'][1]);
        } elseif (@$args['fit']) {
            $args['fit'] = explode('x', $args['fit']);
            $image = $this->openFit($src, $args['fit'][0], $args['fit'][1]);
        } else {
            $image = $this->open($src);
        }

        // grayscale
        if (@$args['grayscale']) {
            $image->effects()->grayscale();
        }

        // colorize
        if (@$args['colorize']) {
            $image->effects()->colorize(
                $image->palette()->color($args['colorize'])
            );
        }

        // blur
        if (@$args['blur']) {
            $image->effects()->blur($args['blur']);
        }

        // special handling for ico files
        $icon = false;
        if ($asset->getExtension() == 'ico') {
            $icon = true;
            $dest .= '.png';
        }

        // do outputting of file
        $filesystem = new Filesystem();
        $filesystem->mkdir(dirname($dest));
        if ('\\' === \DIRECTORY_SEPARATOR) {
            // on windows, just save to output file
            $image->save($dest, $this->saveOptions);
        } else {
            // otherwise try to do fancy deduplicating symlinks
            $image_data = dirname($dest).'/image_data';
            $filesystem->remove($image_data);
            $image->save($image_data, $this->saveOptions);
            $filesystem->symlink($image_data, $dest);
        }

        // if an icon was requested, change dest back to .ico and save out
        if ($icon) {
            $sizes = array_map(function ($e) {
                return [$e,$e];
            }, $args['ico_sizes']);
            if (count($sizes) == 1) {
                $sizes = $sizes[0];
            }
            $ico_lib = new \PHP_ICO($dest, $sizes);
            $ico_lib->save_ico($asset->getOutputFile());
            unlink($dest);
        }
    }

    public function onAssetFile_jpg($input)
    {
        return $this->buildImage($input[1], $input[0]);
    }

    public function onAssetFile_jpeg($input)
    {
        return $this->buildImage($input[1], $input[0]);
    }

    public function onAssetFile_gif($input)
    {
        return $this->buildImage($input[1], $input[0]);
    }

    public function onAssetFile_png($input)
    {
        return $this->buildImage($input[1], $input[0]);
    }

    public function onAssetFile_wbmp($input)
    {
        return $this->buildImage($input[1], $input[0]);
    }

    public function onAssetFile_xbm($input)
    {
        return $this->buildImage($input[1], $input[0]);
    }

    public function onAssetFile_webp($input)
    {
        return $this->buildImage($input[1], $input[0]);
    }

    public function onAssetFile_bmp($input)
    {
        return $this->buildImage($input[1], $input[0]);
    }

    protected function open($src)
    {
        return $this->imagine()->open($src);
    }
    
    protected function openCrop($src, int $width, int $height)
    {
        $size = new Imagine\Image\Box($width, $height);
        $mode = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
        return $this->open($src)->thumbnail($size, $mode);
    }

    protected function openFit($src, int $width, int $height)
    {
        $size = new Imagine\Image\Box($width, $height);
        $mode = Imagine\Image\ImageInterface::THUMBNAIL_INSET;
        return $this->open($src)->thumbnail($size, $mode);
    }

    protected function imagine()
    {
        if (!$this->imagine) {
            switch ($this->leafcutter->config('images.driver')) {
                case 'imagick':
                    $this->imagine = new Imagine\Imagick\Imagine();
                    break;
                case 'gmagick':
                    $this->imagine = new Imagine\Gmagick\Imagine();
                    break;
                default:
                    $this->imagine = new Imagine\Gd\Imagine();
                    break;
            }
        }
        return $this->imagine;
    }
}
