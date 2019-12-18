<?php
namespace Leafcutter\Content\Assets;

use Leafcutter\Leafcutter;

/**
 * This class holds the event hooks for building basic built-in assets
 */
class SystemAssetBuilder
{
    protected $leafcutter;

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
    }

    /**
     * Build a callback asset to compile SCSS code
     *
     * @param array $input
     * @return StringAsset
     */
    public function onAssetFile_scss($input) : StringAsset
    {
        list($url, $candidate) = $input;
        $content = file_get_contents($candidate);
        
        $compiler = new \ScssPhp\ScssPhp\Compiler([]);

        $dirs = $this->leafcutter->content()->listDirs($url->getContext());

        //set up variables
        $compiler->setVariables(
            $this->leafcutter->themes()->variables()->list()
        );
        //set up import dirs and function
        $thisDir = dirname($candidate);
        if (!in_array($thisDir, $dirs)) {
            $dirs[] = $thisDir;
        }
        $compiler->setImportPaths($dirs);
        $compiler->addImportPath(function ($path) use ($url) {
            // try to include a raw scss file if possible
            if (strtolower(substr($path, -5)) == '.scss') {
                if ($files = $this->leafcutter->content()->list($path)) {
                    return reset($files);
                }
            }
            // try to include an asset
            $asset = $this->leafcutter->assets()->get($path, $url);
            if ($asset) {
                return $asset->getOutputFile();
            }
            return null;
        });

        $content = $compiler->compile($content);
        $content = $this->leafcutter->prepareCSS($content, $url->getFullContext());
        $asset = new StringAsset($url, $content);
        $asset->setExtension('css');
        return $asset;
    }

    /**
     * Build a callback asset to compile Less code
     *
     * @param array $input
     * @return StringAsset
     */
    public function onAssetFile_less($input) : StringAsset
    {
        list($url, $candidate) = $input;
        $content = file_get_contents($candidate);
        $content = $this->less()->parse($content)->getCss();
        $content = $this->leafcutter->prepareCSS($content, $url->getFullContext());
        $asset = new StringAsset($url, $content);
        $asset->setExtension('css');
        return $asset;
    }

    /**
     * Build a copier asset
     *
     * @param array $input
     * @return CopierAsset
     */
    public function onAssetFile_unmatched($input) : CopierAsset
    {
        list($url, $candidate) = $input;
        $asset = new CopierAsset($url, $candidate);
        return $asset;
    }

    protected static function less() : \Less_Parser
    {
        static $less;
        $less = $less ?? new \Less_Parser();
        return $less;
    }
}
