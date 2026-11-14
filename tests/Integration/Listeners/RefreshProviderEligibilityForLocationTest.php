<?php

declare(strict_types=1);

use Statamic\Entries\Entry as EntryInstance;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Path;
use Statamic\Facades\Stache;
use Statamic\Fields\FieldsetRepository;
use Statamic\Stache\Indexes\Index;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Store;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use UAMSWeb\Aether\People\Listeners\RefreshProviderEligibilityForLocation;
use UAMSWeb\Aether\People\Support\ProviderEligibilityIndexRefresher;

/**
 * Tests that saving or deleting a practice location re-indexes the eligibility of exactly
 * the people who link it (UAMS-Web/uams-statamic-archive#3123).
 *
 * Each case plants a stale value in the people eligibility index, then saves an entry. Only a
 * refresh can replace the planted value, so the index shows which people the listener reached.
 *
 * @command  vendor/bin/pest tests/Integration/Listeners/RefreshProviderEligibilityForLocationTest.php
 */
covers(RefreshProviderEligibilityForLocation::class);

beforeEach(function (): void {
    // Read host fieldsets from the stand-ins, as the host would from its own directory
    app(FieldsetRepository::class)->setDirectory(__DIR__.'/../../Fixtures/HostFieldsets');

    // Keep the Stache out of the shared fixtures directory, one directory per test
    $root = storage_path('framework/testing/stache/'.uniqid('location-listener-', true).'/dev-null');
    Stache::stores()->each(function (Store $store) use ($root): void {
        $store->directory(Path::tidy($root.'/'.$store->key()));
    });
    Stache::clear();

    foreach (['people', 'pages', 'clinical_locations'] as $handle) {
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
 * Persist a published entry in a collection.
 */
function listenerEntry(string $collection, string $slug): EntryInstance
{
    $entry = mustBe(EntryInstance::class, Entry::make(), 'a new entry');
    $entry->collection($collection);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => $slug]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * The people store's eligibility index.
 */
function listenerEligibilityIndex(): Index
{
    $entries = mustBe(AggregateStore::class, Stache::store('entries'), 'the entries store');
    $people = mustBe(Store::class, $entries->store('people'), 'the people store');

    return mustBe(Index::class, $people->index(ProviderEligibilityIndexRefresher::ELIGIBILITY_INDEX), 'the eligibility index');
}

/**
 * Persist a person who passes every base rule and the credentials path.
 *
 * @param  array<string, mixed>  $fields
 */
function listenerLinkedPerson(string $slug, array $fields = []): EntryInstance
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
        ...$fields,
    ]);
    $person->saveQuietly();

    return $person;
}

/**
 * Overwrite one person's indexed eligibility with a value only a refresh would replace.
 */
function listenerPlantEligibility(EntryInstance $person, bool $value): void
{
    $index = listenerEligibilityIndex();
    $index->put($person->id(), $value);
    $index->cache();
}

it('refreshes a person who links the saved location', function (): void {
    $location = listenerEntry('clinical_locations', 'sickbay-annex');
    $kirk = listenerLinkedPerson('james-kirk', ['clinical_locations' => [$location->id()]]);
    listenerPlantEligibility($kirk, false);

    $location->save();

    expect(listenerEligibilityIndex()->get($kirk->id()))->toBeTrue();
});

it('refreshes a person whose stored location is a single ID rather than a list', function (): void {
    $location = listenerEntry('clinical_locations', 'sickbay-annex');
    $spock = listenerLinkedPerson('spock', ['clinical_locations' => $location->id()]);
    listenerPlantEligibility($spock, false);

    $location->save();

    expect(listenerEligibilityIndex()->get($spock->id()))->toBeTrue();
});

it('leaves a person who does not link the saved location alone', function (): void {
    $linked = listenerEntry('clinical_locations', 'sickbay-annex');
    $other = listenerEntry('clinical_locations', 'other-location');

    $scotty = listenerLinkedPerson('montgomery-scott', ['clinical_locations' => [$linked->id()]]);
    listenerPlantEligibility($scotty, false);

    $other->save();

    expect(listenerEligibilityIndex()->get($scotty->id()))->toBeFalse();
});

it('ignores an entry outside the clinical_locations collection, even one a person links', function (): void {
    $location = listenerEntry('clinical_locations', 'sickbay-annex');
    $page = listenerEntry('pages', 'sickbay');

    $mccoy = listenerLinkedPerson('leonard-mccoy', [
        'clinical_locations' => [$location->id(), $page->id()],
    ]);
    listenerPlantEligibility($mccoy, false);

    $page->save();

    expect(listenerEligibilityIndex()->get($mccoy->id()))->toBeFalse();
});

it('refreshes a person who links a deleted location', function (): void {
    $location = listenerEntry('clinical_locations', 'sickbay-annex');
    $uhura = listenerLinkedPerson('nyota-uhura', ['clinical_locations' => [$location->id()]]);
    listenerPlantEligibility($uhura, true);

    $location->delete();

    expect(listenerEligibilityIndex()->get($uhura->id()))->toBeFalse();
});

it('does nothing for a location that has no ID yet', function (): void {
    $location = listenerEntry('clinical_locations', 'sickbay-annex');
    $sulu = listenerLinkedPerson('hikaru-sulu', ['clinical_locations' => [$location->id()]]);
    listenerPlantEligibility($sulu, false);

    // A location that was never given an ID cannot be named by any person
    $unsaved = mustBe(EntryInstance::class, Entry::make(), 'a new entry');
    $unsaved->collection('clinical_locations');
    $unsaved->id(null);

    app(RefreshProviderEligibilityForLocation::class)->handle(new EntrySaved($unsaved));

    expect(listenerEligibilityIndex()->get($sulu->id()))->toBeFalse();
});

it('handles both the saved and the deleted event', function (): void {
    $location = listenerEntry('clinical_locations', 'sickbay-annex');
    $chekov = listenerLinkedPerson('pavel-chekov', ['clinical_locations' => [$location->id()]]);
    $listener = app(RefreshProviderEligibilityForLocation::class);

    listenerPlantEligibility($chekov, false);
    $listener->handle(new EntrySaved($location));
    expect(listenerEligibilityIndex()->get($chekov->id()))->toBeTrue();

    listenerPlantEligibility($chekov, false);
    $listener->handle(new EntryDeleted($location));
    expect(listenerEligibilityIndex()->get($chekov->id()))->toBeTrue();
});
