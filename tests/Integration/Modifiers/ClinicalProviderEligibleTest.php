<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;
use UAMSWeb\Aether\People\Modifiers\ClinicalProviderEligible;

uses(MockeryPHPUnitIntegration::class);

covers(ClinicalProviderEligible::class);

/**
 * Coverage for the `clinical_provider_eligible` Antlers modifier: the shared output filter for
 * authored provider relationships (`physician_locations`, `clinical_providers`) that render as
 * augmented `entries` lists rather than a `{{ collection:people }}` tag. It keeps only `people`
 * who are eligible for a clinical provider profile AND currently searchable (not actively
 * separated, not hidden).
 *
 * The modifier runs over the container's real eligibility and searchable predicates. People are
 * in-memory entry doubles, so nothing is written to the shared test content directory; the
 * Entry facade answers for a published location and for each person id. The host keeps the cases
 * that render its own partials.
 *
 * @command  vendor/bin/pest tests/Integration/Modifiers/ClinicalProviderEligibleTest.php
 */
beforeEach(function (): void {
    // Resolve the one published location and every person added through eligibleModifierPerson()
    eligibleModifierRegistry()->exchangeArray([]);

    EntryFacade::partialMock()->shouldReceive('find')->zeroOrMoreTimes()->andReturnUsing(
        function (string $id): ?Entry {
            if ($id === 'loc-published') {
                $location = Mockery::mock(Entry::class);
                $location->shouldReceive('status')->andReturn('published');

                return mustBe(Entry::class, $location, 'the location double');
            }

            $found = eligibleModifierRegistry()[$id] ?? null;

            return $found instanceof Entry ? $found : null;
        }
    );
});

/**
 * The person doubles the Entry facade resolves by id, for the running test.
 *
 * @return ArrayObject<string, Entry>
 */
function eligibleModifierRegistry(): ArrayObject
{
    /** @var ArrayObject<string, Entry>|null $registry */
    static $registry = null;

    return $registry ??= new ArrayObject;
}

/**
 * A people entry double with the base fields that make it eligible, plus the overrides.
 *
 * @param  array<string, mixed>  $fields  Field overrides.
 */
function eligibleModifierPerson(string $id, string $title, array $fields = []): Entry
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
        'title' => $title,
        'credentials' => ['md'],
        ...$fields,
    ];

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('status')->andReturn('published');
    $entry->shouldReceive('get')->andReturnUsing(
        fn (string $key, mixed $default = null): mixed => array_key_exists($key, $data) ? $data[$key] : $default
    );

    $person = mustBe(Entry::class, $entry, 'the person double');
    eligibleModifierRegistry()[$id] = $person;

    return $person;
}

/**
 * The separation fields that make a provider actively separated today.
 *
 * @return array<string, mixed>
 */
function eligibleModifierSeparated(): array
{
    return [
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ];
}

/**
 * The ids the modifier keeps from a value.
 *
 * @return array<int, mixed>
 */
function eligibleModifierIds(mixed $value): array
{
    return array_map(
        fn (Entry $entry): mixed => $entry->id(),
        app(ClinicalProviderEligible::class)->index($value, [], []),
    );
}

it('keeps an eligible, searchable provider', function (): void {
    $mccoy = eligibleModifierPerson('mccoy', 'Leonard McCoy');

    expect(eligibleModifierIds([$mccoy]))->toBe(['mccoy']);
});

it('drops a person who fails clinical-provider eligibility (a resident)', function (): void {
    $chekov = eligibleModifierPerson('chekov', 'Pavel Chekov', ['resident_query' => true]);

    expect(eligibleModifierIds([$chekov]))->toBe([]);
});

it('drops an eligible provider who is actively separated (not searchable)', function (): void {
    $sulu = eligibleModifierPerson('sulu', 'Hikaru Sulu', eligibleModifierSeparated());

    expect(eligibleModifierIds([$sulu]))->toBe([]);
});

it('drops a person hidden from the provider list (not searchable)', function (): void {
    $uhura = eligibleModifierPerson('uhura', 'Nyota Uhura', ['hidden_from_provider_list_query' => true]);

    expect(eligibleModifierIds([$uhura]))->toBe([]);
});

it('keeps only the eligible, searchable providers from a mixed population, in order', function (): void {
    $mccoy = eligibleModifierPerson('mccoy', 'Leonard McCoy');
    $chekov = eligibleModifierPerson('chekov', 'Pavel Chekov', ['resident_query' => true]);
    $sulu = eligibleModifierPerson('sulu', 'Hikaru Sulu', eligibleModifierSeparated());
    $scott = eligibleModifierPerson('scott', 'Montgomery Scott');

    expect(eligibleModifierIds([$mccoy, $chekov, $sulu, $scott]))->toBe(['mccoy', 'scott']);
});

it('drops non-entry items without error', function (): void {
    $mccoy = eligibleModifierPerson('mccoy', 'Leonard McCoy');

    // A stray non-entry value (a dangling id or malformed relationship item) is skipped, not fatal
    expect(eligibleModifierIds([$mccoy, 'not-an-entry-id', null]))->toBe(['mccoy']);
});

it('filters a resolved entry collection to eligible providers', function (): void {
    $mccoy = eligibleModifierPerson('mccoy', 'Leonard McCoy');
    $chekov = eligibleModifierPerson('chekov', 'Pavel Chekov', ['resident_query' => true]);

    expect(eligibleModifierIds(new Collection([$mccoy, $chekov])))->toBe(['mccoy']);
});

it('resolves an unresolved query builder input (the lazy augmented relationship shape)', function (): void {
    // A live `entries` relationship augments to an UNRESOLVED query builder; call the modifier
    // directly so Antlers does not resolve it first
    $mccoy = eligibleModifierPerson('mccoy', 'Leonard McCoy');
    $chekov = eligibleModifierPerson('chekov', 'Pavel Chekov', ['resident_query' => true]);
    $builder = Mockery::mock(EntryQueryBuilder::class);
    $builder->shouldReceive('get')->andReturn(new Collection([$mccoy, $chekov]));

    expect(eligibleModifierIds($builder))->toBe(['mccoy']);
});

it('resolves related ids to entries and keeps only the eligible provider', function (): void {
    // Some render contexts pass the relationship as bare entry ids
    eligibleModifierPerson('mccoy', 'Leonard McCoy');
    eligibleModifierPerson('chekov', 'Pavel Chekov', ['resident_query' => true]);

    expect(eligibleModifierIds(['mccoy', 'chekov']))->toBe(['mccoy']);
});

it('is registered as the clinical_provider_eligible Antlers modifier', function (): void {
    $mccoy = eligibleModifierPerson('mccoy', 'Leonard McCoy');
    $chekov = eligibleModifierPerson('chekov', 'Pavel Chekov', ['resident_query' => true]);

    $out = (string) Antlers::parse('{{ list | clinical_provider_eligible | count }}', ['list' => [$mccoy, $chekov]], true);

    expect(trim($out))->toBe('1');
});
