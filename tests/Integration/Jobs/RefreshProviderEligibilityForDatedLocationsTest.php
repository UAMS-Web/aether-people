<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event as ScheduleEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Path;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Fields\FieldsetRepository;
use Statamic\Sites\Site as SiteInstance;
use Statamic\Stache\Indexes\Index;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Store;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use UAMSWeb\Aether\People\Jobs\RefreshProviderEligibilityForDatedLocations;
use UAMSWeb\Aether\People\Lenses\Gates\ClinicalProviderLensGate;
use UAMSWeb\Aether\People\ServiceProvider;
use UAMSWeb\Aether\People\Support\ProviderEligibilityIndexRefresher;

/**
 * Tests that a future-dated practice location confers provider eligibility only from its date (UAMS-Web/uams-statamic-archive#3178),
 * and that the job is unique, queued and scheduled as configured.
 *
 * `clinical_locations` is built here as it is configured in content: dated, with future entries private
 * and past entries public. Each case reads the four surfaces a provider appears on. The provider
 * directory and Find-a-Doc provider selection both filter on the people eligibility index; the clinical
 * provider lens and the Find-a-Doc API evaluate eligibility when asked.
 *
 * @command  vendor/bin/pest tests/Integration/Jobs/RefreshProviderEligibilityForDatedLocationsTest.php
 */
covers(RefreshProviderEligibilityForDatedLocations::class, ServiceProvider::class);

beforeEach(function (): void {
    // Read host fieldsets from the stand-ins, as the host would from its own directory
    app(FieldsetRepository::class)->setDirectory(__DIR__.'/../../Fixtures/HostFieldsets');

    // Keep the Stache out of the shared fixtures directory, one directory per test
    $root = storage_path('framework/testing/stache/'.uniqid('dated-locations-', true).'/dev-null');
    Stache::stores()->each(function (Store $store) use ($root): void {
        $store->directory(Path::tidy($root.'/'.$store->key()));
    });
    Stache::clear();

    Carbon::setTestNow(Carbon::parse('2026-10-20 09:00:00'));
    Cache::forget(RefreshProviderEligibilityForDatedLocations::LAST_RUN_KEY);

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    // The host registers the eligibility computed on `people`; the index under test holds its value
    CollectionFacade::computed(
        'people',
        ProviderEligibilityIndexRefresher::ELIGIBILITY_INDEX,
        fn (EntryInstance $entry): bool => app(ProviderPeopleListingComputedValues::class)
            ->computeClinicalProviderProfileEligibleQuery($entry),
    );

    $locations = CollectionFacade::make('clinical_locations');
    $locations->title('Clinical Locations');
    $locations->dated(true);
    $locations->futureDateBehavior('private');
    $locations->pastDateBehavior('public');
    $locations->save();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Persist a practice location with the given date and published flag.
 */
function datedLocation(string $slug, string $date, bool $published = true): EntryInstance
{
    $location = mustBe(EntryInstance::class, Entry::make(), 'a new entry');
    $location->collection('clinical_locations');
    $location->slug($slug);
    $location->date(Carbon::parse($date));
    $location->published($published);
    $location->data(['title' => $slug]);
    $location->saveQuietly();

    return $location;
}

/**
 * Persist a person who passes every rule except, possibly, the practice location one.
 */
function datedLocationPerson(string $slug, EntryInstance $location): EntryInstance
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
        'clinical_locations' => [$location->id()],
    ]);
    $person->saveQuietly();

    return $person;
}

/**
 * The people store's eligibility index.
 */
function datedLocationEligibilityIndex(): Index
{
    $entries = mustBe(AggregateStore::class, Stache::store('entries'), 'the entries store');
    $people = mustBe(Store::class, $entries->store('people'), 'the people store');

    return mustBe(Index::class, $people->index(ProviderEligibilityIndexRefresher::ELIGIBILITY_INDEX), 'the eligibility index');
}

/**
 * Which of the three provider surfaces list the person.
 *
 * @return array{directory: bool, lens: bool, find_a_doc_selection: bool}
 */
function datedLocationSurfaces(EntryInstance $person): array
{
    // The directory and the Find-a-Doc providers block both filter on the eligibility index
    $query = mustBe(EntryQueryBuilder::class, Entry::query(), 'the entry query');
    $query->where('collection', 'people');
    $query->where(ProviderEligibilityIndexRefresher::ELIGIBILITY_INDEX, true);

    /** @var Collection<int, EntryInstance> $eligible */
    $eligible = $query->get();
    $indexed = $eligible->contains(fn (EntryInstance $entry): bool => $entry->id() === $person->id());

    $id = mustBeString($person->id(), 'the person id');
    $fresh = mustBe(EntryInstance::class, Entry::find($id), 'the stored person');
    $site = mustBe(SiteInstance::class, Site::default(), 'the default site');

    return [
        'directory' => $indexed,
        'lens' => app(ClinicalProviderLensGate::class)->passes($fresh, $site),
        'find_a_doc_selection' => $indexed,
    ];
}

/**
 * Run the job as the queue would.
 */
function runDatedLocationRefresh(): void
{
    app()->call([new RefreshProviderEligibilityForDatedLocations, 'handle']);
}

it('keeps a provider whose only location is scheduled off every surface', function (): void {
    $location = datedLocation('opening-soon', '2026-10-25');
    $person = datedLocationPerson('beverly-crusher', $location);

    $stored = mustBe(EntryInstance::class, Entry::find($location->id()), 'the stored location');

    expect($stored->status())->toBe('scheduled')
        ->and(datedLocationSurfaces($person))->toBe([
            'directory' => false,
            'lens' => false,
            'find_a_doc_selection' => false,
        ]);
});

it('lists the provider everywhere once the date passes and the refresh runs, with no save', function (): void {
    $location = datedLocation('opening-soon', '2026-10-25');
    $person = datedLocationPerson('beverly-crusher', $location);

    // A run before the date records its time and changes nothing
    runDatedLocationRefresh();
    expect(datedLocationSurfaces($person)['directory'])->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00'));

    // The index is stale until the refresh: nothing was saved when the date arrived
    expect(datedLocationSurfaces($person)['directory'])->toBeFalse();

    runDatedLocationRefresh();

    expect(datedLocationSurfaces($person))->toBe([
        'directory' => true,
        'lens' => true,
        'find_a_doc_selection' => true,
    ]);
});

it('keeps a provider with a published, past-dated location on every surface', function (): void {
    $location = datedLocation('open-already', '2026-01-05');
    $person = datedLocationPerson('jane-doe', $location);

    expect(datedLocationSurfaces($person))->toBe([
        'directory' => true,
        'lens' => true,
        'find_a_doc_selection' => true,
    ]);
});

it('keeps a provider whose only location is a draft off every surface, before and after its date', function (): void {
    $location = datedLocation('never-opened', '2026-10-25', published: false);
    $person = datedLocationPerson('wesley-crusher', $location);

    runDatedLocationRefresh();
    Carbon::setTestNow(Carbon::parse('2026-10-26 09:00:00'));
    runDatedLocationRefresh();

    expect(datedLocationSurfaces($person))->toBe([
        'directory' => false,
        'lens' => false,
        'find_a_doc_selection' => false,
    ]);
});

it('re-indexes only the people linked to locations whose date passed since the last run', function (): void {
    $older = datedLocation('open-already', '2026-01-05');
    $passing = datedLocation('opening-soon', '2026-10-25');
    $unchanged = datedLocationPerson('data', $older);
    $newlyEligible = datedLocationPerson('john-smith', $passing);

    runDatedLocationRefresh();

    // Values only a refresh would replace: the planted false on a person whose location
    // passed before the last run must survive the next one
    $index = datedLocationEligibilityIndex();
    $index->put($unchanged->id(), false);
    $index->cache();

    Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00'));
    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($unchanged->id()))->toBeFalse()
        ->and(datedLocationEligibilityIndex()->get($newlyEligible->id()))->toBeTrue();
});

it('treats every past-dated location as passed when no run is recorded', function (): void {
    $older = datedLocation('open-already', '2026-01-05');
    $person = datedLocationPerson('data', $older);

    $index = datedLocationEligibilityIndex();
    $index->put($person->id(), false);
    $index->cache();

    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($person->id()))->toBeTrue()
        ->and(Cache::get(RefreshProviderEligibilityForDatedLocations::LAST_RUN_KEY))
        ->toBe(Carbon::parse('2026-10-20 09:00:00')->getTimestamp());
});

it('leaves a location dated after now for a later run', function (): void {
    $passing = datedLocation('opening-soon', '2026-10-25');
    $person = datedLocationPerson('john-smith', $passing);

    $index = datedLocationEligibilityIndex();
    $index->put($person->id(), true);
    $index->cache();

    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($person->id()))->toBeTrue();
});

it('treats a date equal to now as passed', function (): void {
    $location = datedLocation('opens-now', '2026-10-20 09:00:00');
    $person = datedLocationPerson('data', $location);

    $index = datedLocationEligibilityIndex();
    $index->put($person->id(), false);
    $index->cache();

    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($person->id()))->toBeTrue();
});

it('treats a date equal to the last run as already handled', function (): void {
    $location = datedLocation('opened-at-last-run', '2026-10-20 08:00:00');
    $person = datedLocationPerson('data', $location);

    Cache::forever(
        RefreshProviderEligibilityForDatedLocations::LAST_RUN_KEY,
        Carbon::parse('2026-10-20 08:00:00')->getTimestamp(),
    );

    $index = datedLocationEligibilityIndex();
    $index->put($person->id(), false);
    $index->cache();

    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($person->id()))->toBeFalse();
});

it('re-indexes a date that passed after the last run', function (): void {
    $location = datedLocation('opened-after-last-run', '2026-10-20 08:30:00');
    $person = datedLocationPerson('data', $location);

    Cache::forever(
        RefreshProviderEligibilityForDatedLocations::LAST_RUN_KEY,
        Carbon::parse('2026-10-20 08:00:00')->getTimestamp(),
    );

    $index = datedLocationEligibilityIndex();
    $index->put($person->id(), false);
    $index->cache();

    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($person->id()))->toBeTrue();
});

it('ignores a last run time that is not an integer', function (): void {
    $location = datedLocation('open-already', '2026-01-05');
    $person = datedLocationPerson('data', $location);

    Cache::forever(RefreshProviderEligibilityForDatedLocations::LAST_RUN_KEY, '2030-01-01');

    $index = datedLocationEligibilityIndex();
    $index->put($person->id(), false);
    $index->cache();

    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($person->id()))->toBeTrue();
});

it('has no date to pass in an undated collection', function (): void {
    $locations = mustBe(Statamic\Entries\Collection::class, CollectionFacade::findByHandle('clinical_locations'), 'the locations collection');
    $locations->dated(false);
    $locations->save();

    // An undated entry reads no date, so there is none to pass, however long ago it was saved
    Carbon::setTestNow(Carbon::parse('2040-01-01 00:00:00'));
    $location = mustBe(EntryInstance::class, Entry::make(), 'a new entry');
    $location->collection('clinical_locations');
    $location->slug('undated');
    $location->published(true);
    $location->data(['title' => 'undated']);
    $location->saveQuietly();

    $person = datedLocationPerson('data', $location);

    $index = datedLocationEligibilityIndex();
    $index->put($person->id(), false);
    $index->cache();

    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($person->id()))->toBeFalse();
});

it('reads only the clinical_locations collection', function (): void {
    $events = CollectionFacade::make('events');
    $events->title('Events');
    $events->dated(true);
    $events->save();

    // A person can link any entry, but only a practice location's date confers eligibility
    $event = mustBe(EntryInstance::class, Entry::make(), 'a new entry');
    $event->collection('events');
    $event->slug('rounds');
    $event->date(Carbon::parse('2026-01-05'));
    $event->published(true);
    $event->data(['title' => 'rounds']);
    $event->saveQuietly();

    $person = datedLocationPerson('data', $event);

    $index = datedLocationEligibilityIndex();
    $index->put($person->id(), false);
    $index->cache();

    runDatedLocationRefresh();

    expect(datedLocationEligibilityIndex()->get($person->id()))->toBeFalse();
});

it('skips a location that is not published when reading what passed', function (): void {
    $draft = datedLocation('draft', '2026-01-05', published: false);
    $person = datedLocationPerson('data', $draft);

    $index = datedLocationEligibilityIndex();
    $index->put($person->id(), true);
    $index->cache();

    runDatedLocationRefresh();

    // A draft is not read, so its linked person is not re-indexed and keeps the planted value
    expect(datedLocationEligibilityIndex()->get($person->id()))->toBeTrue();
});

it('records the time of the run, whatever it refreshed', function (): void {
    runDatedLocationRefresh();

    expect(Cache::get(RefreshProviderEligibilityForDatedLocations::LAST_RUN_KEY))
        ->toBe(Carbon::parse('2026-10-20 09:00:00')->getTimestamp());
});

it('logs how many locations and people it refreshed and the window it read', function (): void {
    $location = datedLocation('open-already', '2026-01-05');
    datedLocationPerson('data', $location);
    datedLocationPerson('john-smith', $location);
    Cache::forever(
        RefreshProviderEligibilityForDatedLocations::LAST_RUN_KEY,
        Carbon::parse('2026-01-01 00:00:00')->getTimestamp(),
    );

    Log::shouldReceive('info')
        ->once()
        ->with('Refreshed provider eligibility for dated locations.', [
            'locations' => 1,
            'people' => 2,
            'since' => Carbon::parse('2026-01-01 00:00:00')->toIso8601String(),
            'now' => Carbon::parse('2026-10-20 09:00:00')->toIso8601String(),
        ]);

    runDatedLocationRefresh();
});

it('logs no window start when no run is recorded', function (): void {
    Log::shouldReceive('info')
        ->once()
        ->with('Refreshed provider eligibility for dated locations.', [
            'locations' => 0,
            'people' => 0,
            'since' => null,
            'now' => Carbon::parse('2026-10-20 09:00:00')->toIso8601String(),
        ]);

    runDatedLocationRefresh();
});

it('is a queued job that stays unique', function (): void {
    expect(class_implements(RefreshProviderEligibilityForDatedLocations::class))
        ->toContain(ShouldQueue::class, ShouldBeUnique::class)
        ->and(RefreshProviderEligibilityForDatedLocations::LAST_RUN_KEY)
        ->toBe('clinical_provider_profile:location_date_refresh:last_run');
});

describe('unique lock', function (): void {
    beforeEach(function (): void {
        // The array store keeps the lock inside this process, so a parallel worker cannot see it
        Config::set('cache.default', 'array');
        Cache::setDefaultDriver('array');
        Queue::fake();
    });

    /**
     * Read the seconds the job's `UniqueFor` attribute gives its lock.
     */
    function datedLocationLockSeconds(): int
    {
        $attributes = new ReflectionClass(RefreshProviderEligibilityForDatedLocations::class)->getAttributes(UniqueFor::class);

        return $attributes[0]->newInstance()->uniqueFor;
    }

    it('drops a second dispatch while the lock is held', function (): void {
        RefreshProviderEligibilityForDatedLocations::dispatch();

        $this->travel(datedLocationLockSeconds() - 1)->seconds();

        RefreshProviderEligibilityForDatedLocations::dispatch();

        Queue::assertPushed(RefreshProviderEligibilityForDatedLocations::class, 1);
    });

    it('accepts a dispatch once the lock from a lost run has expired', function (): void {
        RefreshProviderEligibilityForDatedLocations::dispatch();

        $this->travel(datedLocationLockSeconds() + 1)->seconds();

        RefreshProviderEligibilityForDatedLocations::dispatch();

        Queue::assertPushed(RefreshProviderEligibilityForDatedLocations::class, 2);
    });

    it('holds the lock for an hour', function (): void {
        expect(datedLocationLockSeconds())->toBe(3600);
    });

    it('outlasts the time the queue gives a run', function (): void {
        expect(datedLocationLockSeconds())->toBeGreaterThan(90);
    });
});

describe('schedule', function (): void {
    /**
     * The scheduled events that run the job, from a schedule the real provider filled in.
     *
     * @return list<ScheduleEvent>
     */
    function datedLocationScheduledEvents(): array
    {
        $schedule = new Schedule;
        // Statamic's provider declares the hook protected, so call it from inside the provider
        (fn () => $this->schedule($schedule))->call(new ServiceProvider(app()));

        return array_values(array_filter(
            $schedule->events(),
            fn (ScheduleEvent $event): bool => $event->description === RefreshProviderEligibilityForDatedLocations::class,
        ));
    }

    it('is on the application schedule at the default cadence once booted', function (): void {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (ScheduleEvent $event): bool => $event->description === RefreshProviderEligibilityForDatedLocations::class,
        ));

        expect($events)->toHaveCount(1)
            ->and($events[0]->expression)->toBe('*/15 * * * *');
    });

    it('runs at the configured cron in the display timezone', function (): void {
        config([
            'clinical_provider_profile.location_date_refresh.enabled' => true,
            'clinical_provider_profile.location_date_refresh.cron' => '5 4 * * 1-5',
            'statamic.system.display_timezone' => 'America/Chicago',
        ]);

        $events = datedLocationScheduledEvents();

        expect($events)->toHaveCount(1)
            ->and($events[0]->expression)->toBe('5 4 * * 1-5')
            ->and($events[0]->timezone)->toBe('America/Chicago');
    });

    it('is not scheduled when the refresh is disabled', function (mixed $enabled): void {
        config(['clinical_provider_profile.location_date_refresh.enabled' => $enabled]);

        expect(datedLocationScheduledEvents())->toBe([]);
    })->with([
        'off' => [false],
        'a truthy value that is not true' => [1],
        'unset' => [null],
    ]);

    it('is not scheduled when the provider layer is off', function (): void {
        config([
            'clinical_provider_profile.location_date_refresh.enabled' => true,
            'people.features.provider_layer' => false,
        ]);

        expect(datedLocationScheduledEvents())->toBe([]);
    });

    it('is scheduled when the provider layer switch is absent', function (): void {
        config([
            'clinical_provider_profile.location_date_refresh.enabled' => true,
            'people.features' => [],
        ]);

        expect(datedLocationScheduledEvents())->toHaveCount(1);
    });
});
