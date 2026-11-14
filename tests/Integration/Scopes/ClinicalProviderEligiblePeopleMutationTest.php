<?php

declare(strict_types=1);

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Query\Builder;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use UAMSWeb\Aether\People\Scopes\ClinicalProviderEligiblePeople;

uses(MockeryPHPUnitIntegration::class);

covers(ClinicalProviderEligiblePeople::class);

/**
 * Narrow mutate coverage for ClinicalProviderEligiblePeople.
 *
 * @command  vendor/bin/pest tests/Integration/Scopes/ClinicalProviderEligiblePeopleMutationTest.php
 */
/**
 * Apply the scope built over the given doubles to the given query double.
 */
function applyEligibleScope(MockInterface $computed, MockInterface $entries, MockInterface $query): void
{
    new ClinicalProviderEligiblePeople(
        mustBe(ProviderPeopleListingComputedValues::class, $computed, 'the computed values double'),
        mustBe(StacheEntryRepository::class, $entries, 'the entry repository double'),
    )->apply(mustBe(Builder::class, $query, 'the query double'), []);
}

it('skips non-Entry rows from the people collection when resolving eligible ids', function (): void {
    // InstanceOfToTrue on `$person instanceof Entry` would call the predicate on the stdClass
    // and fail: the guard must keep apply() to Entry instances only.
    $eligible = Mockery::mock(Entry::class);
    $eligible->shouldReceive('id')->once()->andReturn('person-ok');

    $computed = Mockery::mock(ProviderPeopleListingComputedValues::class);
    $computed->shouldReceive('computeClinicalProviderProfileEligibleQuery')
        ->once()
        ->with($eligible)
        ->andReturn(true);

    $entries = Mockery::mock(StacheEntryRepository::class);
    $entries->shouldReceive('whereCollection')
        ->once()
        ->with('people')
        ->andReturn(EntryCollection::make([
            new stdClass,
            $eligible,
        ]));

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', ['person-ok'])
        ->andReturnSelf();

    applyEligibleScope($computed, $entries, $query);
});

it('leaves out an eligible person that has no string id, and one that is not eligible', function (): void {
    $noId = Mockery::mock(Entry::class);
    $noId->shouldReceive('id')->andReturn(null);
    $ineligible = Mockery::mock(Entry::class);
    $ineligible->shouldReceive('id')->andReturn('person-no');
    $eligible = Mockery::mock(Entry::class);
    $eligible->shouldReceive('id')->andReturn('person-ok');

    $computed = Mockery::mock(ProviderPeopleListingComputedValues::class);
    $computed->shouldReceive('computeClinicalProviderProfileEligibleQuery')
        ->andReturnUsing(fn (Entry $entry): bool => $entry !== $ineligible);

    $entries = Mockery::mock(StacheEntryRepository::class);
    $entries->shouldReceive('whereCollection')
        ->with('people')
        ->andReturn(EntryCollection::make([$noId, $ineligible, $eligible]));

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', ['person-ok'])
        ->andReturnSelf();

    applyEligibleScope($computed, $entries, $query);
});

it('reads the people collection by its configured handle', function (): void {
    config(['people.collection' => 'staff']);

    $computed = Mockery::mock(ProviderPeopleListingComputedValues::class);
    $entries = Mockery::mock(StacheEntryRepository::class);
    $entries->shouldReceive('whereCollection')
        ->once()
        ->with('staff')
        ->andReturn(EntryCollection::make([]));

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')->once()->with('id', [])->andReturnSelf();

    applyEligibleScope($computed, $entries, $query);
});
