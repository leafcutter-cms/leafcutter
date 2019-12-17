<?php
namespace Leafcutter\Common;

use Symfony\Component\Finder\Finder;

/**
 * Trait to manage a list of source directories as well as
 * "primary" directories that are returned at the top of the list
 * of directories.
 *
 * Also maintains a hash of the current directory list configuration.
 */
trait SourceDirectoriesTrait
{
    private $sourceDirectories = [];
    private $primarySourceDirectories = [];
    private $sourceDirectoryHash;
    private $compiledSourceDirectories;

    protected function sourceDirectoriesChanged()
    {
        //implement this method if a class using this trait must do something when directories change
    }

    // public function locateSourceFile(string $glob) : ?string
    // {
    //     list($path, $filename) = $this->prepareGlob($glob);
    //     foreach ($this->sourceDirectories() as $sourceDir) {
    //         $thisDir = $sourceDir.$path;
    //         if (is_dir($thisDir)) {
    //             foreach (glob($thisDir.$filename) as $fullFileName) {
    //                 if (is_file($fullFileName)) {
    //                     return $fullFileName;
    //                 }
    //             }
    //         }
    //     }
    //     return null;
    // }

    // public function locateFilePath(string $glob) : ?string
    // {
    //     list($path, $filename) = $this->prepareGlob($glob);
    //     foreach ($this->sourceDirectories() as $sourceDir) {
    //         $thisDir = $sourceDir.$path;
    //         if (is_dir($thisDir)) {
    //             foreach (glob($thisDir.$filename) as $fullFileName) {
    //                 if (is_file($fullFileName)) {
    //                     return substr($fullFileName, strlen($sourceDir));
    //                 }
    //             }
    //         }
    //     }
    //     return null;
    // }

    // public function listFilePaths(string $glob) : array
    // {
    //     list($path, $filename) = $this->prepareGlob($glob);
    //     $found = [];
    //     foreach ($this->sourceDirectories() as $sourceDir) {
    //         $thisDir = $sourceDir.$path;
    //         if (is_dir($thisDir)) {
    //             foreach (glob($thisDir.$filename) as $fullFileName) {
    //                 $filePath = substr($fullFileName, strlen($sourceDir));
    //                 if (!in_array($filePath, $found)) {
    //                     $found[] = $filePath;
    //                 }
    //             }
    //         }
    //     }
    //     return $found;
    // }

    // protected function prepareGlob(string $glob) : array
    // {
    //     if (strpos($glob, '/') === null) {
    //         return ['/', $glob];
    //     } else {
    //         return [
    //             $this->normalizedPath(dirname($glob)),
    //             basename($glob)
    //         ];
    //     }
    // }

    /**
     * Get the current list of source directories, with primary directories
     * first, and more recently added entries first.
     *
     * @return array
     */
    public function sourceDirectories() : array
    {
        if ($this->compiledSourceDirectories === null) {
            $this->compiledSourceDirectories = array_reverse(array_merge(
                $this->sourceDirectories,
                $this->primarySourceDirectories
            ));
            $this->compiledSourceDirectories = array_map(
                function ($e) {
                    return str_replace('\\', '/', $e);
                },
                $this->compiledSourceDirectories
            );
        }
        return $this->compiledSourceDirectories;
    }

    /**
     * Get a hash of the current output of sourceDirectories()
     *
     * @return string
     */
    public function sourceDirectoryHash() : string
    {
        if ($this->sourceDirectoryHash === null) {
            $this->sourceDirectoryHash = hash('crc32', serialize($this->sourceDirectories()));
        }
        return $this->sourceDirectoryHash;
    }

    /**
     * Adds a directory that will always be highest priority for
     * scanning. If it's already added, make it highest priority.
     *
     * @param string $dir
     * @return void
     */
    public function addPrimaryDirectory(string $dir)
    {
        $dir = str_replace('\\', '/', $dir);
        if ($dir = realpath($dir)) {
            //remove added dir
            array_diff($this->primarySourceDirectories, [$dir]);
            //add new dir to end
            $this->primarySourceDirectories[] = realpath($dir);
            //filter/unique primarySourceDirectories
            $this->primarySourceDirectories = array_filter(array_unique($this->primarySourceDirectories));
            //clear cached things
            $this->sourceDirectoryHash = null;
            $this->compiledSourceDirectories = null;
            $this->sourceDirectoriesChanged();
        }
    }

    /**
     * Adds a directory to be searched. If directory is already
     * added, move it to the top priority.
     *
     * @param string $dir
     * @return void
     */
    public function addDirectory(string $dir)
    {
        if ($dir = realpath($dir)) {
            //remove added dir
            array_diff($this->sourceDirectories, [$dir]);
            //add new dir to end
            $this->sourceDirectories[] = realpath($dir);
            //filter/unique sourceDirectories
            $this->sourceDirectories = array_filter(array_unique($this->sourceDirectories));
            //clear cached things
            $this->sourceDirectoryHash = null;
            $this->compiledSourceDirectories = null;
        }
    }

    /**
     * Remove a directory to be searched.
     *
     * @param string $dir
     * @return void
     */
    public function removeDirectory(string $dir)
    {
        if ($dir = realpath($dir)) {
            //remove directory
            array_diff($this->sourceDirectories, [$dir]);
            //clear cached things
            $this->sourceDirectoryHash = null;
            $this->compiledSourceDirectories = null;
            $this->sourceDirectoriesChanged();
        }
    }
}
