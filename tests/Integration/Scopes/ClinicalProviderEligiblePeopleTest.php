<?php

declare(strict_types=1);

use Carbon\Carbon;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use UAMSWeb\Aether\People\Scopes\ClinicalProviderEligiblePeople;

uses(MockeryPHPUnitIntegration::class);

covers(ClinicalProviderEligiblePeople::class);

/**
 * Coverage for the `clinical_provider_eligible_people` query scope: the CP selection-surface
 * complement to the `clinical_provider_eligible` output filter. It constrains a `people` picker
 * to people eligible for a UAMS Health clinical provider profile, reusing the predicate the
 * output filter applies so the picker and the live directories agree on who is a provider.
 *
 * The scope runs over the container's real eligibility predicate. People are in-memory entry
 * doubles served by a stubbed entry repository, so nothing is written to the shared test content
 * directory; the Entry facade answers for a published location.
 *
 * @command  vendor/bin/pest tests/Integration/Scopes/ClinicalProviderEligiblePeopleTest.php
 */
beforeEach(function (): void {
    // Resolve the one published location the eligible people link to
    EntryFacade::partialMock()->shouldReceive('find')->zeroOrMoreTimes()->andReturnUsing(
        function (string $id): ?Entry {
            if ($id !== 'loc-published') {
                return null;
            }

            $location = Mockery::mock(Entry::class);
            $location->shouldReceive('status')->andReturn('published');

            return mustBe(Entry::class, $location, 'the location double');
        }
    );
});

/**
 * A people entry double that is eligible by default; overrides make it ineligible.
 *
 * @param  array<string, mixed>  $fields  Field overrides.
 */
function makeScopePerson(string $id, array $fields = []): Entry
{
    $data = [
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_employee_subtype' => 'regular',
        'resident_query' => false,
        'fellow_query' => false,
        'clinical_role_query' => true,
        'direct_patient_care_query' => true,
        'clinical_locations' => ['loc-published'],
        'credentials' => ['md'],
        ...$fields,
    ];

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('status')->andReturn('published');
    $entry->shouldReceive('get')->andReturnUsing(
        fn (string $key, mixed $default = null): mixed => array_key_exists($key, $data) ? $data[$key] : $default
    );

    return mustBe(Entry::class, $entry, 'the person double');
}

/**
 * Apply the scope over the given people and return the ids it constrains the query to.
 *
 * @param  list<Entry>  $people  The `people` collection's entries.
 * @return array<array-key, mixed>
 */
function scopedPeopleIds(array $people): array
{
    $entries = Mockery::mock(StacheEntryRepository::class);
    $entries->shouldReceive('whereCollection')->with('people')->andReturn(new EntryCollection($people));

    $constrained = null;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('id', Mockery::on(function (mixed $ids) use (&$constrained): bool {
            $constrained = $ids;

            return true;
        }))
        ->andReturnSelf();

    new ClinicalProviderEligiblePeople(
        app(ProviderPeopleListingComputedValues::class),
        mustBe(StacheEntryRepository::class, $entries, 'the entry repository double'),
    )->apply(mustBe(Builder::class, $query, 'the query double'), []);

    return is_array($constrained) ? $constrained : [];
}

it('offers only clinical-provider-eligible people in the picker query', function (): void {
    $people = [makeScopePerson('mccoy'), makeScopePerson('chekov', ['resident_query' => true])];

    expect(scopedPeopleIds($people))->toBe(['mccoy']);
});

it('keeps only the eligible people from a mixed population', function (): void {
    $people = [
        makeScopePerson('mccoy'),
        makeScopePerson('chekov', ['resident_query' => true]),
        makeScopePerson('crusher', ['fellow_query' => true]),
        makeScopePerson('scott'),
    ];

    expect(scopedPeopleIds($people))->toBe(['mccoy', 'scott']);
});

it('still offers an actively-separated eligible provider (eligibility only, not searchable)', function (): void {
    // The picker filters on eligibility only; the output filter, not this scope, hides separated
    // and hidden providers from the public render, so editors can still manage associations for
    // a temporarily separated provider.
    $sulu = makeScopePerson('sulu', [
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);

    expect(scopedPeopleIds([$sulu]))->toBe(['sulu']);
});

it('constrains the query to nothing when no person is eligible', function (): void {
    // An empty eligible set is still passed to `whereIn`, so the picker is constrained to
    // nothing rather than left unconstrained
    $people = [makeScopePerson('chekov', ['resident_query' => true]), makeScopePerson('crusher', ['fellow_query' => true])];

    expect(scopedPeopleIds($people))->toBe([]);
});

it('registers under its handle', function (): void {
    // Statamic discovers the scope from the add-on's Scopes directory under its snake_case handle
    expect(app('statamic.scopes')->get('clinical_provider_eligible_people'))
        ->toBe(ClinicalProviderEligiblePeople::class);
});
