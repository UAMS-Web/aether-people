<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Stache;

if (! function_exists('isolateStacheIn')) {
    /**
     * Point every Stache store at a directory of the caller's own, so nothing the test saves reaches the
     * fixtures directory and no parallel worker shares a directory with another.
     *
     * Statamic's `PreventsSavingStacheItemsToDisk` does the same with one fixed directory, which two
     * parallel workers then write to at once.
     *
     * @param  string  $directory  A directory unique to this test.
     */
    function isolateStacheIn(string $directory): void
    {
        Stache::stores()->each(function ($store) use ($directory): void {
            $store->directory($directory.'/'.str_replace('::', '/', (string) $store->key()));
        });
    }
}

if (! function_exists('isolateStacheAndBlueprintsIn')) {
    /**
     * Isolate the Stache and the blueprint directory in a directory of this test's own, and return it.
     *
     * A fixture blueprint saved under a real handle (`collections.people`) would otherwise be written over the
     * add-on's shipped one, so blueprints are pointed at the throwaway directory too, with the `people`
     * collection's namespace registered in it.
     *
     * @param  string  $name  A name for the test's directory under `storage/framework/testing`.
     * @return string The test's own directory.
     */
    function isolateStacheAndBlueprintsIn(string $name): string
    {
        $directory = storage_path('framework/testing/'.$name.'/'.uniqid('', true));
        File::ensureDirectoryExists($directory);

        // Keep every saved collection, entry and global out of the tracked fixtures directory
        isolateStacheIn($directory.'/stache');

        // Save fixture blueprints into the throwaway directory, never over the add-on's shipped ones
        File::ensureDirectoryExists($directory.'/blueprints');
        Blueprint::setDirectory($directory.'/blueprints');
        Blueprint::addNamespace('collections.people', $directory.'/blueprints/collections/people');

        return $directory;
    }
}
