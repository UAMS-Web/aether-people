<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

/**
 * The trainee type of a `people` entry, held in one `trainee_type` field in place of a yes/no
 * field per type (UAMS-Web/uams-statamic-archive#3135, UAMS-Web/aether-people#58).
 *
 * The field holds one of three keys: `none`, `resident` and `fellow`. A person is never two
 * kinds of trainee, which the old fields enforced by hiding the fellow question from a
 * resident. An entry that was never asked has no value, and counts as no trainee.
 */
final class TraineeType
{
    /**
     * The handle of the field.
     */
    public const string FIELD = 'trainee_type';

    /**
     * The value for a person who is not a trainee.
     */
    public const string NONE = 'none';

    /**
     * The value for a medical resident.
     */
    public const string RESIDENT = 'resident';

    /**
     * The value for a medical fellow.
     */
    public const string FELLOW = 'fellow';

    /**
     * The handle of the yes/no field the resident answer was stored in before the trainee-type field.
     */
    public const string LEGACY_RESIDENT_FIELD = 'resident_query';

    /**
     * The handle of the yes/no field the fellow answer was stored in before the trainee-type field.
     */
    public const string LEGACY_FELLOW_FIELD = 'fellow_query';

    /**
     * Whether a stored trainee type is a resident.
     *
     * @param  mixed  $value  The stored value of the field
     */
    public static function isResident(mixed $value): bool
    {
        return $value === self::RESIDENT;
    }

    /**
     * An entry's data with the old yes/no fields carried over to the trainee-type field, or null when it carries none.
     *
     * A yes in `resident_query` makes a resident, otherwise a yes in `fellow_query` a fellow, and
     * an answer of no to either a person who is not a trainee. An entry that was never asked
     * keeps no value. A trainee type already stored is left as it is, and the Workday
     * provenance list names the new field wherever it named an old one, so a locked field stays locked.
     *
     * @param  array<array-key, mixed>  $data  The entry's data as stored
     * @return array<array-key, mixed>|null
     */
    public static function carryOver(array $data): ?array
    {
        $resident = $data[self::LEGACY_RESIDENT_FIELD] ?? null;
        $fellow = $data[self::LEGACY_FELLOW_FIELD] ?? null;
        $supplied = $data[WorkdayManagedFields::PROVENANCE_KEY] ?? null;

        $namesLegacy = \is_array($supplied)
            && (\in_array(self::LEGACY_RESIDENT_FIELD, $supplied, true) || \in_array(self::LEGACY_FELLOW_FIELD, $supplied, true));

        if (! \array_key_exists(self::LEGACY_RESIDENT_FIELD, $data) && ! \array_key_exists(self::LEGACY_FELLOW_FIELD, $data) && ! $namesLegacy) {
            return null;
        }

        unset($data[self::LEGACY_RESIDENT_FIELD], $data[self::LEGACY_FELLOW_FIELD]);

        if (($data[self::FIELD] ?? null) === null) {
            $type = match (true) {
                self::answeredYes($resident) => self::RESIDENT,
                self::answeredYes($fellow) => self::FELLOW,
                $resident !== null || $fellow !== null => self::NONE,
                default => null,
            };

            if ($type !== null) {
                $data[self::FIELD] = $type;
            }
        }

        if ($namesLegacy) {
            $data[WorkdayManagedFields::PROVENANCE_KEY] = self::renamedProvenance($supplied);
        }

        return $data;
    }

    /**
     * Whether a stored yes/no answer is a yes.
     *
     * The string `false` is the one import form a plain cast reads as true; every other form
     * casts as intended.
     *
     * @param  mixed  $value  The stored answer
     */
    private static function answeredYes(mixed $value): bool
    {
        return $value !== 'false' && $value;
    }

    /**
     * A Workday provenance list with the old trainee handles replaced by the new one, once.
     *
     * @param  array<array-key, mixed>  $supplied  The stored provenance list
     * @return list<mixed>
     */
    private static function renamedProvenance(array $supplied): array
    {
        $renamed = [];

        foreach ($supplied as $handle) {
            $handle = \in_array($handle, [self::LEGACY_RESIDENT_FIELD, self::LEGACY_FELLOW_FIELD], true) ? self::FIELD : $handle;

            if (! \in_array($handle, $renamed, true)) {
                $renamed[] = $handle;
            }
        }

        return $renamed;
    }
}
