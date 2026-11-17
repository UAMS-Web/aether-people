<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Assert;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use UAMSWeb\Aether\People\Support\PeopleCollection;
use UAMSWeb\Aether\People\UpdateScripts\CarryTraineeTypeOver;

/**
 * The update script that carries each entry's old resident and fellow answers over to the
 * trainee-type field (UAMS-Web/aether-people#58).
 *
 * @command  vendor/bin/pest tests/Integration/UpdateScripts/CarryTraineeTypeOverTest.php
 */

/**
 * Persist an entry without events, in a collection that is made if it is not there.
 *
 * @param  array<string, mixed>  $data
 */
function carryTraineePerson(string $collection, string $id, array $data): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->id($id);
    $entry->slug($id);
    $entry->published(true);
    $entry->data($data);
    $entry->saveQuietly();
}

/**
 * The stored data of an entry, read back from the repository.
 *
 * @return array<array-key, mixed>
 */
function carryTraineeData(string $id): array
{
    // Read the file, not the instance the Stache kept in memory
    Stache::clear();

    $entry = Entry::find($id);
    Assert::assertInstanceOf(Statamic\Entries\Entry::class, $entry);

    /** @var Collection<array-key, mixed> $data */
    $data = $entry->data();

    // The file also records which blueprint the entry uses, which is not what the script handles
    return $data->except('blueprint')->all();
}

/**
 * Run the script as `updates:run` does.
 */
function carryTraineeRun(): void
{
    new CarryTraineeTypeOver('uams-web/aether-people')->update();
}

beforeEach(function (): void {
    isolateStacheAndBlueprintsIn('carry-trainee-type');

    /** @var Collection<string, Statamic\Sites\Site> $allSites */
    $allSites = Site::all();
    $siteHandles = $allSites->map(fn ($site) => $site->handle())->values()->all();

    foreach ([PeopleCollection::handle(), 'organizations'] as $handle) {
        $collection = CollectionFacade::make($handle);
        $collection->title(ucfirst($handle));
        $collection->sites($siteHandles);
        $collection->save();
    }
});

it('carries each people entry over and drops the old fields', function (): void {
    $people = PeopleCollection::handle();
    carryTraineePerson($people, 'resident', ['resident_query' => true, 'fellow_query' => false, 'bio' => 'Kept']);
    carryTraineePerson($people, 'fellow', ['resident_query' => false, 'fellow_query' => true]);
    carryTraineePerson($people, 'neither', ['resident_query' => false, 'fellow_query' => false]);
    carryTraineePerson($people, 'asked-never', ['bio' => 'Untouched']);
    carryTraineePerson($people, 'supplied', ['resident_query' => true, 'workday_supplied' => ['resident_query', 'gender']]);

    carryTraineeRun();

    expect(carryTraineeData('resident'))->toBe(['bio' => 'Kept', 'trainee_type' => 'resident'])
        ->and(carryTraineeData('fellow'))->toBe(['trainee_type' => 'fellow'])
        ->and(carryTraineeData('neither'))->toBe(['trainee_type' => 'none'])
        ->and(carryTraineeData('asked-never'))->toBe(['bio' => 'Untouched'])
        ->and(carryTraineeData('supplied'))->toBe(['workday_supplied' => ['trainee_type', 'gender'], 'trainee_type' => 'resident']);
});

it('leaves an entry in another collection as it is', function (): void {
    carryTraineePerson('organizations', 'unit', ['resident_query' => true]);

    carryTraineeRun();

    expect(carryTraineeData('unit'))->toBe(['resident_query' => true]);
});

it('changes nothing on a second run', function (): void {
    carryTraineePerson(PeopleCollection::handle(), 'resident', ['resident_query' => true, 'fellow_query' => false]);

    carryTraineeRun();
    $first = carryTraineeData('resident');
    carryTraineeRun();

    expect(carryTraineeData('resident'))->toBe($first)
        ->and($first)->toBe(['trainee_type' => 'resident']);
});

it('saves without events, so no listener refreshes anything for an unchanged eligibility', function (): void {
    carryTraineePerson(PeopleCollection::handle(), 'resident', ['resident_query' => true]);

    Event::fake([EntrySaving::class, EntrySaved::class]);

    carryTraineeRun();

    Event::assertNotDispatched(EntrySaving::class);
    Event::assertNotDispatched(EntrySaved::class);
});

it('runs on every update and is registered with Statamic', function (): void {
    $script = new CarryTraineeTypeOver('uams-web/aether-people');

    expect($script->shouldUpdate('1.0.0', '0.9.0'))->toBeTrue()
        ->and(app('statamic.update-scripts')->contains(
            fn (array $registered): bool => $registered['class'] === CarryTraineeTypeOver::class && $registered['package'] === 'uams-web/aether-people'
        ))->toBeTrue();
});
