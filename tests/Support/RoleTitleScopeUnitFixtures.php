<?php

declare(strict_types=1);

use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Entry;

if (! function_exists('roleTitleScopeUnitEntry')) {
    /**
     * Save an entry for a role title scope unit test and return it.
     *
     * @param  string  $id  Entry id, also used as its slug
     * @param  string  $collection  Collection handle
     * @param  array<string, mixed>  $data  Entry data
     */
    function roleTitleScopeUnitEntry(
        string $id,
        string $collection,
        array $data = []
    ): EntryInstance {
        /** @var EntryInstance $entry */
        $entry = Entry::make();
        $entry->id($id);
        $entry->collection($collection);
        $entry->slug($id);
        $entry->data($data);
        $entry->save();

        return $entry;
    }
}
