<?php

declare(strict_types=1);

use Illuminate\Validation\Rules\In;
use Statamic\Fields\Field;
use UAMSWeb\Aether\People\Fieldtypes\RoleTitle;
use UAMSWeb\Aether\People\Support\RoleTitleDefinition;

covers(RoleTitle::class);

/**
 * The `role_title` fieldtype against a `role_title` config whose rows the shipped
 * config does not have: a group with no `fields`, nested rows that are not
 * arrays or have no type, text rows with no default or a default that is not a
 * string, a toggle, several linked-unit rows, `replaces_nested_field` given as a
 * string and with entries that are not strings or name no field, and radios with
 * no `allow_optional_none` or a blank `required_with_nested_field`.
 *
 * Each odd row sits before a row that still has to be handled, so a skip that
 * stopped the walk instead of moving past the row would drop the later one.
 *
 * @command  php artisan test --compact tests/Integration/Fieldtypes/RoleTitleFieldtypeConfigShapesTest.php
 */
beforeEach(function (): void {
    RoleTitleDefinition::forgetInstance();
});

afterEach(function (): void {
    RoleTitleDefinition::forgetInstance();
});

/**
 * Load the shipped `role_title` config with a `scope` group of odd rows, an
 * `extra` group with no fields ahead of it, and `scope_qualifier` without its
 * `allow_optional_none` flag.
 */
function roleTitleShapesLoadOddConfig(): void
{
    /** @var array<string, mixed> $raw */
    $raw = config('role_title');

    /** @var array<string, array<string, mixed>> $subfields */
    $subfields = $raw['subfields'];

    $scope = $subfields['scope'];

    /** @var array<string, array<string, mixed>> $fields */
    $fields = $scope['fields'];

    $joiner = $fields['scope_joiner'];
    unset($joiner['allow_optional_none']);
    $joiner['required_with_nested_field'] = '';

    $entries = [
        'type' => 'entries',
        'default' => null,
        'available_in' => [],
        'field' => $fields['scope_unit']['field'],
    ];

    $scopeUnit = $fields['scope_unit'];
    $scopeUnit['replaces_nested_field'] = [7, 'missing', 'scope_name', 'scope_article'];

    $scope['fields'] = [
        'bogus' => 'not a row',
        'note' => ['type' => 'text', 'available_in' => []],
        'code' => ['type' => 'text', 'default' => 5, 'available_in' => []],
        'tag' => ['type' => 'text', 'default' => ['x'], 'available_in' => []],
        'flag' => ['type' => 'toggle', 'available_in' => []],
        'untyped' => ['available_in' => []],
        'scope_joiner' => $joiner,
        'scope_article' => $fields['scope_article'],
        'link_a' => $entries,
        'link_b' => $entries + ['replaces_nested_field' => 'note'],
        'scope_unit' => $scopeUnit,
        'scope_name' => $fields['scope_name'],
    ];

    $qualifier = $subfields['scope_qualifier'];
    unset($qualifier['allow_optional_none']);

    $reordered = [];

    foreach ($subfields as $key => $row) {
        if ($key === 'scope') {
            $reordered['extra'] = ['type' => 'group', 'available_in' => []];
            $row = $scope;
        }

        $reordered[$key] = $key === 'scope_qualifier' ? $qualifier : $row;
    }

    $raw['subfields'] = $reordered;

    config()->set('role_title', $raw);
    RoleTitleDefinition::forgetInstance();
}

/**
 * A role_title fieldtype bound to a plain field.
 */
function roleTitleShapesFieldtype(): RoleTitle
{
    $fieldtype = new RoleTitle;
    $fieldtype->setField(new Field('rt', ['type' => 'role_title']));

    return $fieldtype;
}

it('builds nested rules past every odd row', function (): void {
    roleTitleShapesLoadOddConfig();

    $rules = roleTitleShapesFieldtype()->extraRules();
    $keys = \array_keys($rules);

    expect($keys)->toContain('rt.scope.note', 'rt.scope.code', 'rt.scope.scope_joiner', 'rt.scope.scope_article', 'rt.scope.link_a', 'rt.scope.link_b', 'rt.scope.scope_unit', 'rt.scope.scope_name')
        ->and($keys)->not->toContain('rt.scope.bogus')
        ->and($keys)->not->toContain('rt.scope.flag')
        ->and($keys)->not->toContain('rt.scope.untyped')
        ->and(\array_filter($keys, static fn (string $key): bool => \str_starts_with($key, 'rt.extra')))->toBe([])
        ->and($rules['rt.scope.note'])->toBe(['nullable', 'string'])
        ->and((string) mustBe(In::class, $rules['rt.scope.scope_joiner'][2], 'joiner in rule'))->toBe('in:"for","of","in"')
        ->and($rules['rt.scope.scope_joiner'])->toHaveCount(4)
        ->and((string) mustBe(In::class, $rules['rt.scope_qualifier'][2], 'qualifier in rule'))->toBe('in:"clinical","research"');
});

it('normalizes each odd nested row and clears what each set link replaces', function (): void {
    roleTitleShapesLoadOddConfig();

    $out = roleTitleShapesFieldtype()->process([
        'configuration' => 'academic_leadership',
        'role' => 'director',
        'scope' => [
            'bogus' => 'kept out',
            'note' => 'a note',
            'code' => 9,
            'flag' => true,
            'scope_joiner' => 'of',
            'scope_article' => 'the',
            'link_b' => ['link-b'],
            'scope_unit' => 'unit-1',
            'scope_name' => 'Surgery',
        ],
    ]);

    expect($out['scope'])->toBe([
        'note' => '',
        'code' => '5',
        'tag' => '',
        'scope_joiner' => 'of',
        'scope_article' => null,
        'link_a' => null,
        'link_b' => 'link-b',
        'scope_unit' => 'unit-1',
        'scope_name' => '',
    ])
        ->and($out['extra'])->toBe([]);
});

it('falls back to each text row default for a scope that is not a map', function (): void {
    roleTitleShapesLoadOddConfig();

    $out = roleTitleShapesFieldtype()->process([
        'configuration' => 'academic_leadership',
        'role' => 'director',
        'scope' => 'Surgery',
    ]);

    expect($out['scope'])->toBe([
        'note' => '',
        'code' => '5',
        'tag' => '',
        'scope_joiner' => 'for',
        'scope_article' => null,
        'link_a' => null,
        'link_b' => null,
        'scope_unit' => null,
        'scope_name' => '',
    ]);
});

it('saves a configuration-less value unchanged when the config names no configuration', function (): void {
    config()->set('role_title.configurations', ['handles' => []]);
    RoleTitleDefinition::forgetInstance();

    $out = roleTitleShapesFieldtype()->process(['role' => 'director']);

    expect($out['configuration'])->toBeNull()
        ->and($out['role'])->toBe('director');
});
