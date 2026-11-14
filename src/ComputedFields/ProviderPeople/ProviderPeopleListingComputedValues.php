<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople;

use Carbon\Carbon;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;
use UAMSWeb\Aether\People\Contracts\LocationRegions;
use UAMSWeb\Aether\People\Support\ClinicalProviderProfileEligibility;

/**
 * Computed-field logic for providers/people listings: related-entry aggregates,
 * separation flags, searchability, and clinical profile eligibility.
 */
class ProviderPeopleListingComputedValues
{
    /**
     * @param  ClinicalProviderProfileEligibility  $clinicalProviderProfileEligibility  Eligibility evaluator for clinical profile computeds
     * @param  StacheEntryRepository  $entries  Statamic Stache entry repository (`whereInId`, `find`, etc.)
     */
    public function __construct(
        private readonly ClinicalProviderProfileEligibility $clinicalProviderProfileEligibility,
        private readonly StacheEntryRepository $entries,
        private readonly LocationRegions $locationAddress
    ) {}

    /**
     * Distinct `brand_organization` values from linked location entries.
     *
     * @param  Entry  $entry  Provider or people entry with `clinical_locations` IDs
     * @return array<array-key, mixed> Non-empty brand organization values
     */
    public function computeBrandOrganizations(
        Entry $entry
    ): array {
        // Get the location IDs from the entry
        $locationIds = $entry->get(
            'clinical_locations',
            []
        );

        // Return early if the location IDs are empty
        if (empty($locationIds)) {
            return [];
        }

        // Get the locations from the location IDs
        /** @var EntryCollection<int, Entry> $locations */
        $locations = $this->entries->whereInId($locationIds);

        // Return the brand organization values from the locations
        return $locations
            ->map(fn (mixed $location) => $location instanceof Entry ? $location->get('brand_organization') : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Distinct region values from linked location entries.
     *
     * @param  Entry  $entry  Provider or people entry with `clinical_locations` IDs
     * @return array<array-key, mixed> Non-empty region values
     */
    public function computeRegions(
        Entry $entry
    ): array {
        // Get the location IDs from the entry
        $locationIds = $entry->get(
            'clinical_locations',
            []
        );

        // Return early if the location IDs are empty
        if (empty($locationIds)) {
            return [];
        }

        // Get the locations from the location IDs
        /** @var EntryCollection<int, Entry> $locations */
        $locations = $this->entries->whereInId($locationIds);

        // Return the region values the locations resolve to: a child location that
        // inherits its street group takes its parent's region (UAMS-Web/uams-statamic-archive#2608)
        return $locations
            ->map(fn (mixed $location): mixed => $location instanceof Entry ? $this->locationAddress->resolveRegions($location) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Portal value from the first linked location entry, if any.
     *
     * @param  Entry  $entry  Provider or people entry with `clinical_locations` IDs
     * @return mixed Stored `portal` field value from the first location, or null
     */
    public function computePortal(
        Entry $entry
    ): mixed {
        // Get the location IDs from the entry
        /** @var array<array-key, mixed> $locationIds */
        $locationIds = $entry->get(
            'clinical_locations',
            []
        );

        // Return early if the location IDs are empty
        if (empty($locationIds)) {
            return null;
        }

        // Get the first location from the location IDs
        $firstLocation = $this->entries->find($locationIds[0]);

        // Return early if the first location is not an entry
        if (! $firstLocation instanceof Entry) {
            return null;
        }

        // Return the portal value from the first location
        return $firstLocation->get('portals');
    }

    /**
     * Semicolon-delimited ASP filter string from expertise, conditions, and treatments
     * titles.
     *
     * @param  Entry  $entry  Provider or people entry with taxonomy relation IDs
     * @return string Unique related entry titles joined with `; `
     */
    public function computeAspFilter(
        Entry $entry
    ): string {
        // Initialize an empty array for the titles
        $titles = [];

        // Get the expertise IDs from the entry
        $expertiseIds = $entry->get(
            'expertise',
            []
        );

        // Check if the expertise IDs are not empty
        if (! empty($expertiseIds)) {
            // Get the expertise titles from the expertise IDs
            /** @var EntryCollection<int, Entry> $expertiseEntries */
            $expertiseEntries = $this->entries->whereInId($expertiseIds);
            $expertiseTitles = $expertiseEntries
                ->filter(
                    fn (mixed $e): bool => $e instanceof Entry && $e->collectionHandle() === 'clinical_expertise'
                )
                ->pluck('title')
                ->filter()
                ->all();

            // Start the titles with the expertise titles (the list is still empty here)
            $titles = $expertiseTitles;
        }

        // Get the condition IDs from the entry
        $conditionIds = $entry->get(
            'conditions',
            []
        );

        // Check if the condition IDs are not empty
        if (! empty($conditionIds)) {
            // Get the condition titles from the condition IDs
            /** @var EntryCollection<int, Entry> $conditionEntries */
            $conditionEntries = $this->entries->whereInId($conditionIds);
            $conditionTitles = $conditionEntries
                ->filter(
                    fn (mixed $e): bool => $e instanceof Entry && $e->collectionHandle() === 'clinical_conditions'
                )
                ->pluck('title')
                ->filter()
                ->all();

            // Merge the condition titles into the titles array
            $titles = [
                ...$titles,
                ...$conditionTitles,
            ];
        }

        // Get the treatment IDs from the entry
        $treatmentIds = $entry->get(
            'treatments',
            []
        );

        // Check if the treatment IDs are not empty
        if (! empty($treatmentIds)) {
            // Get the treatment titles from the treatment IDs
            /** @var EntryCollection<int, Entry> $treatmentEntries */
            $treatmentEntries = $this->entries->whereInId($treatmentIds);
            $treatmentTitles = $treatmentEntries
                ->filter(
                    fn (mixed $e): bool => $e instanceof Entry && $e->collectionHandle() === 'clinical_services'
                )
                ->pluck('title')
                ->filter()
                ->all();

            // Merge the treatment titles into the titles array
            $titles = [
                ...$titles,
                ...$treatmentTitles,
            ];
        }

        // Get the unique titles from the titles array
        /** @var array<int, string> $uniqueTitles */
        $uniqueTitles = collect($titles)
            ->unique()
            ->filter()
            ->values()
            ->all();

        // Return the unique titles joined with `; `
        return \implode(
            '; ',
            $uniqueTitles
        );
    }

    /**
     * Whether the entry should be auto-unpublished after a separation end date in the
     * past.
     *
     * @param  Entry  $entry  Provider or people entry with separation fields
     * @return bool True when separated with an end date before today
     */
    public function computeSeparatedUnpublishQuery(
        Entry $entry
    ): bool {
        // Get the separated flag from the entry
        $separated = $entry->get(
            'clinical_separated_query',
            false
        );

        // Get the separated end date from the entry
        /** @var \DateTimeInterface|string|int|float|null $endDate */
        $endDate = $entry->get('separated_end_date');

        // ---
        // Return early if the separated flag is not true or the separated end date is not
        // set
        // ---
        if (
            ! $separated
            || ! $endDate
        ) {
            return false;
        }

        // Get the current date
        $currentDate = Carbon::today();

        // Parse the separated end date
        $end = Carbon::parse($endDate);

        // Return true if the separated end date is before the current date
        return $end->lt($currentDate);
    }

    /**
     * Whether the entry should appear in front-end search (not hidden and not
     * actively separated).
     *
     * @param  Entry  $entry  Provider or people entry
     * @return bool False when separated in range or when `hidden` is true
     */
    public function computeSearchable(
        Entry $entry
    ): bool {
        // Get the separated active flag from the entry
        $separatedActive = $this->isSeparatedActive($entry);

        // Get the hidden flag from the entry (excluded from provider list)
        $hidden = $entry->get(
            'hidden_from_provider_list_query',
            false
        );

        // ---
        // Return true if the separated active flag is not true or the hidden flag is not
        // true
        // ---
        return ! $separatedActive && ! $hidden;
    }

    /**
     * Whether the entry qualifies for the clinical provider profile eligibility
     * query computed.
     *
     * @param  Entry  $entry  People entry (or providers when applicable)
     * @return bool True when the entry passes clinical provider profile eligibility rules
     */
    public function computeClinicalProviderProfileEligibleQuery(
        Entry $entry
    ): bool {
        // ---
        // Return true if the entry qualifies for the clinical provider profile
        // eligibility query
        // ---
        return $this->clinicalProviderProfileEligibility->qualifies($entry);
    }

    /**
     * Whether the entry is in an active separation window for a published entry.
     *
     * @param  Entry  $entry  Provider or people entry
     * @return bool True when separated, dates bracket today, and status is published
     */
    public function isSeparatedActive(
        Entry $entry
    ): bool {
        // Get the separated flag from the entry
        $separated = $entry->get(
            'clinical_separated_query',
            false
        );

        // Get the separated start date from the entry
        /** @var \DateTimeInterface|string|int|float|null $startDate */
        $startDate = $entry->get('separated_start_date');

        // Get the separated end date from the entry
        /** @var \DateTimeInterface|string|int|float|null $endDate */
        $endDate = $entry->get('separated_end_date');

        // Get the status from the entry
        $status = $entry->status();

        // ---
        // Return early if the separated flag is not true or the separated start date is
        // not set or the separated end date is not set or the status is not published
        // ---
        if (
            ! $separated
            || ! $startDate
            || ! $endDate
            || $status !== 'published'
        ) {
            return false;
        }

        // Get the current date
        $currentDate = Carbon::today();

        // Parse the separated start date
        $start = Carbon::parse($startDate);

        // Parse the separated end date
        $end = Carbon::parse($endDate);

        // ---
        // Return true if the separated start date is less than or equal to the current
        // date and the separated end date is greater than or equal to the current date
        // ---
        return $start->lte($currentDate) && $end->gte($currentDate);
    }
}
