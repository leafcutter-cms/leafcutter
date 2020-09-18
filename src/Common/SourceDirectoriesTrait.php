<?php
namespace Leafcutter\Common;

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

    /**
     * Get the current list of source directories, with primary directories
     * first, and more recently added entries first.
     *
     * @return array
     */
    public function sourceDirectories(): array
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
    public function sourceDirectoryHash(): string
    {
        if ($this->sourceDirectoryHash === null) {
            $this->sourceDirectoryHash = hash('md5', serialize($this->sourceDirectories()));
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
        } else {
            user_error('addPrimaryDirectory: ' . $dir . ' is not a directory', E_USER_WARNING);
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
        } else {
            user_error('addDirectory: ' . $dir . ' is not a directory', E_USER_WARNING);
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
