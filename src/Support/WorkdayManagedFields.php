<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\File;
use Statamic\Entries\Entry;
use Statamic\Facades\YAML;

/**
 * The `people` fields Workday owns, which no Control Panel user other than an
 * administrator may change (UAMS-Web/uams-statamic-archive#3186, decided on UAMS-Web/uams-statamic-archive#3130).
 *
 * The Workday IDs, `uams_worker_query`, the two subtypes and the legal name
 * parts in `person_name` are always Workday's; the nickname, the pronunciation
 * and the display format are kept in fields of their own that stay editable
 * (see `PersonNameComposite`), and `gender` stays editable beside the read-only
 * `workday_gender` (see `WorkdayGender`). A trainee status field is Workday's only once the Workday sync has
 * listed it in the entry's `workday_supplied` data, which only the sync writes;
 * until then it is an ordinary field an editor answers. The administrators the
 * people editing scope leaves unscoped (super users, and users who may configure
 * collections) are exempt, so they can enter and correct these values while no
 * sync exists. With no signed-in user (an import, a console command, the sync
 * itself) nothing is locked.
 */
final class WorkdayManagedFields
{
    /**
     * The entry data key listing the trainee fields Workday has supplied.
     */
    public const string PROVENANCE_KEY = 'workday_supplied';

    /**
     * Fields that are always Workday's. `uams_workday_id` is the group holding
     * the four Workday IDs, so locking it locks each of them, `person_name`
     * holds only the legal name parts, and `workday_gender` holds what Workday
     * sent for `gender`, which itself stays editable (UAMS-Web/uams-statamic-archive#3187).
     *
     * @var list<string>
     */
    public const array ALWAYS_LOCKED = [
        'person_name',
        'uams_worker_query',
        'uams_worker_subtype',
        'uams_employee_subtype',
        'uams_workday_id',
        WorkdayGender::WORKDAY_FIELD,
    ];

    /**
     * Trainee status fields, Workday's only when the entry's provenance list
     * names them.
     *
     * @var list<string>
     */
    public const array LOCKED_WHEN_SUPPLIED = [
        'resident_query',
        'fellow_query',
    ];

    public function __construct(
        private readonly PeopleEditingScope $scope
    ) {}

    /**
     * Whether the user is held to the Workday-managed fields.
     *
     * @param  Authenticatable|null  $user  The signed-in user, or null outside a request
     */
    public function appliesTo(?Authenticatable $user): bool
    {
        return $this->scope->appliesTo($user);
    }

    /**
     * Whether an entry belongs to the collection these fields live in.
     *
     * @param  mixed  $entry  The entry an event carried
     */
    public function governs(mixed $entry): bool
    {
        return $this->scope->governs($entry);
    }

    /**
     * The data an entry holds on disk, read from its file rather than from the
     * object, which a save has already changed by the time it is written. A new
     * entry has no file and so no stored data.
     *
     * @param  Entry  $entry  The entry, as loaded or as about to be saved
     * @return array<array-key, mixed>
     */
    public function storedData(Entry $entry): array
    {
        $path = $entry->initialPath() ?? $entry->path();

        if (! \is_string($path) || ! File::exists($path)) {
            return [];
        }

        return YAML::file($path)->parse();
    }

    /**
     * The handles locked for an entry whose stored data is given.
     *
     * @param  array<array-key, mixed>  $storedData  The entry's data as stored
     * @return list<string>
     */
    public function lockedHandles(array $storedData): array
    {
        $supplied = $this->suppliedTraineeHandles($storedData);

        return [...self::ALWAYS_LOCKED, ...$supplied];
    }

    /**
     * Whether a handle is locked for an entry whose stored data is given.
     *
     * @param  string  $handle  The field handle
     * @param  array<array-key, mixed>  $storedData  The entry's data as stored
     */
    public function isLocked(string $handle, array $storedData): bool
    {
        return \in_array($handle, $this->lockedHandles($storedData), true);
    }

    /**
     * Put the stored value of every locked field, and of the provenance list,
     * back into data about to be written. A field with no stored value is
     * removed, so a locked field cannot be given its first value either.
     *
     * @param  array<array-key, mixed>  $incomingData  The data about to be written
     * @param  array<array-key, mixed>  $storedData  The entry's data as stored
     * @return array<array-key, mixed>
     */
    public function restore(array $incomingData, array $storedData): array
    {
        $handles = [...$this->lockedHandles($storedData), self::PROVENANCE_KEY];

        foreach ($handles as $handle) {
            // Keep the stored value, or drop one that was never stored
            if (\array_key_exists($handle, $storedData)) {
                $incomingData[$handle] = $storedData[$handle];
            } else {
                unset($incomingData[$handle]);
            }
        }

        return $incomingData;
    }

    /**
     * The trainee fields the stored provenance list names.
     *
     * @param  array<array-key, mixed>  $storedData  The entry's data as stored
     * @return list<string>
     */
    private function suppliedTraineeHandles(array $storedData): array
    {
        $supplied = $storedData[self::PROVENANCE_KEY] ?? [];

        if (! \is_array($supplied)) {
            return [];
        }

        return array_values(array_filter(
            self::LOCKED_WHEN_SUPPLIED,
            static fn (string $handle): bool => \in_array($handle, $supplied, true)
        ));
    }
}
