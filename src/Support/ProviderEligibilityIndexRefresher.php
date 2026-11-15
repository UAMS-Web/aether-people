<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Stache;
use Statamic\Stache\Indexes\Index;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Store;
use UAMSWeb\Aether\People\Jobs\RefreshProviderEligibilityForDatedLocations;
use UAMSWeb\Aether\People\Listeners\RefreshProviderEligibilityForLocation;

/**
 * Re-indexes the clinical provider profile eligibility of the people linked to given practice locations.
 *
 * Eligibility requires a linked `clinical_locations` entry whose status is published (UAMS-Web/uams-statamic-archive#3123, UAMS-Web/uams-statamic-archive#3178),
 * so a person's `clinical_provider_profile_eligible_query` computed value depends on other entries. The
 * Stache refreshes a computed value index only when the indexed entry itself is saved, so whatever
 * changes a location's status, a save or delete ({@see RefreshProviderEligibilityForLocation})
 * or its date passing ({@see RefreshProviderEligibilityForDatedLocations}), refreshes the
 * linked people through here.
 */
final class ProviderEligibilityIndexRefresher
{
    /**
     * The computed field whose people index is refreshed.
     */
    public const string ELIGIBILITY_INDEX = 'clinical_provider_profile_eligible_query';

    /**
     * Refresh the indexed eligibility of every person whose stored `clinical_locations` names one of the locations.
     *
     * @param  list<string>  $locationIds  IDs of the `clinical_locations` entries whose status may have changed.
     * @return int How many people were refreshed.
     */
    public function refreshPeopleLinking(array $locationIds): int
    {
        if ($locationIds === []) {
            return 0;
        }

        /** @var AggregateStore $entries */
        $entries = Stache::store('entries');

        /** @var Store $people */
        $people = $entries->store(PeopleCollection::handle());

        /** @var Index $eligibility */
        $eligibility = $people->index(self::ELIGIBILITY_INDEX);

        $refreshed = 0;

        foreach ($this->peopleLinking($people, $locationIds) as $personId) {
            $person = EntryFacade::find($personId);

            // A person the index names always resolves, so this narrows the type for phpstan and
            // forcing it true changes nothing.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($person instanceof Entry) {
                $eligibility->updateItem($person);
                $refreshed++;
            }
        }

        return $refreshed;
    }

    /**
     * IDs of the people whose stored `clinical_locations` names any of the locations.
     *
     * Read from the people store's value index, so finding them loads no person that does not link one.
     *
     * @param  list<string>  $locationIds
     * @return list<string>
     */
    private function peopleLinking(Store $people, array $locationIds): array
    {
        $linking = [];

        /** @var Index $locations */
        $locations = $people->index('clinical_locations');

        /** @var Collection<string, mixed> $items */
        $items = $locations->items();

        foreach ($items as $personId => $value) {
            // A stored list or a single ID; anything that cannot be an entry ID names no location
            $ids = array_filter(\is_array($value) ? $value : [$value], is_string(...));

            if (array_intersect($locationIds, $ids) !== []) {
                $linking[] = $personId;
            }
        }

        return $linking;
    }
}
