<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Modifiers;

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Modifiers\Modifier;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use UAMSWeb\Aether\People\Support\ListNormalizer;

/**
 * Antlers modifier: filter a list of `people` entries down to those renderable as
 * clinical providers - eligible for a UAMS Health clinical provider profile AND
 * currently searchable (not in an active separation window, not hidden from
 * provider lists).
 *
 * This is the UAMS-Web/uams-statamic-archive#919 shared "provider relationship" output filter for authored
 * `entries` relationships (`physician_locations`, `clinical_providers`) that are
 * rendered as augmented lists rather than via a `{{ collection:people }}` tag. It
 * mirrors the FaD Providers block's
 * `clinical_provider_profile_eligible_query:is="true" searchable:is="true"` tag
 * conditions: both express the same predicate through the same
 * {@see ProviderPeopleListingComputedValues} methods, so a person listed as a
 * provider anywhere is filtered consistently, without re-solving eligibility per
 * template.
 *
 * Use at each provider-relationship render site, e.g.:
 *   {{ physician_locations | clinical_provider_eligible }}
 *
 * Eligibility is evaluated on the entry directly (not a stored value), so it stays
 * correct as eligibility drifts over time and does not depend on the entries query
 * driver. Non-`people`/unresolvable items are dropped.
 */
class ClinicalProviderEligible extends Modifier
{
    /**
     * @param  ProviderPeopleListingComputedValues  $computed  Provides the eligibility + searchable predicates shared with the people computeds.
     */
    public function __construct(
        private readonly ProviderPeopleListingComputedValues $computed
    ) {}

    /**
     * Filter the value to renderable clinical-provider `people` entries.
     *
     * @param  mixed  $value  A list-like value of people entries or ids (augmented `entries` field, collection, lazy query, or array)
     * @param  array<int|string, mixed>  $params  Unused
     * @param  array<string, mixed>  $context  Unused
     * @return array<int, Entry> Filtered list of eligible, searchable people entries
     */
    public function index(
        $value,
        array $params,
        array $context
    ): array {
        // Normalize the augmented entries value (collection / lazy query / array) to a flat list.
        $items = (new ListNormalizer)->normalize($value);

        // Accumulate only the entries passing both predicates.
        $eligible = [];

        foreach ($items as $item) {
            // Resolve an id reference to its entry; keep entry instances as-is.
            $entry = $this->resolveEntry($item);

            // Drop anything that is not a resolvable entry.
            if (! $entry instanceof Entry) {
                continue;
            }

            // Keep only entries that are both clinical-provider eligible and currently searchable.
            if (
                $this->computed->computeClinicalProviderProfileEligibleQuery($entry)
                && $this->computed->computeSearchable($entry)
            ) {
                $eligible[] = $entry;
            }
        }

        return $eligible;
    }

    /**
     * Resolve a list item to a concrete entry, or null when it cannot be.
     *
     * @param  mixed  $item  An entry instance or an entry id
     * @return Entry|null The resolved entry, or null when it is neither
     */
    private function resolveEntry(
        mixed $item
    ): ?Entry {
        // Already a concrete entry.
        if ($item instanceof Entry) {
            return $item;
        }

        // An id reference (string/int) - look it up.
        if (\is_string($item) || \is_int($item)) {
            $found = EntryFacade::find((string) $item);

            // EntryFacade::find() is typed `?Entry`, so InstanceOfToTrue here is
            // equivalent: null still returns null, and a hit is already an Entry.
            // @pest-mutate-ignore: InstanceOfToTrue
            return $found instanceof Entry ? $found : null;
        }

        return null;
    }
}
