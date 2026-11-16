<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Workday;

use Illuminate\Support\Collection;
use Statamic\Contracts\Revisions\Revision;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Events\RevisionSaving;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;
use UAMSWeb\Aether\People\Support\WorkdayManagedFields;

/**
 * Keeps the Workday-managed `people` fields at their stored values when a user
 * held to them saves (UAMS-Web/uams-statamic-archive#3186, decided on UAMS-Web/uams-statamic-archive#3130).
 *
 * Statamic shows a read-only field greyed out but still writes whatever value a
 * request carries, so the lock is enforced here, on the data about to be
 * written. A change to a published entry is written as a working copy (UAMS-Web/uams-statamic-archive#3127)
 * rather than saved, so the working copy is held to the same values when it is
 * saved, and so is a working copy made by restoring an older revision.
 *
 * The stored values are put back rather than the save refused: a locked field
 * comes back from the publish form in whatever shape its fieldtype submits,
 * which need not match how it is stored, and refusing on that difference would
 * stop every save of an untouched entry.
 */
class RestoreWorkdayManagedFields
{
    public function __construct(
        protected WorkdayManagedFields $fields
    ) {}

    /**
     * Restore the locked fields of a `people` entry about to be saved.
     *
     * @param  EntrySaving  $event  The entry about to be saved
     */
    public function handleEntrySaving(EntrySaving $event): void
    {
        $entry = $event->entry;

        // governs() refuses anything that is not an entry itself, so this guard is for phpstan.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $entry instanceof Entry || ! $this->fields->governs($entry)) {
            return;
        }

        if (! $this->fields->appliesTo(User::current())) {
            return;
        }

        $data = $entry->data();

        // phpstan requires this type guard; an entry's data is always a collection.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $data instanceof Collection) {
            return;
        }

        $entry->data($this->fields->restore($data->all(), $this->fields->storedData($entry)));
    }

    /**
     * Restore the locked fields of a `people` working copy or revision about to
     * be saved, from the published entry it belongs to.
     *
     * @param  RevisionSaving  $event  The revision about to be saved
     */
    public function handleRevisionSaving(RevisionSaving $event): void
    {
        $revision = $event->revision;

        if (! $revision instanceof Revision) {
            return;
        }

        $key = $revision->key();

        if (! \is_string($key) || ! str_starts_with($key, self::revisionKeyPrefix())) {
            return;
        }

        if (! $this->fields->appliesTo(User::current())) {
            return;
        }

        $attributes = $revision->attributes();

        // phpstan requires these shape guards; Statamic always writes both keys.
        if (! \is_array($attributes) || ! \is_array($attributes['data'] ?? null)) {
            return;
        }

        // Read the stored values from the published entry the revision belongs to
        $entry = isset($attributes['id']) ? EntryFacade::find($attributes['id']) : null;

        if (! $entry instanceof Entry) {
            return;
        }

        $attributes['data'] = $this->fields->restore($attributes['data'], $this->fields->storedData($entry));
        $revision->attributes($attributes);
    }

    /**
     * The revision key prefix of a `people` entry.
     *
     * A method rather than a constant so that the mutation runner, which skips a
     * line no test reports as covered, tests each change to it.
     */
    private static function revisionKeyPrefix(): string
    {
        return 'collections/'.PeopleEditingScope::COLLECTION.'/';
    }
}
