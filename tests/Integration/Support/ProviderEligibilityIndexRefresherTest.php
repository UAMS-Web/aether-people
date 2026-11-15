<?php

declare(strict_types=1);

use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Path;
use Statamic\Facades\Stache;
use Statamic\Fields\FieldsetRepository;
use Statamic\Stache\Indexes\Index;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Store;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use UAMSWeb\Aether\People\Support\ProviderEligibilityIndexRefresher;

/**
 * Tests that the refresher re-indexes the eligibility of exactly the people whose stored
 * `clinical_locations` names one of the given locations, and counts them.
 *
 * Each case plants a stale value in the people eligibility index. Only a refresh replaces it, so the
 * index shows which people were reached.
 *
 * @command  vendor/bin/pest tests/Integration/Support/ProviderEligibilityIndexRefresherTest.php
 */
covers(ProviderEligibilityIndexRefresher::class);

beforeEach(function (): void {
    // Read host fieldsets from the stand-ins, as the host would from its own directory
    app(FieldsetRepository::class)->setDirectory(__DIR__.'/../../Fixtures/HostFieldsets');

    // Keep the Stache out of the shared fixtures directory, one directory per test
    $root = storage_path('framework/testing/stache/'.uniqid('index-refresher-', true).'/dev-null');
    Stache::stores()->each(function (Store $store) use ($root): void {
        $store->directory(Path::tidy($root.'/'.$store->key()));
    });
    Stache::clear();

    foreach (['people', 'clinical_locations'] as $handle) {
        $collection = CollectionFacade::make($handle);
        $collection->title($handle);
        $collection->save();
    }

    // The host registers the eligibility computed on `people`; the index under test holds its value
    CollectionFacade::computed(
        'people',
        ProviderEligibilityIndexRefresher::ELIGIBILITY_INDEX,
        fn (EntryInstance $entry): bool => app(ProviderPeopleListingComputedValues::class)
            ->computeClinicalProviderProfileEligibleQuery($entry),
    );
});

/**
 * Persist a published practice location.
 */
function refresherLocation(string $slug): EntryInstance
{
    $location = mustBe(EntryInstance::class, Entry::make(), 'a new entry');
    $location->collection('clinical_locations');
    $location->slug($slug);
    $location->published(true);
    $location->data(['title' => $slug]);
    $location->saveQuietly();

    return $location;
}

/**
 * Persist a person who passes every rule when the location they link is published.
 */
function refresherPerson(string $slug, mixed $locations): EntryInstance
{
    $person = mustBe(EntryInstance::class, Entry::make(), 'a new entry');
    $person->collection('people');
    $person->slug($slug);
    $person->published(true);
    $person->data([
        'title' => $slug,
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_employee_subtype' => 'regular',
        'resident_query' => false,
        'fellow_query' => false,
        'clinical_role_query' => true,
        'direct_patient_care_query' => true,
        'credentials' => ['md'],
        'clinical_locations' => $locations,
    ]);
    $person->saveQuietly();

    return $person;
}

/**
 * The people store's eligibility index.
 */
function refresherEligibilityIndex(): Index
{
    $entries = mustBe(AggregateStore::class, Stache::store('entries'), 'the entries store');
    $people = mustBe(Store::class, $entries->store('people'), 'the people store');

    return mustBe(Index::class, $people->index(ProviderEligibilityIndexRefresher::ELIGIBILITY_INDEX), 'the eligibility index');
}

/**
 * Overwrite one person's indexed eligibility with a value only a refresh would replace.
 */
function refresherPlant(EntryInstance $person, bool $value): void
{
    $index = refresherEligibilityIndex();
    $index->put($person->id(), $value);
    $index->cache();
}

/**
 * Persist a person who links the location and plant a stale value for them.
 */
function refresherStalePerson(string $slug, EntryInstance $location): EntryInstance
{
    $person = refresherPerson($slug, [$location->id()]);
    refresherPlant($person, false);

    return $person;
}

it('names the index it refreshes', function (): void {
    expect(ProviderEligibilityIndexRefresher::ELIGIBILITY_INDEX)->toBe('clinical_provider_profile_eligible_query');
});

it('refreshes every person who links one of the locations and counts them', function (): void {
    $first = refresherLocation('first');
    $second = refresherLocation('second');
    $untouched = refresherLocation('untouched');

    $a = refresherStalePerson('a', $first);
    $b = refresherStalePerson('b', $second);
    $c = refresherStalePerson('c', $untouched);

    $refreshed = (new ProviderEligibilityIndexRefresher)->refreshPeopleLinking([
        mustBeString($first->id(), 'the first id'),
        mustBeString($second->id(), 'the second id'),
    ]);

    expect($refreshed)->toBe(2)
        ->and(refresherEligibilityIndex()->get($a->id()))->toBeTrue()
        ->and(refresherEligibilityIndex()->get($b->id()))->toBeTrue()
        ->and(refresherEligibilityIndex()->get($c->id()))->toBeFalse();
});

it('counts a person once however many of the locations they link', function (): void {
    $first = refresherLocation('first');
    $second = refresherLocation('second');
    $person = refresherPerson('both', [$first->id(), $second->id()]);
    refresherPlant($person, false);

    $refreshed = (new ProviderEligibilityIndexRefresher)->refreshPeopleLinking([
        mustBeString($first->id(), 'the first id'),
        mustBeString($second->id(), 'the second id'),
    ]);

    expect($refreshed)->toBe(1)
        ->and(refresherEligibilityIndex()->get($person->id()))->toBeTrue();
});

it('reads a stored single ID as well as a stored list', function (): void {
    $location = refresherLocation('single');
    $person = refresherPerson('single-id', $location->id());
    refresherPlant($person, false);

    $refreshed = (new ProviderEligibilityIndexRefresher)->refreshPeopleLinking([mustBeString($location->id(), 'the location id')]);

    expect($refreshed)->toBe(1)
        ->and(refresherEligibilityIndex()->get($person->id()))->toBeTrue();
});

it('skips a stored value that cannot be an entry ID', function (mixed $stored): void {
    $location = refresherLocation('real');
    $person = refresherPerson('odd', $stored);
    refresherPlant($person, true);

    $refreshed = (new ProviderEligibilityIndexRefresher)->refreshPeopleLinking([mustBeString($location->id(), 'the location id')]);

    expect($refreshed)->toBe(0)
        ->and(refresherEligibilityIndex()->get($person->id()))->toBeTrue();
})->with([
    'null' => [null],
    'an integer' => [7],
    'a list of integers' => [[1, 2]],
    'an empty list' => [[]],
]);

it('skips a location ID that no person links', function (): void {
    $location = refresherLocation('linked');
    $person = refresherStalePerson('linked-person', $location);

    $refreshed = (new ProviderEligibilityIndexRefresher)->refreshPeopleLinking(['no-such-location']);

    expect($refreshed)->toBe(0)
        ->and(refresherEligibilityIndex()->get($person->id()))->toBeFalse();
});

it('reads nothing when given no locations', function (): void {
    $location = refresherLocation('linked');
    $person = refresherStalePerson('linked-person', $location);
    $index = refresherEligibilityIndex();

    // An empty list returns before the Stache is touched at all
    Stache::shouldReceive('store')->never();

    expect((new ProviderEligibilityIndexRefresher)->refreshPeopleLinking([]))->toBe(0)
        ->and($index->get($person->id()))->toBeFalse();
});

it('does not match a stored number to a location ID that reads the same', function (): void {
    $person = refresherPerson('numeric', [7]);
    refresherPlant($person, true);

    // array_intersect compares as strings, so only the string filter keeps 7 from naming '7'
    expect((new ProviderEligibilityIndexRefresher)->refreshPeopleLinking(['7']))->toBe(0)
        ->and(refresherEligibilityIndex()->get($person->id()))->toBeTrue();
});
