<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use UAMSWeb\Aether\People\Contracts\LocationRegions;
use UAMSWeb\Aether\People\Support\ClinicalProviderProfileEligibility;

uses(MockeryPHPUnitIntegration::class);

covers(ProviderPeopleListingComputedValues::class);

/**
 * Regression tests for the people/providers `searchable` computed and its separation inputs, and the
 * related-entry aggregates.
 *
 * Guards the UAMS-Web/uams-statamic-archive#802 `separated` -> `clinical_separated_query` handle rename (the
 * computed reads were initially missed) and the `searchable` dependency on `hidden_from_provider_list_query`.
 *
 * @command vendor/bin/pest tests/Integration/ComputedFields/ProviderPeopleListingComputedValuesTest.php
 */

/**
 * Build a mocked entry whose get() resolves from $data honoring defaults.
 *
 * @param  array<mixed>  $data  Field handle => stored value
 */
function providerPeopleListingSearchableMockEntry(array $data, string $status = 'published'): Entry
{
    $entry = Mockery::mock(Entry::class);

    $entry->shouldReceive('get')->andReturnUsing(
        fn (string $key, $default = null) => $data[$key] ?? $default
    );

    $entry->shouldReceive('status')->andReturn($status);

    return mustBe(Entry::class, $entry, 'the entry double');
}

/**
 * The service as the container builds it.
 */
function providerPeopleListingSearchableService(): ProviderPeopleListingComputedValues
{
    return app(ProviderPeopleListingComputedValues::class);
}

/**
 * A location-regions service that answers from each location's `resolved_regions` field, so a test can
 * tell what the service resolved from what the location stored itself.
 */
function providerPeopleListingRegionsFromResolvedField(): LocationRegions
{
    return new class implements LocationRegions
    {
        public function resolveRegions(Entry $location): mixed
        {
            return $location->get('resolved_regions');
        }
    };
}

/**
 * A service over a mocked entry repository, with a location-regions fake that reads `resolved_regions`.
 */
function providerPeopleListingServiceOver(MockInterface $repository): ProviderPeopleListingComputedValues
{
    return new ProviderPeopleListingComputedValues(
        new ClinicalProviderProfileEligibility,
        mustBe(StacheEntryRepository::class, $repository, 'the repository double'),
        providerPeopleListingRegionsFromResolvedField()
    );
}

/**
 * A related-entry double in the given collection with the given field values.
 *
 * @param  array<string, mixed>  $data
 */
function providerPeopleRelated(string $collection, array $data): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);
    $entry->shouldReceive('get')->andReturnUsing(fn (string $key, $default = null) => $data[$key] ?? $default);
    $entry->shouldReceive('offsetGet')->andReturnUsing(fn (string $key) => $data[$key] ?? null);
    $entry->shouldReceive('offsetExists')->andReturnUsing(fn (string $key): bool => array_key_exists($key, $data));

    return mustBe(Entry::class, $entry, 'the entry double');
}

it('is searchable when neither separated nor hidden', function (): void {
    expect(
        providerPeopleListingSearchableService()->computeSearchable(
            providerPeopleListingSearchableMockEntry([])
        )
    )->toBeTrue();
});

it('is not searchable when hidden from provider list', function (): void {
    $entry = providerPeopleListingSearchableMockEntry([
        'hidden_from_provider_list_query' => true,
    ]);

    expect(providerPeopleListingSearchableService()->computeSearchable($entry))->toBeFalse();
});

it('is not searchable during active separation window', function (): void {
    $entry = providerPeopleListingSearchableMockEntry([
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);

    expect(providerPeopleListingSearchableService()->isSeparatedActive($entry))->toBeTrue();
    expect(providerPeopleListingSearchableService()->computeSearchable($entry))->toBeFalse();
});

it('ignores the legacy separated handle in computeds', function (): void {
    // The pre-rename `separated` key must be ignored after the UAMS-Web/uams-statamic-archive#802 rename.
    $entry = providerPeopleListingSearchableMockEntry([
        'separated' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);

    expect(providerPeopleListingSearchableService()->isSeparatedActive($entry))->toBeFalse();
    expect(providerPeopleListingSearchableService()->computeSearchable($entry))->toBeTrue();
});

it('reads the renamed handle in the separated unpublish query', function (): void {
    $entry = providerPeopleListingSearchableMockEntry([
        'clinical_separated_query' => true,
        'separated_end_date' => Carbon::yesterday()->toDateString(),
    ]);

    expect(
        providerPeopleListingSearchableService()->computeSeparatedUnpublishQuery($entry)
    )->toBeTrue();
});

it('reads nothing from locations when the entry links none', function (string $method, mixed $empty): void {
    $repository = Mockery::mock(StacheEntryRepository::class);
    $repository->shouldReceive('whereInId')->never();
    $repository->shouldReceive('find')->never();

    expect(providerPeopleListingServiceOver($repository)->{$method}(providerPeopleListingSearchableMockEntry([])))->toBe($empty);
})->with([
    'brand organizations' => ['computeBrandOrganizations', []],
    'regions' => ['computeRegions', []],
    'portal' => ['computePortal', null],
]);

it('collects distinct non-empty values from the linked locations, skipping non-entries', function (string $method, string $field): void {
    $repository = Mockery::mock(StacheEntryRepository::class);
    $repository->shouldReceive('whereInId')->once()->with(['l1', 'l2', 'l3', 'l4', 'l5'])->andReturn(EntryCollection::make([
        providerPeopleRelated('clinical_locations', [$field => 'A']),
        'not an entry',
        providerPeopleRelated('clinical_locations', [$field => 'A']),
        providerPeopleRelated('clinical_locations', []),
        providerPeopleRelated('clinical_locations', [$field => 'B']),
    ]));

    expect(providerPeopleListingServiceOver($repository)->{$method}(providerPeopleListingSearchableMockEntry(['clinical_locations' => ['l1', 'l2', 'l3', 'l4', 'l5']])))
        ->toBe(['A', 'B']);
})->with([
    'brand organizations' => ['computeBrandOrganizations', 'brand_organization'],
    'regions' => ['computeRegions', 'resolved_regions'],
]);

it('takes a location region from the location-regions service, not from the stored field', function (): void {
    // The service, not the stored field, decides a location's region (UAMS-Web/uams-statamic-archive#2608): a
    // location inside a facility takes the facility's, whatever it stored itself.
    $repository = Mockery::mock(StacheEntryRepository::class);
    $repository->shouldReceive('whereInId')->once()->with(['contained'])->andReturn(EntryCollection::make([
        providerPeopleRelated('clinical_locations', ['resolved_regions' => 'central-arkansas', 'regions' => 'STORED-REGION']),
    ]));

    expect(providerPeopleListingServiceOver($repository)->computeRegions(providerPeopleListingSearchableMockEntry(['clinical_locations' => ['contained']])))
        ->toBe(['central-arkansas']);
});

it('reads the portal from the first linked location only', function (): void {
    $repository = Mockery::mock(StacheEntryRepository::class);
    $repository->shouldReceive('find')->once()->with('first')->andReturn(providerPeopleRelated('clinical_locations', ['portals' => 'mychart']));

    expect(providerPeopleListingServiceOver($repository)->computePortal(providerPeopleListingSearchableMockEntry(['clinical_locations' => ['first', 'second']])))
        ->toBe('mychart');
});

it('reads no portal when the first linked location does not resolve', function (): void {
    $repository = Mockery::mock(StacheEntryRepository::class);
    $repository->shouldReceive('find')->once()->with('gone')->andReturnNull();

    expect(providerPeopleListingServiceOver($repository)->computePortal(providerPeopleListingSearchableMockEntry(['clinical_locations' => ['gone']])))->toBeNull();
});

it('joins the distinct titles of each relation from its own collection only', function (): void {
    $repository = Mockery::mock(StacheEntryRepository::class);
    $repository->shouldReceive('whereInId')->with(['e1', 'e2', 'e3'])->andReturn(EntryCollection::make([
        'not an entry',
        providerPeopleRelated('clinical_expertise', ['title' => 'Cardiology']),
        providerPeopleRelated('clinical_conditions', ['title' => 'Wrong collection']),
        providerPeopleRelated('clinical_expertise', ['title' => '']),
    ]));
    $repository->shouldReceive('whereInId')->with(['c1', 'c2'])->andReturn(EntryCollection::make([
        providerPeopleRelated('clinical_conditions', ['title' => 'Arrhythmia']),
        'not an entry',
    ]));
    $repository->shouldReceive('whereInId')->with(['t1', 't2'])->andReturn(EntryCollection::make([
        'not an entry',
        providerPeopleRelated('clinical_services', ['title' => 'Ablation']),
        providerPeopleRelated('clinical_services', ['title' => 'Cardiology']),
    ]));

    $entry = providerPeopleListingSearchableMockEntry(['expertise' => ['e1', 'e2', 'e3'], 'conditions' => ['c1', 'c2'], 'treatments' => ['t1', 't2']]);

    expect(providerPeopleListingServiceOver($repository)->computeAspFilter($entry))->toBe('Cardiology; Arrhythmia; Ablation');
});

it('builds an empty filter when the entry has no relations', function (): void {
    $repository = Mockery::mock(StacheEntryRepository::class);
    $repository->shouldReceive('whereInId')->never();

    expect(providerPeopleListingServiceOver($repository)->computeAspFilter(providerPeopleListingSearchableMockEntry([])))->toBe('');
});

it('builds the filter from whichever relations are present', function (string $field, string $collection): void {
    $repository = Mockery::mock(StacheEntryRepository::class);
    $repository->shouldReceive('whereInId')->once()->with(['x'])->andReturn(EntryCollection::make([
        providerPeopleRelated($collection, ['title' => 'Only']),
    ]));

    expect(providerPeopleListingServiceOver($repository)->computeAspFilter(providerPeopleListingSearchableMockEntry([$field => ['x']])))->toBe('Only');
})->with([
    'expertise' => ['expertise', 'clinical_expertise'],
    'conditions' => ['conditions', 'clinical_conditions'],
    'treatments' => ['treatments', 'clinical_services'],
]);

it('flags a separation for unpublishing only once its end date has passed', function (mixed $separated, ?string $end, bool $expected): void {
    Carbon::setTestNow('2026-10-08 12:00:00');

    try {
        $data = ['separated_end_date' => $end];
        if ($separated !== 'omit') {
            $data['clinical_separated_query'] = $separated;
        }

        $entry = providerPeopleListingSearchableMockEntry($data);

        expect(providerPeopleListingSearchableService()->computeSeparatedUnpublishQuery($entry))->toBe($expected);
    } finally {
        Carbon::setTestNow();
    }
})->with([
    'ended yesterday' => [true, '2026-10-07', true],
    'ends today' => [true, '2026-10-08', false],
    'ends tomorrow' => [true, '2026-10-09', false],
    'not separated' => [false, '2026-10-07', false],
    'no separated flag' => ['omit', '2026-10-07', false],
    'no end date' => [true, null, false],
]);

it('reads a separation as active only for a published entry within its dates', function (array $data, string $status, bool $expected): void {
    Carbon::setTestNow('2026-10-08 12:00:00');

    try {
        $entry = providerPeopleListingSearchableMockEntry($data, $status);

        expect(providerPeopleListingSearchableService()->isSeparatedActive($entry))->toBe($expected)
            ->and(providerPeopleListingSearchableService()->computeSearchable($entry))->toBe(! $expected);
    } finally {
        Carbon::setTestNow();
    }
})->with([
    'within' => [['clinical_separated_query' => true, 'separated_start_date' => '2026-10-01', 'separated_end_date' => '2026-10-31'], 'published', true],
    'starts today' => [['clinical_separated_query' => true, 'separated_start_date' => '2026-10-08', 'separated_end_date' => '2026-10-31'], 'published', true],
    'ends today' => [['clinical_separated_query' => true, 'separated_start_date' => '2026-10-01', 'separated_end_date' => '2026-10-08'], 'published', true],
    'starts tomorrow' => [['clinical_separated_query' => true, 'separated_start_date' => '2026-10-09', 'separated_end_date' => '2026-10-31'], 'published', false],
    'ended yesterday' => [['clinical_separated_query' => true, 'separated_start_date' => '2026-10-01', 'separated_end_date' => '2026-10-07'], 'published', false],
    'draft' => [['clinical_separated_query' => true, 'separated_start_date' => '2026-10-01', 'separated_end_date' => '2026-10-31'], 'draft', false],
    'not separated' => [['separated_start_date' => '2026-10-01', 'separated_end_date' => '2026-10-31'], 'published', false],
    'no start date' => [['clinical_separated_query' => true, 'separated_end_date' => '2026-10-31'], 'published', false],
    'no end date' => [['clinical_separated_query' => true, 'separated_start_date' => '2026-10-01'], 'published', false],
]);

it('delegates clinical profile eligibility to the configured rules', function (): void {
    config(['clinical_provider_profile' => ['base' => [], 'paths' => [[['field' => 'x', 'operator' => 'not_empty']]]]]);

    expect(providerPeopleListingSearchableService()->computeClinicalProviderProfileEligibleQuery(providerPeopleListingSearchableMockEntry(['x' => 'y'])))->toBeTrue()
        ->and(providerPeopleListingSearchableService()->computeClinicalProviderProfileEligibleQuery(providerPeopleListingSearchableMockEntry([])))->toBeFalse();
});

it('resolves regions through the contract the container binds', function (): void {
    // The default container binding is the host's service; a test-bound one is what the service calls.
    app()->instance(LocationRegions::class, providerPeopleListingRegionsFromResolvedField());

    $repository = Mockery::mock(StacheEntryRepository::class);
    $repository->shouldReceive('whereInId')->once()->with(['l1'])->andReturn(EntryCollection::make([
        providerPeopleRelated('clinical_locations', ['resolved_regions' => 'ozarks']),
    ]));
    app()->instance(StacheEntryRepository::class, $repository);

    expect(providerPeopleListingSearchableService()->computeRegions(providerPeopleListingSearchableMockEntry(['clinical_locations' => ['l1']])))
        ->toBe(['ozarks']);
});
