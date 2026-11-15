<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Scopes;

use Statamic\Entries\Entry;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use UAMSWeb\Aether\People\Support\PeopleCollection;

/**
 * Entry query scope that constrains a `people` picker to the clinical-provider-
 * profile-eligible subset, so an editor cannot select - and UAMS-Web/uams-statamic-archive#1361's stored
 * bidirectional sync cannot then write back - a person who is not eligible for a
 * UAMS Health clinical provider profile.
 *
 * Listed as a `query_scopes` handle on the `uamsweb_common_fad.providers`
 * ("Related Clinical Providers") entries field. It reuses the exact predicate the
 * UAMS-Web/uams-statamic-archive#919 output filter applies - `ProviderPeopleListingComputedValues::computeClinicalProviderProfileEligibleQuery()`
 * - so the CP selection surface and the live provider directories agree on who is a
 * provider, with no divergent re-evaluation of eligibility.
 *
 * ## Why the eligible ids are resolved in memory
 *
 * Eligibility is a computed flag (`ClinicalProviderProfileEligibility` +
 * `config/clinical_provider_profile.php`), not stored or indexed, so it cannot be
 * expressed as a Stache `where` on a column. The eligible set is a small subset of
 * `people`, so this resolves the passing ids once and constrains the picker query by
 * id - the "direct in-memory `where`" UAMS-Web/uams-statamic-archive#919 recorded for this scope, needing no
 * persisted mirror on the file/Stache driver. (A SQL/eloquent driver migration would
 * want an indexed mirror; see the UAMS-Web/uams-statamic-archive#919 decision's driver caveat.)
 *
 * Eligibility only - NOT `searchable`: an actively-separated or hidden provider stays
 * selectable so editors can manage its associations, while UAMS-Web/uams-statamic-archive#919's output filter keeps
 * it out of the public render. See UAMS-Web/uams-statamic-archive#1405.
 */
class ClinicalProviderEligiblePeople extends Scope
{
    /**
     * @param  ProviderPeopleListingComputedValues  $computed  Shared provider/people eligibility predicate (reused from UAMS-Web/uams-statamic-archive#919)
     * @param  StacheEntryRepository  $entries  Statamic Stache entry repository (`whereCollection` returns a typed collection)
     */
    public function __construct(
        private readonly ProviderPeopleListingComputedValues $computed,
        private readonly StacheEntryRepository $entries
    ) {}

    /**
     * Constrain the query to `people` entries eligible for a clinical provider profile.
     *
     * @param  Builder  $query  Entry query builder for the picker
     * @param  array<string, mixed>  $values  Scope invocation context (unused; the scope is single-purpose)
     */
    public function apply(
        $query,
        $values
    ): void {
        $query->whereIn(
            'id',
            $this->eligiblePeopleIds()
        );
    }

    /**
     * Resolve the ids of every `people` entry eligible for a clinical provider profile.
     *
     * @return array<int, string> Eligible people entry ids
     */
    private function eligiblePeopleIds(): array
    {
        $eligible = [];

        foreach ($this->entries->whereCollection(PeopleCollection::handle()) as $person) {
            // Evaluate the shared UAMS-Web/uams-statamic-archive#919 eligibility predicate on each concrete entry.
            if (
                $person instanceof Entry
                && $this->computed->computeClinicalProviderProfileEligibleQuery($person)
            ) {
                $id = $person->id();

                if (\is_string($id)) {
                    $eligible[] = $id;
                }
            }
        }

        return $eligible;
    }
}
