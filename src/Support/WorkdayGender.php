<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Statamic\Entries\Entry;

/**
 * The rule that keeps a person's `gender` and the value Workday holds for it
 * apart (UAMS-Web/uams-statamic-archive#3187, decided on UAMS-Web/uams-statamic-archive#3131 and UAMS-Web/uams-statamic-archive#3132).
 *
 * `gender` stays the field every reader uses. `workday_gender` holds what the
 * Workday sync last sent, and only the sync writes it. The sync copies its value
 * into `gender` only while `gender` is empty or still equals the previous
 * `workday_gender`; otherwise the entry is overridden, and a new Workday value
 * keeps the override and flags the entry for review. The flag is a data key
 * outside the blueprint, so no Control Panel form can set or clear it, and it is
 * cleared by confirming the override or by taking Workday's value. Nothing here
 * saves the entry or sends mail: the caller saves.
 */
final class WorkdayGender
{
    /**
     * The field every reader uses.
     */
    public const string FIELD = 'gender';

    /**
     * The read-only field holding what the Workday sync last sent.
     */
    public const string WORKDAY_FIELD = 'workday_gender';

    /**
     * The entry data key marking an override Workday has since disagreed with.
     */
    public const string REVIEW_KEY = 'workday_gender_review';

    /**
     * Record a value from the Workday sync, and copy it into `gender` when the
     * entry is not overridden. An empty Workday value changes nothing.
     *
     * @param  Entry  $entry  The person entry, saved afterwards by the caller
     * @param  string|null  $workdayValue  The gender Workday sent
     */
    public function apply(Entry $entry, ?string $workdayValue): void
    {
        if ($workdayValue === null || $workdayValue === '') {
            return;
        }

        // Read the values the rule compares before anything is written
        $previous = $this->stringValue($entry->get(self::WORKDAY_FIELD));
        $gender = $this->stringValue($entry->get(self::FIELD));

        $entry->set(self::WORKDAY_FIELD, $workdayValue);

        // Follow Workday while gender is empty or still matches what it last sent
        if ($gender === null || $gender === $previous) {
            $entry->set(self::FIELD, $workdayValue);
            $entry->remove(self::REVIEW_KEY);

            return;
        }

        // Workday now agrees with the override, so there is nothing to review
        if ($gender === $workdayValue) {
            $entry->remove(self::REVIEW_KEY);

            return;
        }

        // Flag a changed Workday value, or a first sync that disagrees with gender
        if ($workdayValue !== $previous) {
            $entry->set(self::REVIEW_KEY, true);
        }
    }

    /**
     * Keep the override and clear the flag.
     *
     * @param  Entry  $entry  The person entry, saved afterwards by the caller
     */
    public function confirmOverride(Entry $entry): void
    {
        $entry->remove(self::REVIEW_KEY);
    }

    /**
     * Replace `gender` with Workday's value and clear the flag. An entry with no
     * Workday value keeps its `gender`.
     *
     * @param  Entry  $entry  The person entry, saved afterwards by the caller
     */
    public function takeWorkdayValue(Entry $entry): void
    {
        $workday = $this->stringValue($entry->get(self::WORKDAY_FIELD));

        if ($workday !== null) {
            $entry->set(self::FIELD, $workday);
        }

        $entry->remove(self::REVIEW_KEY);
    }

    /**
     * Whether `gender` differs from a Workday value the entry holds.
     *
     * @param  Entry  $entry  The person entry
     */
    public function isOverridden(Entry $entry): bool
    {
        $workday = $this->stringValue($entry->get(self::WORKDAY_FIELD));

        return $workday !== null && $this->stringValue($entry->get(self::FIELD)) !== $workday;
    }

    /**
     * Whether the entry waits on review: flagged, and still overridden. A flag on
     * an entry whose `gender` was since set to Workday's value by hand is moot.
     *
     * @param  Entry  $entry  The person entry
     */
    public function isFlagged(Entry $entry): bool
    {
        return $entry->get(self::REVIEW_KEY) === true && $this->isOverridden($entry);
    }

    /**
     * A non-empty string value, or null for anything else.
     *
     * @param  mixed  $value  A stored value
     */
    private function stringValue(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
