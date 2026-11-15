<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use UAMSWeb\Aether\People\Jobs\RefreshProviderEligibilityForDatedLocations;

/**
 * Evaluates people entries against `config('clinical_provider_profile')` for UAMS
 * Health clinical provider profile eligibility.
 */
final class ClinicalProviderProfileEligibility
{
    /**
     * Whether the people entry satisfies all base rules and at least one path.
     *
     * @param  Entry  $entry  People collection entry to evaluate
     * @return bool True when eligible for a UAMS Health clinical provider profile
     */
    public function qualifies(Entry $entry): bool
    {
        // Load declarative rules from config
        /** @var array<string, mixed> $config */
        $config = \is_array(\config('clinical_provider_profile', []))
            ? \config('clinical_provider_profile', [])
            : [];

        // Check base requirements (all must pass)
        /** @var array<array-key, array<string, mixed>> $base */
        $base = \is_array($config['base'] ?? null)
            ? $config['base']
            : [];

        if (! $this->rulesAllPass(
            $entry,
            $base
        )) {
            return false;
        }

        // Check alternative paths (any path may qualify)
        $paths = $config['paths'] ?? [];

        // Guard non-iterable config values before iterating paths
        if (! \is_array($paths)) {
            $paths = [];
        }

        foreach ($paths as $rules) {
            // Skip non-array path definitions from config
            if (! \is_array($rules)) {
                continue;
            }

            // Check whether this path's rules all pass
            /** @var array<array-key, array<string, mixed>> $pathRules */
            $pathRules = $rules;

            if ($this->rulesAllPass(
                $entry,
                $pathRules
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate a list of rules; short-circuit on first failure.
     *
     * @param  Entry  $entry  People entry providing field values
     * @param  array<array-key, array<string, mixed>>  $rules  Rule rows from config
     * @return bool True when every rule passes
     */
    private function rulesAllPass(Entry $entry, array $rules): bool
    {
        return array_all($rules, fn (array $rule): bool => $this->rulePasses(
            $entry,
            $rule
        ));
    }

    /**
     * Dispatch by operator to the matching evaluator.
     *
     * @param  Entry  $entry  People entry providing field values
     * @param  array<string, mixed>  $rule  Single rule row from config
     * @return bool True when the rule passes
     */
    private function rulePasses(
        Entry $entry,
        array $rule
    ): bool {
        $field = $rule['field'] ?? null;

        // Reject rules without a field handle
        if (! \is_string($field) || $field === '') {
            return false;
        }

        // Equivalent for the '' default: any text that names no operator reaches the same default arm.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $operator = $rule['operator'] ?? '';

        // Read the raw field value (no default so missing stays null)
        $actual = $entry->get($field);

        return match ($operator) {
            'equals' => $this->matchesEquals(
                $actual,
                $rule['value'] ?? null
            ),
            'not_equals' => $this->matchesNotEquals(
                $actual,
                $rule['value'] ?? null
            ),
            'not_empty' => $this->isNonEmpty($actual),
            'contains_any' => $this->fieldContainsAnySlug(
                $actual,
                $rule['values'] ?? []
            ),
            'has_published_entry' => $this->referencesPublishedEntry($actual),
            default => false,
        };
    }

    /**
     * Strict-ish equality; booleans compare after normalization so unset reads as false.
     *
     * @param  mixed  $actual  Raw field value from the entry
     * @param  mixed  $expected  Config comparison value
     * @return bool True when values compare equal per operator semantics
     */
    private function matchesEquals(
        mixed $actual,
        mixed $expected
    ): bool {
        // Compare booleans with normalized toggle semantics
        if (\is_bool($expected)) {
            return $this->normalizeToBool($actual) === $expected;
        }

        // Compare strings case-insensitively for subtype-like fields
        if (\is_string($expected)) {
            return \is_string($actual)
                && \strcasecmp(
                    $actual,
                    $expected
                ) === 0;
        }

        // Fallback to identity for other expected types
        return $actual === $expected;
    }

    /**
     * Inverse of equals for the expected config value.
     *
     * @param  mixed  $actual  Raw field value from the entry
     * @param  mixed  $expected  Config comparison value
     * @return bool True when values differ per operator semantics
     */
    private function matchesNotEquals(
        mixed $actual,
        mixed $expected
    ): bool {
        // Invert equals result for booleans and strings
        if (\is_bool($expected)) {
            return $this->normalizeToBool($actual) !== $expected;
        }

        if (\is_string($expected)) {
            return ! \is_string($actual)
                || \strcasecmp(
                    $actual,
                    $expected
                ) !== 0;
        }

        return $actual !== $expected;
    }

    /**
     * Coerce Statamic-style toggles to bool (null counts as false).
     *
     * @param  mixed  $value  Raw toggle from the entry or imports
     * @return bool Normalized boolean
     */
    private function normalizeToBool(mixed $value): bool
    {
        // The string 'false' is the one import form a plain cast reads as true. Every other
        // form casts as intended: true, 'true', '1' and 1 to true; false, null, '', '0' and 0
        // to false.
        if ($value === 'false') {
            return false;
        }

        return (bool) $value;
    }

    /**
     * True when the value has at least one element (arrays, collections).
     *
     * @param  mixed  $value  Raw field value (e.g. locations entry IDs)
     * @return bool True when non-empty per supported shapes
     */
    private function isNonEmpty(mixed $value): bool
    {
        // Treat null and empty string as empty
        if ($value === null || $value === '') {
            return false;
        }

        // Check array length
        if (\is_array($value)) {
            return $value !== [];
        }

        // Check Laravel collection count
        if ($value instanceof IlluminateCollection) {
            return $value->isNotEmpty();
        }

        // Other scalars are non-empty when present
        return true;
    }

    /**
     * Whether the value names at least one entry that exists and whose status is published.
     *
     * Drafts and IDs that resolve to no entry do not count, so a person linked only to
     * draft or deleted locations reads the same as one linked to none (UAMS-Web/uams-statamic-archive#3123). The status,
     * not the published flag, decides it: `clinical_locations` keeps a future-dated entry
     * published but private, so a scheduled location counts only from its date (UAMS-Web/uams-statamic-archive#3178).
     * {@see RefreshProviderEligibilityForDatedLocations} re-indexes the linked
     * people when that date passes.
     *
     * @param  mixed  $value  Entry field (a list or collection of entry IDs, or one ID)
     * @return bool True when at least one referenced entry's status is published
     */
    private function referencesPublishedEntry(mixed $value): bool
    {
        // Normalize a collection or a single ID to a list of IDs
        if ($value instanceof IlluminateCollection) {
            $value = $value->all();
        }

        if (! \is_array($value)) {
            $value = [$value];
        }

        foreach ($value as $id) {
            // Skip anything that cannot be an entry ID
            if (! \is_string($id)) {
                continue;
            }

            $referenced = EntryFacade::find($id);

            if ($referenced instanceof Entry && $referenced->status() === 'published') {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any credential slug matches the configured allow list (case-insensitive).
     *
     * @param  mixed  $value  Entry field (list of taxonomy term slugs)
     * @param  mixed  $allowed  Config list of slug strings
     * @return bool True when at least one slug intersects the allow list
     */
    private function fieldContainsAnySlug(
        mixed $value,
        mixed $allowed
    ): bool {
        // Normalize allow list to lowercase string keys
        if (! \is_array($allowed)) {
            return false;
        }

        $allowedSet = [];

        foreach ($allowed as $slug) {
            // Skip non-string entries in config
            if (! \is_string($slug)) {
                continue;
            }

            $trimmed = \trim($slug);

            // Skip empty strings. Equivalent: the `$key !== ''` check below never lets an
            // empty slug match, so a '' key in the set changes nothing. The two back each other up.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($trimmed === '') {
                continue;
            }

            // Equivalent for the stored value: membership is read with isset(), true for false too.
            // @pest-mutate-ignore: TrueToFalse
            $allowedSet[\strtolower($trimmed)] = true;
        }

        // Fail fast when nothing is configured to match. Equivalent without the return: an
        // empty set matches no slug, so the walk below returns the same false.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($allowedSet === []) {
            return false;
        }

        // Normalize stored credentials to a flat slug list
        if ($value instanceof IlluminateCollection) {
            $value = $value->all();
        }

        // Non-array stored values cannot satisfy contains_any
        if (! \is_array($value)) {
            return false;
        }

        foreach ($value as $slug) {
            // Compare each slug after trimming and lowercasing
            if (! \is_string($slug) && ! \is_int($slug)) {
                continue;
            }

            $key = \strtolower(\trim((string) $slug));

            // Check membership in the allowed slug set. Equivalent for the '' check: the set
            // never holds an empty key (see above).
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($key !== '' && isset($allowedSet[$key])) {
                return true;
            }
        }

        return false;
    }
}
