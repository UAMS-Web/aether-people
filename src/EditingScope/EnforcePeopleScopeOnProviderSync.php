<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\EditingScope;

use Exception;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User;
use Statamic\Facades\YAML;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;

/**
 * Refuses a scoped editor's save that would change a `people` entry outside their
 * organizations through a relationship sync (UAMS-Web/uams-statamic-archive#3223, decided there to cover every
 * field the addon syncs into `people`).
 *
 * The Stillat relationships addon keeps each field below in sync with its
 * reciprocal on `people` (registered in `StillatRelationshipsServiceProvider`),
 * and writes the person with `saveQuietly()` after the save, so
 * `EnforcePeopleEditingScope` never sees that write. Adding a person to
 * one of these fields, or removing one, rewrites that person's record, so a
 * scoped editor may only add or remove people in scope. People already listed
 * and left unchanged do not block the save, so an entry that lists another
 * organization's person stays editable. Listing another organization's person is
 * an administrator's change.
 *
 * The addon writes on `EntrySaved` and only records state on `EntrySaving`, so
 * refusing here stops both the entry and the person being written. It is subscribed
 * by the add-on's service provider after `EnforcePeopleEditingScope` (UAMS-Web/uams-statamic-archive#3240).
 */
class EnforcePeopleScopeOnProviderSync
{
    public function __construct(
        protected PeopleEditingScope $scope
    ) {}

    /**
     * The synced field on each collection whose reciprocal is on `people`.
     *
     * A method rather than a constant, so the mutation runner tests each item.
     *
     * @return array<string, string> Field handle keyed by collection handle.
     */
    public static function syncedFields(): array
    {
        return [
            'clinical_locations' => 'physician_locations',
            'clinical_expertise' => 'clinical_providers',
            'clinical_conditions' => 'clinical_providers',
            'clinical_services' => 'clinical_providers',
            'clinical_resources' => 'clinical_providers',
            'recognition_lists' => 'members',
        ];
    }

    /**
     * Abort the save when a scoped user adds or removes an out-of-scope person.
     *
     * @throws Exception When an added or removed person is outside the scope
     */
    public function handle(EntrySaving $event): void
    {
        $entry = $event->entry;

        // Check whether the entry is in a collection whose field syncs to people
        if (! $entry instanceof Entry) {
            return;
        }

        $collection = $entry->collectionHandle();
        $field = \is_string($collection) ? (self::syncedFields()[$collection] ?? null) : null;

        if ($field === null) {
            return;
        }

        // Check whether the signed-in user is limited to their organizations
        $user = User::current();

        if ($user === null || ! $this->scope->appliesTo($user)) {
            return;
        }

        // Find the people added to or removed from the field by this save, reading
        // what is stored rather than anything the repository hands back or the entry's
        // original data, which a lookup during the save can overwrite
        $before = $this->ids($this->storedValue($entry, $field));
        $after = $this->ids($entry->get($field));
        $changed = array_merge(array_diff($after, $before), array_diff($before, $after));

        foreach ($changed as $id) {
            $person = $this->scope->governedEntry(EntryFacade::find($id));

            // Skip an id that names no person the editing scope governs, including
            // one that names no entry at all
            if (! $person instanceof Entry) {
                continue;
            }

            if (! $this->scope->allows($user, $person)) {
                throw new Exception(sprintf(
                    'You cannot add or remove %s: they are not linked to an '
                    .'organization you edit for. Ask an administrator to change them.',
                    $this->personLabel($person, $id),
                ));
            }
        }
    }

    /**
     * Read a field from the entry as it is stored, before this save.
     *
     * The stored file is read first, because nothing in memory is reliable here: Statamic's own
     * repository returns the stored instance from `find()`, and that instance already carries this
     * save's values, and its original data is synced to those values again by any lookup made
     * after the Control Panel merged them in (a URI uniqueness check is one). The file holds what was
     * last written whatever the host's repository returns.
     *
     * @param  Entry  $entry  The entry being saved
     * @param  string  $field  The field handle
     * @return mixed The stored value, or null for an entry not yet stored
     */
    private function storedValue(Entry $entry, string $field): mixed
    {
        $id = $entry->id();

        // Check whether the entry has been stored before
        if ($id === null) {
            return null;
        }

        // Read the file the entry was loaded from, when it has one that still exists
        $path = $entry->initialPath();

        if (\is_string($path) && is_file($path)) {
            return YAML::file($path)->parse()[$field] ?? null;
        }

        // An entry with no file of its own, such as one built in code with the id of a stored
        // entry, falls back to the repository's stored copy
        $stored = EntryFacade::find($id);

        // Treat an entry the repository does not hold, or hands back as the same
        // instance, as new, so every listed person is checked
        if (! $stored instanceof Entry || $stored === $entry) {
            return null;
        }

        return $stored->get($field);
    }

    /**
     * Name a person for a refusal message, as `Title [id]`.
     *
     * @param  Entry  $person  The person the save would change
     * @param  string  $id  The id the person was found by
     */
    private function personLabel(Entry $person, string $id): string
    {
        $title = $person->get('title');

        return sprintf(
            '%s [%s]',
            \is_string($title) && $title !== '' ? $title : 'Untitled',
            $id,
        );
    }

    /**
     * Normalize a stored relationship value to the entry ids it lists.
     *
     * An id that names no entry, such as an empty string, is skipped by the
     * caller, so only values that are not strings are dropped here.
     *
     * @param  mixed  $value  The field value, a single id or a list of ids
     * @return array<array-key, string> The string ids
     */
    private function ids(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_filter($values, is_string(...));
    }
}
