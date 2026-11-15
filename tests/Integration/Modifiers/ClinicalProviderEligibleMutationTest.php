<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use UAMSWeb\Aether\People\Modifiers\ClinicalProviderEligible;

uses(MockeryPHPUnitIntegration::class);

covers(ClinicalProviderEligible::class);

/**
 * Narrow mutate coverage for ClinicalProviderEligible.
 *
 * @command  vendor/bin/pest tests/Integration/Modifiers/ClinicalProviderEligibleMutationTest.php
 */

/**
 * Keys are entry ids; PHP stores a numeric-string id such as '42' as an int key.
 *
 * @param  array<array-key, array{eligible: bool, searchable: bool}>  $byId
 */
function clinicalProviderEligibleModifier(array $byId): ClinicalProviderEligible
{
    $computed = Mockery::mock(ProviderPeopleListingComputedValues::class);
    $computed->shouldReceive('computeClinicalProviderProfileEligibleQuery')
        ->andReturnUsing(function (Entry $entry) use ($byId): bool {
            $id = mustBeString($entry->id(), 'the entry id');

            return $byId[$id]['eligible'] ?? false;
        });
    $computed->shouldReceive('computeSearchable')
        ->andReturnUsing(function (Entry $entry) use ($byId): bool {
            $id = mustBeString($entry->id(), 'the entry id');

            return $byId[$id]['searchable'] ?? false;
        });

    return new ClinicalProviderEligible(mustBe(ProviderPeopleListingComputedValues::class, $computed, 'the computed values double'));
}

function clinicalProviderEligibleEntry(string $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return mustBe(Entry::class, $entry, 'the entry double');
}

/**
 * The ids of the entries the modifier keeps from a value.
 *
 * Compared as ids so a failing assertion prints strings, not a dump of the entry doubles.
 *
 * @return array<int, mixed>
 */
function clinicalProviderEligibleKept(ClinicalProviderEligible $modifier, mixed $value): array
{
    return array_map(fn (Entry $entry): mixed => $entry->id(), $modifier->index($value, [], []));
}

it('keeps entries that are both eligible and searchable', function (): void {
    $keep = clinicalProviderEligibleEntry('keep');
    $dropEligible = clinicalProviderEligibleEntry('drop-eligible');
    $dropSearchable = clinicalProviderEligibleEntry('drop-search');

    $modifier = clinicalProviderEligibleModifier([
        'keep' => ['eligible' => true, 'searchable' => true],
        'drop-eligible' => ['eligible' => false, 'searchable' => true],
        'drop-search' => ['eligible' => true, 'searchable' => false],
    ]);

    // Non-entry first: ContinueToBreak would stop before $keep.
    expect(clinicalProviderEligibleKept($modifier, [null, $keep, $dropEligible, $dropSearchable]))->toBe(['keep']);
});

it('resolves string and int ids through EntryFacade before filtering', function (): void {
    $keep = clinicalProviderEligibleEntry('keep');
    $fromInt = clinicalProviderEligibleEntry('42');
    EntryFacade::shouldReceive('find')->with('keep')->andReturn($keep);
    // RemoveStringCast would pass int 42; require the string cast explicitly.
    EntryFacade::shouldReceive('find')
        ->once()
        ->withArgs(static fn (mixed $id): bool => $id === '42')
        ->andReturn($fromInt);

    $modifier = clinicalProviderEligibleModifier([
        'keep' => ['eligible' => true, 'searchable' => true],
        '42' => ['eligible' => true, 'searchable' => true],
    ]);

    expect(clinicalProviderEligibleKept($modifier, ['keep', 42, null, ['not-scalar']]))->toBe(['keep', '42']);
});

it('returns an empty list when an id does not resolve', function (): void {
    EntryFacade::shouldReceive('find')->with('bogus')->andReturnNull();

    $modifier = clinicalProviderEligibleModifier([]);

    expect(clinicalProviderEligibleKept($modifier, ['bogus']))->toBe([]);
});

it('resolves a lazy query builder before filtering', function (): void {
    $keep = clinicalProviderEligibleEntry('keep');
    $drop = clinicalProviderEligibleEntry('drop');
    $builder = Mockery::mock(EntryQueryBuilder::class);
    $builder->shouldReceive('get')->andReturn(new Collection([$keep, $drop]));

    $modifier = clinicalProviderEligibleModifier([
        'keep' => ['eligible' => true, 'searchable' => true],
        'drop' => ['eligible' => false, 'searchable' => true],
    ]);

    expect(clinicalProviderEligibleKept($modifier, $builder))->toBe(['keep']);
});
