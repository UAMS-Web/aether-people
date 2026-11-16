<?php

declare(strict_types=1);

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
