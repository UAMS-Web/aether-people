<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests\Support;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry;
use Statamic\Stache\Repositories\EntryRepository;

/**
 * An entry repository whose `find()` hands back a fresh copy of the stored entry, as the site's own
 * repository does by re-wrapping each row in the container-bound entry class.
 *
 * Statamic's repository returns the stored instance itself, so a Control Panel save that merges new
 * values into the entry changes what `find()` then returns. A listener comparing an entry with its
 * stored copy needs the copy to stay as stored.
 */
class FreshEntryRepository extends EntryRepository
{
    /**
     * Find an entry by id and return a copy that later changes to the stored entry do not reach.
     *
     * @param  mixed  $id  The entry id.
     */
    #[\Override]
    public function find($id): ?EntryContract
    {
        $stored = parent::find($id);

        if (! $stored instanceof Entry) {
            return null;
        }

        $fresh = mustBe(Entry::class, $this->make(), 'a new entry');
        $fresh->id($stored->id());
        $fresh->collection($stored->collectionHandle());
        $fresh->locale($stored->locale());
        $fresh->data($stored->data());
        $fresh->published($stored->published());
        $fresh->slug($stored->slug());

        return $fresh;
    }
}
