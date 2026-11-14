<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Entries\Entry;

/**
 * Evaluates a `people` entry against `config('researcher_profile.signals')` for
 * researcher-lens eligibility (UAMS-Web/uams-statamic-archive#1667): the person has a public research identity
 * when ANY configured signal field is non-empty.
 *
 * The signal is PERSON-LEVEL (a TRI research profile, a scholarly author
 * identifier, declared research interests) rather than a site's organization role,
 * because researchers are scattered across sites and the org-to-people bridge has
 * no research role. This mirrors {@see ClinicalProviderProfileEligibility} - the
 * clinical lens's person-level gate - so the researcher lens fits the registry
 * with the same shape.
 */
final class ResearcherProfileEligibility
{
    /**
     * Whether the person has any configured researcher signal.
     *
     * @param  Entry  $entry  People collection entry to evaluate.
     * @return bool True when eligible for the researcher lens.
     */
    public function qualifies(Entry $entry): bool
    {
        /** @var array<array-key, mixed> $signals */
        $signals = \is_array(\config('researcher_profile.signals'))
            ? \config('researcher_profile.signals')
            : [];

        foreach ($signals as $field) {
            // A signal is a `people` field handle; a non-empty value qualifies.
            if (\is_string($field) && $field !== '' && $this->isNonEmpty($entry->get($field))) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the value has content (non-null, non-empty string / array / collection).
     *
     * @param  mixed  $value  Raw field value from the entry.
     * @return bool True when the field is present and non-empty.
     */
    private function isNonEmpty(mixed $value): bool
    {
        // Treat null and empty string as empty.
        if ($value === null || $value === '') {
            return false;
        }

        // An empty array (e.g. no research-interest terms) is empty.
        if (\is_array($value)) {
            return $value !== [];
        }

        // An empty Laravel collection is empty.
        if ($value instanceof IlluminateCollection) {
            return $value->isNotEmpty();
        }

        // Any other present scalar counts as non-empty.
        return true;
    }
}
