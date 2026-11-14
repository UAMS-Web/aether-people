<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Listeners;

use Statamic\Entries\Entry;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use UAMSWeb\Aether\People\Jobs\RefreshProviderEligibilityForDatedLocations;
use UAMSWeb\Aether\People\Support\ProviderEligibilityIndexRefresher;

/**
 * Re-indexes the eligibility of every person linked to a practice location when that location changes.
 *
 * Clinical provider profile eligibility requires a published entry in `clinical_locations` (UAMS-Web/uams-statamic-archive#3123), so
 * a person's `clinical_provider_profile_eligible_query` computed value depends on another entry's status.
 * The Stache refreshes a computed value index only when the indexed entry itself is saved, so without
 * this listener publishing, unpublishing or deleting a location would leave the provider listings that
 * filter on that index (`clinical_provider_profile_eligible_query:is="true"`) stale until each person
 * was re-saved or the Stache rebuilt. A location whose date passes without a save is caught by
 * {@see RefreshProviderEligibilityForDatedLocations} instead (UAMS-Web/uams-statamic-archive#3178).
 */
final class RefreshProviderEligibilityForLocation
{
    public function __construct(private readonly ProviderEligibilityIndexRefresher $refresher) {}

    /**
     * Refresh linked people when a `clinical_locations` entry is saved or deleted.
     */
    public function handle(EntrySaved|EntryDeleted $event): void
    {
        $location = $event->entry;

        // The event types the entry only as the contract, and phpstan needs the narrowing; every
        // entry Statamic dispatches here is this class, so forcing the check true changes nothing.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $location instanceof Entry || $location->collectionHandle() !== 'clinical_locations') {
            return;
        }

        $locationId = $location->id();

        if (! \is_string($locationId)) {
            return;
        }

        $this->refresher->refreshPeopleLinking([$locationId]);
    }
}
