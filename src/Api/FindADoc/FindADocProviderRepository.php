<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Api\FindADoc;

use Illuminate\Support\Collection;
use Statamic\Entries\Collection as EntryCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use UAMSWeb\Aether\People\Support\PeopleCollection;

/**
 * Resolves the `people` entries exposable through the Find-a-Doc read API (UAMS-Web/uams-statamic-archive#1043).
 *
 * A "provider" in the API is a `people` entry eligible for a clinical-provider
 * profile (UAMS-Web/uams-statamic-archive#919) AND currently searchable - the exact predicate the public
 * directory and the FaD block apply
 * ({@see ProviderPeopleListingComputedValues}). Enforcing both here is the API's
 * security boundary: an ineligible person, a separated/hidden provider, or an
 * unpublished entry is never returned, so a caller cannot reach a provider the
 * site itself would not list.
 */
final class FindADocProviderRepository
{
    /**
     * The collection that backs provider profiles.
     */
    private static function peopleCollection(): string
    {
        return PeopleCollection::handle();
    }

    public function __construct(
        private readonly ProviderPeopleListingComputedValues $computed
    ) {}

    /**
     * Every published `people` entry that is provider-eligible and searchable.
     *
     * @return Collection<int, Entry> The exposable providers
     */
    public function eligibleProviders(): Collection
    {
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::peopleCollection());
        $query->whereStatus('published');

        /** @var Collection<int, Entry> $people */
        $people = $query->get();

        return $people
            ->filter(fn (Entry $person): bool => $this->isExposable($person))
            // A provider localized to several sites is one contract record; its
            // localizations share an id, so keep one to avoid duplicate entries.
            ->unique(fn (Entry $person) => $person->id())
            ->values();
    }

    /**
     * A single exposable provider by entry id, or null when it does not exist,
     * is not a person, or fails the eligibility / searchable gate.
     */
    public function findEligible(string $id): ?Entry
    {
        $entry = EntryFacade::find($id);

        if (! $entry instanceof Entry) {
            return null;
        }

        /** @var ?EntryCollection $collection */
        $collection = $entry->collection();

        if ($collection === null || $collection->handle() !== self::peopleCollection()) {
            return null;
        }

        return $this->isExposable($entry) ? $entry : null;
    }

    /**
     * Whether the person is published, provider-eligible, and searchable.
     */
    private function isExposable(Entry $person): bool
    {
        return $person->published()
            && $this->computed->computeClinicalProviderProfileEligibleQuery($person)
            && $this->computed->computeSearchable($person);
    }
}
