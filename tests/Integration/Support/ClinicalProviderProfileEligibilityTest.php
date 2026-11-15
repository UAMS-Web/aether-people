<?php

declare(strict_types=1);

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\FieldsetRepository;
use UAMSWeb\Aether\People\Support\ClinicalProviderProfileEligibility;

uses(MockeryPHPUnitIntegration::class);

covers(ClinicalProviderProfileEligibility::class);

/**
 * Resolve location IDs through the Entry facade without touching the Stache: `loc-published`
 * has the published status, `loc-draft` is a draft, `loc-scheduled` is published with a future
 * date in a collection that keeps future entries private, and any other ID names no entry.
 */
beforeEach(function (): void {
    $statuses = ['loc-published' => 'published', 'loc-draft' => 'draft', 'loc-scheduled' => 'scheduled'];

    EntryFacade::partialMock()->shouldReceive('find')->zeroOrMoreTimes()->andReturnUsing(
        function (string $id) use ($statuses): ?Entry {
            if (! isset($statuses[$id])) {
                return null;
            }

            // A scheduled entry is published but private, so its flag alone would count it
            $location = Mockery::mock(Entry::class);
            $location->shouldReceive('published')->andReturn($statuses[$id] !== 'draft');
            $location->shouldReceive('status')->andReturn($statuses[$id]);

            return mustBe(Entry::class, $location, 'the location double');
        }
    );
});

/**
 * Minimum field set where every base rule passes for Path A/B tests.
 *
 * @return array<string, mixed>
 */
function clinicalProviderBasePassFields(): array
{
    return [
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_employee_subtype' => 'regular',
        'resident_query' => false,
        'fellow_query' => false,
        'clinical_role_query' => true,
        'direct_patient_care_query' => true,
        'clinical_profile_disable_query' => false,
        'clinical_locations' => ['loc-published'],
    ];
}

/**
 * Mock entry whose `get` reads from an associative field map.
 *
 * @param  array<string, mixed>  $fields
 */
function clinicalProviderEntryMockWithFields(array $fields): Entry
{
    $entry = Mockery::mock(Entry::class);

    $entry->shouldReceive('get')->zeroOrMoreTimes()->andReturnUsing(
        function (string $key, mixed $default = null) use ($fields) {
            if (array_key_exists($key, $fields)) {
                return $fields[$key];
            }

            return $default;
        }
    );

    return mustBe(Entry::class, $entry, 'the entry double');
}

/**
 * Tests UAMS Health clinical provider profile eligibility evaluation.
 *
 * @command  vendor/bin/pest tests/Integration/Support/ClinicalProviderProfileEligibilityTest.php
 */
it('denies eligibility when a base rule fails', function (): void {
    $fields = clinicalProviderBasePassFields();
    $fields['uams_worker_query'] = false;

    $service = new ClinicalProviderProfileEligibility;
    $entry = clinicalProviderEntryMockWithFields($fields);

    expect($service->qualifies($entry))->toBeFalse();
});

it('allows when base passes and the appointments-and-locations path passes', function (): void {
    $fields = array_merge(clinicalProviderBasePassFields(), [
        'appointments_query' => true,
        'appointments_individual_query' => true,
    ]);

    $service = new ClinicalProviderProfileEligibility;
    $entry = clinicalProviderEntryMockWithFields($fields);

    expect($service->qualifies($entry))->toBeTrue();
});

it('allows when base passes and the clinical-credentials path passes', function (): void {
    $fields = array_merge(clinicalProviderBasePassFields(), [
        'appointments_query' => false,
        'credentials' => ['md'],
    ]);

    $service = new ClinicalProviderProfileEligibility;
    $entry = clinicalProviderEntryMockWithFields($fields);

    expect($service->qualifies($entry))->toBeTrue();
});

it('denies when base passes but no path passes', function (): void {
    $fields = array_merge(clinicalProviderBasePassFields(), [
        'appointments_query' => false,
        'appointments_individual_query' => false,
        'credentials' => [],
    ]);

    $service = new ClinicalProviderProfileEligibility;
    $entry = clinicalProviderEntryMockWithFields($fields);

    expect($service->qualifies($entry))->toBeFalse();
});

/**
 * @param  array<string, mixed>  $path
 * @param  array<string, mixed>  $location
 */
it('qualifies through each path only with a published linked location and no opt-out', function (array $path, array $location, bool $expected): void {
    /** @var array<string, mixed> $fields */
    $fields = array_merge(clinicalProviderBasePassFields(), $path, $location);

    expect((new ClinicalProviderProfileEligibility)->qualifies(clinicalProviderEntryMockWithFields($fields)))->toBe($expected);
})->with([
    'appointments path' => [['appointments_query' => true, 'appointments_individual_query' => true, 'credentials' => []]],
    'credentials path' => [['appointments_query' => false, 'credentials' => ['md']]],
])->with([
    'published location' => [['clinical_locations' => ['loc-published']], true],
    'published location after a draft and a missing one' => [['clinical_locations' => ['loc-draft', 'loc-gone', 'loc-published']], true],
    'published location as a collection' => [['clinical_locations' => collect(['loc-published'])], true],
    'published location as a single id' => [['clinical_locations' => 'loc-published'], true],
    'draft location only' => [['clinical_locations' => ['loc-draft']], false],
    'scheduled location only' => [['clinical_locations' => ['loc-scheduled']], false],
    'published location after a scheduled one' => [['clinical_locations' => ['loc-scheduled', 'loc-published']], true],
    'missing location only' => [['clinical_locations' => ['loc-gone']], false],
    'no location' => [['clinical_locations' => []], false],
    'location field absent' => [['clinical_locations' => null], false],
    'non-string ids only' => [['clinical_locations' => [5, '', ['loc-published']]], false],
    'published after a non-string id' => [['clinical_locations' => [5, 'loc-published']], true],
    'opt-out set with a published location' => [['clinical_profile_disable_query' => true], false],
]);

it('reads no field the person blueprint does not define', function (): void {
    // Read the host's fieldsets from the stand-ins, as the host would from its own directory
    app(FieldsetRepository::class)->setDirectory(__DIR__.'/../../Fixtures/HostFieldsets');

    $blueprint = Blueprint::in('collections/people')->get('person');
    expect($blueprint)->not->toBeNull();

    /** @var Statamic\Fields\Blueprint $blueprint */
    /** @var array{base: list<array{field: string}>, paths: array<string, list<array{field: string}>>} $config */
    $config = config('clinical_provider_profile');
    $rules = [...$config['base'], ...array_merge(...array_values($config['paths']))];

    $undefined = array_values(array_filter(
        array_map(fn (array $rule): string => $rule['field'], $rules),
        fn (string $field): bool => ! $blueprint->hasField($field)
    ));

    expect($rules)->not->toBeEmpty()
        ->and($undefined)->toBe([]);
});

it('matches a hyphenated credential slug case-insensitively in path B', function (): void {
    $fields = array_merge(clinicalProviderBasePassFields(), [
        'appointments_query' => false,
        'credentials' => ['M-D'],
    ]);

    $service = new ClinicalProviderProfileEligibility;
    $entry = clinicalProviderEntryMockWithFields($fields);

    expect($service->qualifies($entry))->toBeTrue();
});

/**
 * Whether an entry with `$fields` qualifies under a config with no base rules and one
 * path made of `$rules`.
 *
 * @param  list<array<mixed>>  $rules
 * @param  array<string, mixed>  $fields
 */
function clinicalProviderQualifiesUnder(array $rules, array $fields): bool
{
    config(['clinical_provider_profile' => ['base' => [], 'paths' => [$rules]]]);

    return (new ClinicalProviderProfileEligibility)->qualifies(clinicalProviderEntryMockWithFields($fields));
}

it('denies everyone when the config is missing or not an array', function (mixed $config): void {
    config(['clinical_provider_profile' => $config]);

    expect((new ClinicalProviderProfileEligibility)->qualifies(clinicalProviderEntryMockWithFields(['x' => 'y'])))->toBeFalse();
})->with([
    'not an array' => ['nope'],
    'empty' => [[]],
]);

it('treats a base that is not a list as no base rules', function (): void {
    config(['clinical_provider_profile' => ['base' => 'nope', 'paths' => [[['field' => 'x', 'operator' => 'not_empty']]]]]);

    expect((new ClinicalProviderProfileEligibility)->qualifies(clinicalProviderEntryMockWithFields(['x' => 'y'])))->toBeTrue();
});

it('qualifies no one when the paths are not a list', function (): void {
    config(['clinical_provider_profile' => ['base' => [], 'paths' => 'nope']]);

    expect((new ClinicalProviderProfileEligibility)->qualifies(clinicalProviderEntryMockWithFields(['x' => 'y'])))->toBeFalse();
});

it('skips a path that is not a list of rules and keeps checking the next', function (): void {
    config(['clinical_provider_profile' => ['base' => [], 'paths' => ['nope', [['field' => 'x', 'operator' => 'not_empty']]]]]);

    expect((new ClinicalProviderProfileEligibility)->qualifies(clinicalProviderEntryMockWithFields(['x' => 'y'])))->toBeTrue();
});

it('fails a rule with no usable field handle or an unknown operator', function (array $rule): void {
    expect(clinicalProviderQualifiesUnder([$rule], ['x' => 'y']))->toBeFalse();
})->with([
    'no field' => [['operator' => 'not_empty']],
    'empty field' => [['field' => '', 'operator' => 'not_empty']],
    'non-string field' => [['field' => 5, 'operator' => 'not_empty']],
    'unknown operator' => [['field' => 'x', 'operator' => 'shrug']],
    'no operator' => [['field' => 'x']],
]);

it('normalizes toggle-like values before comparing them to a boolean', function (mixed $actual, bool $expected): void {
    expect(clinicalProviderQualifiesUnder([['field' => 'flag', 'operator' => 'equals', 'value' => true]], ['flag' => $actual]))->toBe($expected)
        ->and(clinicalProviderQualifiesUnder([['field' => 'flag', 'operator' => 'not_equals', 'value' => true]], ['flag' => $actual]))->toBe(! $expected);
})->with([
    'true' => [true, true],
    'false' => [false, false],
    'missing' => [null, false],
    'empty string' => ['', false],
    'string true' => ['true', true],
    'string one' => ['1', true],
    'integer one' => [1, true],
    'string false' => ['false', false],
    'string zero' => ['0', false],
    'integer zero' => [0, false],
    'other text' => ['yes', true],
    'empty list' => [[], false],
]);

it('compares strings case-insensitively, and never matches a non-string to a string', function (): void {
    $equals = fn (mixed $actual): bool => clinicalProviderQualifiesUnder([['field' => 's', 'operator' => 'equals', 'value' => 'Employee']], ['s' => $actual]);
    $differs = fn (mixed $actual): bool => clinicalProviderQualifiesUnder([['field' => 's', 'operator' => 'not_equals', 'value' => 'Employee']], ['s' => $actual]);

    expect($equals('employee'))->toBeTrue()
        ->and($equals('contractor'))->toBeFalse()
        ->and($equals(5))->toBeFalse()
        ->and($differs('EMPLOYEE'))->toBeFalse()
        ->and($differs('contractor'))->toBeTrue()
        ->and($differs(null))->toBeTrue();
});

it('compares any other expected value by identity', function (): void {
    $equals = fn (mixed $actual): bool => clinicalProviderQualifiesUnder([['field' => 'n', 'operator' => 'equals', 'value' => 5]], ['n' => $actual]);
    $differs = fn (mixed $actual): bool => clinicalProviderQualifiesUnder([['field' => 'n', 'operator' => 'not_equals', 'value' => 5]], ['n' => $actual]);

    expect($equals(5))->toBeTrue()
        ->and($equals('5'))->toBeFalse()
        ->and($differs('5'))->toBeTrue()
        ->and($differs(5))->toBeFalse();
});

it('reads a missing rule value as null', function (): void {
    expect(clinicalProviderQualifiesUnder([['field' => 'n', 'operator' => 'equals']], ['n' => null]))->toBeTrue()
        ->and(clinicalProviderQualifiesUnder([['field' => 'n', 'operator' => 'not_equals']], ['n' => null]))->toBeFalse();
});

it('treats a value as present unless it is null, empty, or an empty list or collection', function (mixed $value, bool $present): void {
    expect(clinicalProviderQualifiesUnder([['field' => 'v', 'operator' => 'not_empty']], ['v' => $value]))->toBe($present);
})->with([
    'null' => [null, false],
    'empty string' => ['', false],
    'empty list' => [[], false],
    'list' => [['loc-1'], true],
    'empty collection' => [collect(), false],
    'collection' => [collect(['loc-1']), true],
    'zero' => [0, true],
    'text' => ['x', true],
]);

it('matches any allowed slug, trimmed and case-insensitive, from a list or a collection', function (mixed $value, bool $expected): void {
    $rule = ['field' => 'creds', 'operator' => 'contains_any', 'values' => ['  MD ', 42, '', 'board-certified']];

    expect(clinicalProviderQualifiesUnder([$rule], ['creds' => $value]))->toBe($expected);
})->with([
    'list' => [['rn', ' md '], true],
    'collection' => [collect(['BOARD-CERTIFIED']), true],
    'no match' => [['rn'], false],
    'skips non-slug entries before a match' => [[['md'], null, '   ', 'md'], true],
    'integer slug' => [[42], false],
    'not a list' => ['md', false],
]);

it('matches an integer slug against an allowed numeric string', function (): void {
    expect(clinicalProviderQualifiesUnder([['field' => 'c', 'operator' => 'contains_any', 'values' => ['42']]], ['c' => [42]]))->toBeTrue();
});

it('matches nothing when the allow list is missing, not a list, or holds no usable slug', function (mixed $allowed): void {
    $rule = ['field' => 'creds', 'operator' => 'contains_any'];
    if ($allowed !== 'omit') {
        $rule['values'] = $allowed;
    }

    expect(clinicalProviderQualifiesUnder([$rule], ['creds' => ['md']]))->toBeFalse();
})->with([
    'missing' => ['omit'],
    'not a list' => ['md'],
    'only blanks and non-strings' => [[' ', 7, null]],
]);

it('denies when a base rule fails, even though a path would pass', function (): void {
    config(['clinical_provider_profile' => [
        'base' => [['field' => 'active', 'operator' => 'equals', 'value' => true]],
        'paths' => [[['field' => 'x', 'operator' => 'not_empty']]],
    ]]);

    expect((new ClinicalProviderProfileEligibility)->qualifies(clinicalProviderEntryMockWithFields(['active' => false, 'x' => 'y'])))->toBeFalse();
});

it('fails an empty field handle before reading any value', function (): void {
    // An empty handle reads as null, which `equals null` would otherwise accept.
    expect(clinicalProviderQualifiesUnder([['field' => '', 'operator' => 'equals', 'value' => null]], []))->toBeFalse();
});

it('reads uppercase or other text as true, since only the lowercase string false is special', function (): void {
    expect(clinicalProviderQualifiesUnder([['field' => 'f', 'operator' => 'equals', 'value' => true]], ['f' => 'FALSE']))->toBeTrue();
});
