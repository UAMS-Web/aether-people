<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\RoleTitleDefinition;
use UAMSWeb\Aether\People\Support\RoleTitleSchemaOutputComputer;
use UAMSWeb\Aether\People\Support\RoleTitleValue;

/**
 * Mutation: 100% on UAMSWeb\Aether\People\Support\RoleTitleSchemaOutputComputer (125 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Integration/Support/RoleTitleSchemaOutputComputerTest.php
 */
it('matches the first schema row for an institutional chancellor', function (): void {
    $titleOptions = RoleTitleValue::emptyTitleOptionsPayload();

    foreach (array_keys($titleOptions) as $k) {
        $titleOptions[$k] = in_array($k, ['emeritus', 'emerita'], true)
            ? false
            : $titleOptions[$k];
    }

    $data = [
        'configuration' => RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP,
        'title_options' => $titleOptions,
        'hierarchical_modifier' => null,
        'positional_qualifier' => null,
        'role' => 'chancellor',
    ];

    $out = RoleTitleSchemaOutputComputer::computeFromNormalizedPayload($data);

    expect(data_get($out, 'occupation.same_as'))->toBe(['https://www.wikidata.org/wiki/Q61061']);
    expect(data_get($out, 'occupation.category_code.onet_soc'))->toBe(['11-9033.00']);
    expect(data_get($out, 'occupation.category_code.isco_08'))->toBe(['1120', '1345']);
    expect(data_get($out, 'occupational_field.same_as'))->toBe([
        'https://www.wikidata.org/wiki/Q4671162',
        'https://www.wikidata.org/wiki/Q4671162',
        'https://www.wikidata.org/wiki/Q484275',
    ]);
    expect(data_get($out, 'occupational_field.category_code'))->toBe([]);
});

it('matches the vice chancellor row over the plain chancellor row', function (): void {
    $titleOptions = RoleTitleValue::emptyTitleOptionsPayload();

    foreach (array_keys($titleOptions) as $k) {
        $titleOptions[$k] = in_array($k, ['emeritus', 'emerita'], true)
            ? false
            : $titleOptions[$k];
    }

    $data = [
        'configuration' => RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP,
        'title_options' => $titleOptions,
        'hierarchical_modifier' => null,
        'positional_qualifier' => 'vice',
        'role' => 'chancellor',
    ];

    $out = RoleTitleSchemaOutputComputer::computeFromNormalizedPayload($data);

    expect(data_get($out, 'occupation.same_as'))->toBe(['https://www.wikidata.org/wiki/Q12416358']);
});

it('matches the faculty adjunct associate professor schema row (contains_all)', function (): void {
    $titleOptions = RoleTitleValue::emptyTitleOptionsPayload();

    foreach (array_keys($titleOptions) as $k) {
        $titleOptions[$k] = $k === 'adjunct';
    }

    $data = [
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'title_options' => $titleOptions,
        'scope_qualifier' => null,
        'hierarchical_modifier' => 'associate',
        'role' => 'professor',
    ];

    $out = RoleTitleSchemaOutputComputer::computeFromNormalizedPayload($data);

    expect(data_get($out, 'occupation.same_as'))->toBe(['https://www.wikidata.org/wiki/Q102162072']);
});

it('uses the equals_any branch for a professor emeritus', function (): void {
    // The honorific is a single-choice radios slot since UAMS-Web/uams-statamic-archive#2138, so a professor
    // emeritus is reached by `honorific => emeritus`, and the positive schema rule
    // that names Q1240569 fires through `equals_any` rather than `contains_any`.
    $data = [
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'title_options' => RoleTitleValue::emptyTitleOptionsPayload(),
        'honorific' => 'emeritus',
        'scope_qualifier' => null,
        'hierarchical_modifier' => null,
        'role' => 'professor',
    ];

    $out = RoleTitleSchemaOutputComputer::computeFromNormalizedPayload($data);

    expect(data_get($out, 'occupation.same_as'))->toBe(['https://www.wikidata.org/wiki/Q1240569']);
    expect(data_get($out, 'occupation.category_code'))->toBe([]);
    expect(data_get($out, 'occupational_field'))->toBe([]);
});

it('matches alternate role keys via equals_any', function (): void {
    $titleOptions = RoleTitleValue::emptyTitleOptionsPayload();

    foreach (array_keys($titleOptions) as $k) {
        $titleOptions[$k] = in_array($k, ['emeritus', 'emerita'], true)
            ? false
            : $titleOptions[$k];
    }

    $data = [
        'configuration' => RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP,
        'title_options' => $titleOptions,
        'hierarchical_modifier' => null,
        'positional_qualifier' => null,
        'role' => 'public_relations_officer',
    ];

    $out = RoleTitleSchemaOutputComputer::computeFromNormalizedPayload($data);

    expect(data_get($out, 'occupation.same_as'))->toBe(['https://www.wikidata.org/wiki/Q3659898']);
});

it('yields empty outputs when the schema list is empty', function (): void {
    // Preserve the real config so other tests are not affected by this swap.
    /** @var array<string, mixed> $original */
    $original = config('role_title');

    try {
        $patched = $original;
        $patched['schema'] = [];
        config(['role_title' => $patched]);

        // Rebuild the definition singleton from the overridden config.
        RoleTitleDefinition::forgetInstance();

        $titleOptions = RoleTitleValue::emptyTitleOptionsPayload();

        foreach (array_keys($titleOptions) as $k) {
            $titleOptions[$k] = false;
        }

        $data = [
            'configuration' => RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP,
            'title_options' => $titleOptions,
            'hierarchical_modifier' => null,
            'positional_qualifier' => null,
            'role' => 'chancellor',
        ];

        $out = RoleTitleSchemaOutputComputer::computeFromNormalizedPayload($data);

        expect($out)->toBe([]);
        expect(RoleTitleSchemaOutputComputer::storedOutputSubfieldKeys())->toBe([]);
        expect(RoleTitleSchemaOutputComputer::emptyPlaceholderSlice())->toBe([]);
    } finally {
        // Restore the original role_title config and singleton cache.
        config(['role_title' => $original]);
        RoleTitleDefinition::forgetInstance();
    }
});

it('carries null schema-output placeholders in the empty shared payload', function (): void {
    $shared = RoleTitleValue::emptyValueSharedPayload();

    expect($shared)->toHaveKey('occupation');
    expect($shared)->toHaveKey('occupational_field');
    expect($shared['occupation'])->toBeNull();
    expect($shared['occupational_field'])->toBeNull();
});

/**
 * Run a callback with the role_title schema replaced by the given rows, then restore it.
 *
 * @param  array<int|string, mixed>  $schema  Replacement schema rows
 */
function roleTitleWithSchema(array $schema, Closure $callback): mixed
{
    /** @var array<string, mixed> $original */
    $original = config('role_title');

    try {
        config(['role_title' => [...$original, 'schema' => $schema]]);
        RoleTitleDefinition::forgetInstance();

        return $callback();
    } finally {
        config(['role_title' => $original]);
        RoleTitleDefinition::forgetInstance();
    }
}

/**
 * Whether a single schema row with these conditions matches the payload.
 *
 * @param  array<array-key, mixed>  $conditions
 * @param  array<string, mixed>  $data
 */
function roleTitleRowMatches(array $conditions, array $data): bool
{
    return roleTitleWithSchema(
        [['conditions' => $conditions, 'output' => ['occupation' => ['matched']]]],
        fn (): bool => RoleTitleSchemaOutputComputer::computeFromNormalizedPayload($data) === ['occupation' => ['matched']]
    ) === true;
}

it('lists each output handle once, in first-seen order, skipping rows without an output map', function (): void {
    roleTitleWithSchema([
        'not a row',
        ['conditions' => [], 'output' => 'not a map'],
        ['conditions' => [], 'output' => ['occupation' => [], 5 => [], 'occupational_field' => []]],
        ['conditions' => [], 'output' => ['occupation' => [], 'skill' => []]],
    ], function (): void {
        expect(RoleTitleSchemaOutputComputer::storedOutputSubfieldKeys())->toBe(['occupation', 'occupational_field', 'skill'])
            ->and(RoleTitleSchemaOutputComputer::emptyPlaceholderSlice())->toBe(['occupation' => null, 'occupational_field' => null, 'skill' => null]);
    });
});

it('returns the normalized output of the first row whose conditions pass', function (): void {
    roleTitleWithSchema([
        ['conditions' => 'not a map', 'output' => ['occupation' => ['bad conditions']]],
        ['conditions' => [], 'output' => 'not a map'],
        ['conditions' => ['role' => [['operator' => 'equals', 'value' => 'dean']]], 'output' => ['occupation' => ['dean']]],
        ['conditions' => ['role' => [['operator' => 'equals', 'value' => 'chancellor']]], 'output' => [
            'occupation' => ['same_as' => ['a', null, true, 5], 'category_code' => [], 7 => 'dropped'],
            3 => 'dropped',
            'occupational_field' => 'as is',
        ]],
        ['conditions' => [], 'output' => ['occupation' => ['later']]],
    ], function (): void {
        expect(RoleTitleSchemaOutputComputer::computeFromNormalizedPayload(['role' => 'chancellor']))->toBe([
            'occupation' => ['same_as' => ['a', '5'], 'category_code' => []],
            'occupational_field' => 'as is',
        ])->and(RoleTitleSchemaOutputComputer::computeFromNormalizedPayload(['role' => 'provost']))->toBe(['occupation' => ['later']]);
    });
});

it('returns no output when no row passes', function (): void {
    roleTitleWithSchema([['conditions' => ['role' => [['operator' => 'equals', 'value' => 'dean']]], 'output' => ['occupation' => ['dean']]]], function (): void {
        expect(RoleTitleSchemaOutputComputer::computeFromNormalizedPayload(['role' => 'chancellor']))->toBe([]);
    });
});

it('fails a row whose rules are malformed', function (array $conditions): void {
    /** @var array<string, mixed> $typedConditions */
    $typedConditions = $conditions;

    expect(roleTitleRowMatches($typedConditions, ['role' => 'chancellor']))->toBeFalse();
})->with([
    'rules not a list' => [['role' => 'equals']],
    'a rule not a map' => [['role' => ['equals']]],
    'no operator' => [['role' => [['value' => 'chancellor']]]],
    'an empty operator' => [['role' => [['operator' => '', 'value' => 'chancellor']]]],
    'a non-string operator' => [['role' => [['operator' => 5, 'value' => 'chancellor']]]],
    'an unknown operator' => [['role' => [['operator' => 'like', 'value' => 'chancellor']]]],
    'a later rule failing' => [['role' => [['operator' => 'equals', 'value' => 'chancellor'], ['operator' => 'equals', 'value' => 'dean']]]],
]);

it('matches scalar rules against the trimmed text value', function (string $operator, mixed $value, mixed $actual, bool $matches): void {
    expect(roleTitleRowMatches(['role' => [['operator' => $operator, 'value' => $value]]], ['role' => $actual]))->toBe($matches);
})->with([
    'equals, padded' => ['equals', 'dean', '  dean ', true],
    'equals, different' => ['equals', 'dean', 'provost', false],
    'equals null, blank' => ['equals', null, '   ', true],
    'equals null, missing' => ['equals', null, null, true],
    'equals null, non-text' => ['equals', null, 5, true],
    'not equals' => ['not_equals', 'dean', 'provost', true],
    'not equals, same' => ['not_equals', 'dean', 'dean', false],
    'equals any' => ['equals_any', ['provost', 'dean'], 'dean', true],
    'equals any, absent' => ['equals_any', ['provost'], 'dean', false],
    'equals any, not a list' => ['equals_any', 'dean', 'dean', false],
    'equals any, a non-text item' => ['equals_any', ['dean', 5], 'dean', false],
    'not equals any' => ['not_equals_any', ['provost'], 'dean', true],
    'not equals any, present' => ['not_equals_any', ['dean'], 'dean', false],
    'not equals any, not a list' => ['not_equals_any', 'provost', 'dean', false],
    'equals any of an empty string, blank' => ['equals_any', [''], '   ', false],
    'not equals any of an empty string, blank' => ['not_equals_any', [''], '   ', true],
    'equals all, every item' => ['equals_all', ['dean', 'dean'], 'dean', true],
    'equals all, one differs' => ['equals_all', ['dean', 'provost'], 'dean', false],
    'equals all, empty' => ['equals_all', [], 'dean', false],
    'equals all, not a list' => ['equals_all', 'dean', 'dean', false],
    'not equals all, one differs' => ['not_equals_all', ['dean', 'provost'], 'dean', true],
    'not equals all, every item' => ['not_equals_all', ['dean'], 'dean', false],
    'not equals all, empty' => ['not_equals_all', [], 'dean', true],
    'not equals all, not a list' => ['not_equals_all', 'dean', 'provost', false],
]);

it('matches checkbox rules against the options that are switched on', function (string $operator, mixed $value, mixed $options, bool $matches): void {
    expect(roleTitleRowMatches(['title_options' => [['operator' => $operator, 'value' => $value]]], ['title_options' => $options]))->toBe($matches);
})->with([
    'contains any' => ['contains_any', ['b', 'z'], ['a' => true, 'b' => 1], true],
    'contains any, none on' => ['contains_any', ['b'], ['a' => true, 'b' => false], false],
    'not contains any' => ['not_contains_any', ['z'], ['a' => '1'], true],
    'not contains any, one on' => ['not_contains_any', ['a'], ['a' => '1'], false],
    'contains all' => ['contains_all', ['a', 'b'], ['a' => true, 'b' => true, 'c' => true], true],
    'contains all, one off' => ['contains_all', ['a', 'b'], ['a' => true, 'b' => 'yes'], false],
    'not contains all' => ['not_contains_all', ['a', 'b'], ['a' => true], true],
    'not contains all, every one on' => ['not_contains_all', ['a'], ['a' => true], false],
    'equals, in any order' => ['equals', ['b', 'a'], ['a' => true, 'b' => true], true],
    'equals, options switched on in reverse' => ['equals', ['a', 'b'], ['b' => true, 'a' => true], true],
    'a non-text option key before a text one' => ['contains_any', ['a'], [0 => true, 'a' => true], true],
    'equals, an extra one on' => ['equals', ['a'], ['a' => true, 'b' => true], false],
    'not equals' => ['not_equals', ['a'], ['a' => true, 'b' => true], true],
    'not equals, same set' => ['not_equals', ['a'], ['a' => true], false],
    'an unknown operator' => ['contains', ['a'], ['a' => true], false],
    'a value that is not a list' => ['contains_any', 'a', ['a' => true], false],
    'a non-text value item' => ['contains_any', ['a', 5], ['a' => true], false],
    'options that are not a map' => ['not_contains_any', ['a'], 'a', true],
    'a non-text option key' => ['contains_any', ['0'], [0 => true], false],
]);

it('normalizes a schema output fragment', function (mixed $raw, mixed $normalized): void {
    expect(RoleTitleSchemaOutputComputer::normalizeSchemaOutputFragment($raw))->toBe($normalized);
})->with([
    'a scalar' => ['as is', 'as is'],
    'an empty list' => [[], []],
    'a list' => [['a', null, false, 5, 1.5], ['a', '5', '1.5']],
    'a nested map' => [['a' => ['b' => [true, 'c']], 0 => 'dropped'], ['a' => ['b' => ['c']]]],
    'a numeric key before a text one' => [[5 => 'dropped', 'a' => 'kept'], ['a' => 'kept']],
]);

it('reads a numeric condition key as text', function (): void {
    // A numeric subfield key is looked up as its text form, so a missing value equals null
    expect(roleTitleRowMatches([0 => [['operator' => 'equals', 'value' => null]]], ['role' => 'dean']))->toBeTrue();
});

it('fails a rule given as an object, even one readable as an array', function (): void {
    // Only a real array is read as a rule, not an ArrayAccess object
    expect(roleTitleRowMatches(['role' => [new ArrayObject(['operator' => 'equals', 'value' => 'chancellor'])]], ['role' => 'chancellor']))->toBeFalse();
});

it('treats a numeric condition key as a checkbox map only when it strictly names one', function (): void {
    /** @var array<string, mixed> $original */
    $original = config('role_title');

    try {
        // A checkbox subfield whose key is the integer 0
        /** @var array<array-key, mixed> $subfields */
        $subfields = $original['subfields'] ?? [];
        $subfields[0] = ['type' => 'checkboxes'];
        config(['role_title' => [...$original, 'subfields' => $subfields, 'schema' => [
            ['conditions' => [0 => [['operator' => 'contains_any', 'value' => ['a']]]], 'output' => ['occupation' => ['matched']]],
        ]]]);
        RoleTitleDefinition::forgetInstance();

        // The text key '0' is not strictly the integer 0, so contains_any takes the scalar path and fails
        /** @var array<string, mixed> $data */
        $data = json_decode('{"0": {"a": true}}', true);

        expect(RoleTitleSchemaOutputComputer::computeFromNormalizedPayload($data))->toBe([]);
    } finally {
        config(['role_title' => $original]);
        RoleTitleDefinition::forgetInstance();
    }
});
