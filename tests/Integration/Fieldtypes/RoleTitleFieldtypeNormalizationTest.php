<?php

declare(strict_types=1);

use Statamic\Fields\Field;
use UAMSWeb\Aether\People\Fieldtypes\RoleTitle;

covers(RoleTitle::class);

/**
 * The `role_title` fieldtype's read, save and validation paths: the blueprint
 * toggles that pick the structure and configuration, the empty and non-array
 * inputs each path takes, the unstructured branch beside the structured one, and
 * the per-subfield normalization (radios, checkboxes, the scope group and the
 * legacy honorific).
 *
 * @command  php artisan test --compact tests/Integration/Fieldtypes/RoleTitleFieldtypeNormalizationTest.php
 */

/**
 * A role_title fieldtype bound to a field with the given blueprint config.
 *
 * @param  array<string, mixed>  $config
 */
function roleTitleNormFieldtype(array $config = [], mixed $value = null): RoleTitle
{
    $field = new Field('rt', ['type' => 'role_title'] + $config);

    if ($value !== null) {
        $field->setValue($value);
    }

    $fieldtype = new RoleTitle;
    $fieldtype->setField($field);

    return $fieldtype;
}

/**
 * Blueprint config with both title structures enabled.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function roleTitleNormBoth(array $extra = []): array
{
    return ['enabled_title_structures' => ['structured', 'unstructured']] + $extra;
}

/**
 * The empty title options map: every option off.
 *
 * @return array<string, bool>
 */
function roleTitleNormNoOptions(): array
{
    return [
        'interim' => false,
        'co_prefix' => false,
        'regional' => false,
        'senior' => false,
        'executive' => false,
        'visiting' => false,
        'distinguished' => false,
        'adjunct' => false,
    ];
}

/**
 * The structured empty value for the faculty configuration.
 *
 * @return array<string, mixed>
 */
function roleTitleNormEmptyFaculty(): array
{
    return [
        'configuration' => 'faculty',
        'mode' => 'structured',
        'value' => null,
        'title_options' => roleTitleNormNoOptions(),
        'scope_qualifier' => null,
        'hierarchical_modifier' => null,
        'object' => null,
        'positional_qualifier' => null,
        'role' => 'chair',
        'honorific' => null,
        'scope' => ['scope_joiner' => 'for', 'scope_article' => null, 'scope_unit' => null, 'scope_name' => ''],
        'occupation' => null,
        'occupational_field' => null,
    ];
}

/**
 * The empty unstructured occupation branches.
 *
 * @return array<string, mixed>
 */
function roleTitleNormEmptyOccupation(): array
{
    return ['same_as' => [], 'category_code' => ['isco_08' => [], 'onet_soc' => []]];
}

/**
 * Call one of the fieldtype's private methods.
 *
 * @param  list<mixed>  $args
 */
function roleTitleNormCall(RoleTitle $fieldtype, string $method, array $args = []): mixed
{
    return new ReflectionMethod(RoleTitle::class, $method)->invoke($fieldtype, ...$args);
}

// --- Blueprint toggles --------------------------------------------------------

it('preloads the blueprint toggles and the scope unit picker config', function (): void {
    $preload = roleTitleNormFieldtype()->preload();

    expect($preload['enabled_configurations'])->toBe(['faculty', 'academic_leadership', 'institutional_leadership', 'clinical_leadership'])
        ->and($preload['default_configuration'])->toBe('faculty')
        ->and($preload['enabled_title_structures'])->toBe(['structured', 'unstructured'])
        ->and($preload['default_title_structure'])->toBe('structured')
        ->and($preload['required_unstructured_subfields'])->toBe(['value'])
        ->and($preload['border'])->toBeTrue()
        ->and($preload['scope_unit_field_config'])->toBe([
            'type' => 'entries',
            'collections' => ['organizations'],
            'max_items' => 1,
            'create' => false,
            'select_across_sites' => true,
            'mode' => 'default',
            'handle' => 'scope_unit',
        ])
        ->and($preload['scope_unit_meta'])->toBe([])
        ->and($preload)->toHaveKey('option_label_maps');
});

it('preloads the border toggle as a boolean', function (mixed $border, bool $expected): void {
    expect(roleTitleNormFieldtype(['border' => $border])->preload()['border'])->toBe($expected);
})->with([
    'off' => [false, false],
    'zero' => [0, false],
    'one' => [1, true],
    'null, which overrides the config default' => [null, true],
]);

it('preloads a bordered container without a field', function (): void {
    expect(new RoleTitle()->preload()['border'])->toBeTrue();
});

it('preloads a configured default configuration that is enabled', function (): void {
    $preload = roleTitleNormFieldtype([
        'enabled_configurations' => ['faculty', 'clinical_leadership'],
        'default_configuration' => 'clinical_leadership',
    ])->preload();

    expect($preload['enabled_configurations'])->toBe(['faculty', 'clinical_leadership'])
        ->and($preload['default_configuration'])->toBe('clinical_leadership');
});

it('keeps enabled configurations in canonical order whatever order the blueprint lists them', function (): void {
    expect(roleTitleNormFieldtype([
        'enabled_configurations' => ['clinical_leadership', 'faculty'],
    ])->preload()['enabled_configurations'])->toBe(['faculty', 'clinical_leadership']);
});

it('falls back to the first enabled configuration for a default that is not a string or not enabled', function (mixed $default): void {
    expect(roleTitleNormFieldtype([
        'enabled_configurations' => ['academic_leadership', 'clinical_leadership'],
        'default_configuration' => $default,
    ])->preload()['default_configuration'])->toBe('academic_leadership');
})->with([
    'not enabled' => ['faculty'],
    'not a string' => [['clinical_leadership']],
]);

it('falls back to the definition default when no enabled configuration is known', function (mixed $enabled): void {
    expect(roleTitleNormFieldtype(['enabled_configurations' => $enabled])->preload()['enabled_configurations'])->toBe(['faculty']);
})->with([
    'unknown handles' => [['nope']],
    'empty list' => [[]],
    'not a list' => ['faculty'],
]);

it('enables title structures in canonical order and defaults to the configured one', function (): void {
    $preload = roleTitleNormFieldtype([
        'enabled_title_structures' => ['unstructured', 'structured'],
        'default_title_structure' => 'unstructured',
    ])->preload();

    expect($preload['enabled_title_structures'])->toBe(['structured', 'unstructured'])
        ->and($preload['default_title_structure'])->toBe('unstructured');
});

it('falls back to the first enabled structure for a default structure that is not usable', function (mixed $default): void {
    expect(roleTitleNormFieldtype([
        'enabled_title_structures' => ['unstructured'],
        'default_title_structure' => $default,
    ])->preload()['default_title_structure'])->toBe('unstructured');
})->with([
    'not enabled' => ['structured'],
    'not a string' => [['unstructured']],
    'missing' => [null],
]);

it('falls back to the structured structure when no enabled structure is known', function (mixed $enabled): void {
    expect(roleTitleNormFieldtype(['enabled_title_structures' => $enabled])->preload()['enabled_title_structures'])->toBe(['structured']);
})->with([
    'unknown handles' => [['nope']],
    'empty list' => [[]],
]);

it('reads the linked scope unit id from the field value', function (mixed $value, ?string $expected): void {
    expect(roleTitleNormCall(roleTitleNormFieldtype([], $value), 'linkedScopeUnitId'))->toBe($expected);
})->with([
    'a linked unit' => [['scope' => ['scope_unit' => 'unit-1']], 'unit-1'],
    'a picker list' => [['scope' => ['scope_unit' => ['unit-2']]], 'unit-2'],
    'no scope' => [['role' => 'chair'], null],
    'not an array' => ['chair', null],
]);

it('reads no linked scope unit without a field', function (): void {
    expect(roleTitleNormCall(new RoleTitle, 'linkedScopeUnitId'))->toBeNull();
});

it('starts new values in the configured structure and configuration', function (): void {
    $value = roleTitleNormFieldtype(roleTitleNormBoth([
        'default_title_structure' => 'unstructured',
        'enabled_configurations' => ['clinical_leadership'],
    ]))->defaultValue();

    expect($value['mode'])->toBe('unstructured')
        ->and($value['configuration'])->toBe('clinical_leadership')
        ->and($value['occupation'])->toBe(roleTitleNormEmptyOccupation())
        ->and($value['occupational_field'])->toBe(['same_as' => [], 'category_code' => []]);
});

it('starts a detached fieldtype from the definition defaults', function (): void {
    expect(new RoleTitle()->defaultValue())->toBe(roleTitleNormEmptyFaculty());
});

// --- Read paths ---------------------------------------------------------------

it('pre-processes a value that is not an array as the derived empty value', function (): void {
    $out = roleTitleNormFieldtype()->preProcess(null);

    expect($out['role'])->toBe('chair')
        ->and($out['value'])->toBe('')
        ->and($out['mode'])->toBe('structured');
});

it('pre-processes a structured value, recomposing a stale value', function (): void {
    $out = roleTitleNormFieldtype()->preProcess([
        'configuration' => 'faculty',
        'role' => 'professor',
        'value' => 'stale',
        'occupation' => ['same_as' => ['https://stale.example']],
    ]);

    expect($out['value'])->toBe('professor')
        ->and(data_get($out, 'occupation.same_as'))->not->toContain('https://stale.example')
        ->and($out)->not->toHaveKey('__cp_role_title_unstructured_occ_same_as');
});

it('pre-processes an unstructured value with its CP replicator rows and trimmed text', function (): void {
    $out = roleTitleNormFieldtype(roleTitleNormBoth())->preProcess([
        'mode' => 'unstructured',
        'value' => '  Chief Wellness Officer  ',
        'occupation' => ['same_as' => ['https://a.example']],
        'occupational_field' => ['same_as' => ['https://b.example']],
    ]);

    expect($out['mode'])->toBe('unstructured')
        ->and($out['value'])->toBe('Chief Wellness Officer')
        ->and(data_get($out, 'occupation.same_as'))->toBe(['https://a.example'])
        ->and(data_get($out, 'occupational_field.same_as'))->toBe(['https://b.example'])
        ->and($out)->toHaveKey('__cp_role_title_unstructured_occ_same_as')
        ->and($out['__cp_role_title_unstructured_occ_same_as'])->toHaveCount(1)
        ->and($out['__cp_role_title_unstructured_field_same_as'])->toHaveCount(1);
});

it('augments a value that is not an array unchanged', function (mixed $value): void {
    expect(roleTitleNormFieldtype()->augment($value))->toBe($value);
})->with([
    'string' => ['chair'],
    'null' => [null],
]);

it('augments an unstructured value with its trimmed text, not a composed title', function (mixed $text, string $expected): void {
    $out = roleTitleNormFieldtype()->augment([
        'mode' => 'unstructured',
        'value' => $text,
        'occupation' => ['same_as' => ['https://a.example']],
    ]);

    expect(data_get($out, 'value'))->toBe($expected)
        ->and(data_get($out, 'occupation.same_as'))->toBe(['https://a.example']);
})->with([
    'padded text' => ['  Chief  ', 'Chief'],
    'not a string' => [7, ''],
]);

it('augments a structured value with a composed title and its schema output', function (): void {
    $out = roleTitleNormFieldtype()->augment(['configuration' => 'faculty', 'role' => 'professor']);

    expect(data_get($out, 'value'))->toBe('professor')
        ->and(data_get($out, 'occupation.category_code.isco_08'))->toBe(['2310'])
        ->and(data_get($out, 'scope'))->toBe(['scope_joiner' => 'for', 'scope_article' => null, 'scope_unit' => null, 'scope_name' => '']);
});

it('drops the schema output keys when no scenario matches the structured value', function (): void {
    $out = roleTitleNormFieldtype()->augment([
        'configuration' => 'faculty',
        'role' => 'unknown-role',
        'occupation' => ['same_as' => ['https://stale.example']],
        'occupational_field' => ['same_as' => ['https://stale.example']],
    ]);

    expect($out)->not->toHaveKey('occupation')
        ->and($out)->not->toHaveKey('occupational_field');
});

it('validates a value that is not an array as the plain empty value', function (): void {
    expect(roleTitleNormFieldtype()->preProcessValidatable('chair'))->toBe(roleTitleNormEmptyFaculty());
});

it('validates a partial value merged onto the empty value', function (): void {
    $out = roleTitleNormFieldtype()->preProcessValidatable([
        'configuration' => 'faculty',
        'role' => 'professor',
        'scope' => ['scope_name' => 'Surgery'],
    ]);

    expect($out['role'])->toBe('professor')
        ->and($out['scope'])->toBe(['scope_joiner' => 'for', 'scope_article' => null, 'scope_unit' => null, 'scope_name' => 'Surgery'])
        ->and($out['title_options'])->toBe(roleTitleNormNoOptions())
        ->and($out['value'])->toBe('professor for Surgery');
});

it('validates an unstructured value with its CP replicator rows', function (): void {
    $out = roleTitleNormFieldtype(roleTitleNormBoth())->preProcessValidatable([
        'mode' => 'unstructured',
        'value' => ' Chief ',
        'occupation' => ['same_as' => ['https://a.example']],
    ]);

    expect($out['value'])->toBe('Chief')
        ->and($out['__cp_role_title_unstructured_occ_same_as'])->toHaveCount(1)
        ->and(data_get($out, 'occupation.same_as'))->toBe(['https://a.example']);
});

// --- Save path ----------------------------------------------------------------

it('saves a value that is not an array as the stored empty value', function (): void {
    $out = roleTitleNormFieldtype()->process('chair');

    expect($out)->toBe([
        'configuration' => 'faculty',
        'mode' => 'structured',
        'title_options' => roleTitleNormNoOptions(),
        'scope_qualifier' => null,
        'hierarchical_modifier' => null,
        'object' => null,
        'positional_qualifier' => null,
        'role' => 'chair',
        'honorific' => null,
        'scope' => ['scope_joiner' => 'for', 'scope_article' => null, 'scope_unit' => null, 'scope_name' => ''],
    ]);
});

it('saves an unstructured value with its occupation branches and no structured keys', function (): void {
    $out = roleTitleNormFieldtype(roleTitleNormBoth())->process([
        'mode' => 'unstructured',
        'configuration' => 'faculty',
        'role' => 'professor',
        'value' => '  Chief  ',
        'occupation' => ['same_as' => ['https://a.example']],
    ]);

    expect($out)->toBe([
        'mode' => 'unstructured',
        'value' => 'Chief',
        'occupation' => ['same_as' => ['https://a.example'], 'category_code' => ['isco_08' => [], 'onet_soc' => []]],
        'occupational_field' => ['same_as' => [], 'category_code' => []],
    ]);
});

it('saves an unstructured value posted from the CP replicators', function (): void {
    $out = roleTitleNormFieldtype(roleTitleNormBoth())->process([
        'value' => 'Chief',
        '__cp_role_title_unstructured_occ_same_as' => [['url' => 'https://a.example']],
    ]);

    expect($out['mode'])->toBe('unstructured')
        ->and($out)->not->toHaveKey('__cp_role_title_unstructured_occ_same_as')
        ->and($out['value'])->toBe('Chief');
});

it('keeps the posted structure when CP replicator keys arrive with a mode', function (): void {
    $out = roleTitleNormFieldtype(roleTitleNormBoth())->process([
        'mode' => 'structured',
        'configuration' => 'faculty',
        'role' => 'professor',
        '__cp_role_title_unstructured_occ_same_as' => [['url' => 'https://a.example']],
    ]);

    expect($out['mode'])->toBe('structured')
        ->and($out['role'])->toBe('professor')
        ->and($out)->not->toHaveKey('occupation');
});

it('saves a padded or unknown structure as the default structure', function (mixed $mode, string $expected): void {
    $out = roleTitleNormFieldtype(roleTitleNormBoth(['default_title_structure' => 'unstructured']))->process([
        'mode' => $mode,
        'value' => 'Chief',
    ]);

    expect($out['mode'])->toBe($expected);
})->with([
    'padded unstructured' => [' unstructured ', 'unstructured'],
    'padded structured' => [' structured ', 'structured'],
    'unknown' => ['other', 'unstructured'],
    'empty' => ['', 'unstructured'],
    'not a string' => [['structured'], 'unstructured'],
]);

it('saves a structure that is not enabled as the first enabled one', function (): void {
    $out = roleTitleNormFieldtype(['enabled_title_structures' => ['structured']])->process([
        'mode' => 'unstructured',
        'configuration' => 'faculty',
        'role' => 'professor',
    ]);

    expect($out['mode'])->toBe('structured');
});

it('saves the trimmed configuration, or the default for a blank one', function (mixed $configuration, string $expected): void {
    $out = roleTitleNormFieldtype()->process(['configuration' => $configuration, 'role' => 'director']);

    expect($out['configuration'])->toBe($expected);
})->with([
    'padded' => [' academic_leadership ', 'academic_leadership'],
    'blank' => ['   ', 'faculty'],
    'not a string' => [['academic_leadership'], 'faculty'],
]);

it('saves title options as booleans, defaulting missing options to off', function (): void {
    $out = roleTitleNormFieldtype()->process([
        'configuration' => 'academic_leadership',
        'role' => 'director',
        'title_options' => ['interim' => 1, 'executive' => '1', 'regional' => 0, 'unknown' => true],
    ]);

    expect($out['title_options'])->toBe([
        'interim' => true,
        'co_prefix' => false,
        'regional' => false,
        'senior' => false,
        'executive' => true,
        'visiting' => false,
        'distinguished' => false,
        'adjunct' => false,
        'unknown' => true,
    ]);
});

it('saves title options that are not a map as every option off', function (): void {
    $out = roleTitleNormFieldtype()->process([
        'configuration' => 'academic_leadership',
        'role' => 'director',
        'title_options' => 'interim',
    ]);

    expect($out['title_options'])->toBe(roleTitleNormNoOptions());
});

it('saves optional radios trimmed, and blank, none or non-string radios as null', function (mixed $raw, ?string $expected): void {
    $out = roleTitleNormFieldtype()->process([
        'configuration' => 'faculty',
        'role' => 'professor',
        'hierarchical_modifier' => $raw,
    ]);

    expect($out['hierarchical_modifier'])->toBe($expected);
})->with([
    'padded' => [' associate ', 'associate'],
    'blank' => ['  ', null],
    'none sentinel' => ['__role_title_none__', null],
    'padded none sentinel' => [' __role_title_none__ ', null],
    'not a string' => [['associate'], null],
    'false' => [false, null],
]);

it('saves the scope group with each nested field in its own shape', function (array $scope, array $expected): void {
    $out = roleTitleNormFieldtype()->process([
        'configuration' => 'academic_leadership',
        'role' => 'director',
        'scope' => $scope,
    ]);

    expect($out['scope'])->toBe($expected);
})->with([
    'a typed scope' => [
        ['scope_joiner' => ' of ', 'scope_article' => ' the ', 'scope_name' => 'Surgery'],
        ['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_unit' => null, 'scope_name' => 'Surgery'],
    ],
    'an unknown joiner and a blank article' => [
        ['scope_joiner' => 'beside', 'scope_article' => ' ', 'scope_name' => 'Surgery'],
        ['scope_joiner' => 'for', 'scope_article' => null, 'scope_unit' => null, 'scope_name' => 'Surgery'],
    ],
    'a blank joiner and a name that is not a string' => [
        ['scope_joiner' => '  ', 'scope_name' => 7],
        ['scope_joiner' => 'for', 'scope_article' => null, 'scope_unit' => null, 'scope_name' => ''],
    ],
    'a joiner that is not a string' => [
        ['scope_joiner' => ['of']],
        ['scope_joiner' => 'for', 'scope_article' => null, 'scope_unit' => null, 'scope_name' => ''],
    ],
    'a linked unit, which clears the name and the article it replaces' => [
        ['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_unit' => ['unit-1'], 'scope_name' => 'Surgery'],
        ['scope_joiner' => 'of', 'scope_article' => null, 'scope_unit' => 'unit-1', 'scope_name' => ''],
    ],
]);

it('saves a scope that is not a map as the empty scope', function (): void {
    $out = roleTitleNormFieldtype()->process([
        'configuration' => 'academic_leadership',
        'role' => 'director',
        'scope' => 'Surgery',
    ]);

    expect($out['scope'])->toBe(['scope_joiner' => 'for', 'scope_article' => null, 'scope_unit' => null, 'scope_name' => '']);
});

it('clears the subfields the active configuration does not offer', function (): void {
    $out = roleTitleNormFieldtype()->process([
        'configuration' => 'institutional_leadership',
        'role' => 'president',
        'scope_qualifier' => 'clinical',
        'title_options' => ['visiting' => true, 'senior' => true],
    ]);

    expect($out['scope_qualifier'])->toBeNull()
        ->and(data_get($out, 'title_options.visiting'))->toBeFalse()
        ->and(data_get($out, 'title_options.senior'))->toBeTrue();
});

it('migrates a legacy honorific only when no honorific is already set', function (): void {
    $out = roleTitleNormFieldtype()->process([
        'configuration' => 'faculty',
        'role' => 'professor',
        'honorific' => 'emerita',
        'title_options' => ['emeritus' => true],
    ]);

    expect($out['honorific'])->toBe('emerita')
        ->and($out['title_options'])->not->toHaveKey('emeritus');
});

it('ignores a legacy honorific flag that is truthy but not true', function (): void {
    $out = roleTitleNormFieldtype()->process([
        'configuration' => 'faculty',
        'role' => 'professor',
        'title_options' => ['emeritus' => 1, 'emerita' => 'yes'],
    ]);

    expect($out['honorific'])->toBeNull();
});

it('enables only the listed handles, never a non-string entry', function (): void {
    $preload = roleTitleNormFieldtype([
        'enabled_title_structures' => [true],
        'enabled_configurations' => [true],
    ])->preload();

    expect($preload['enabled_title_structures'])->toBe(['structured'])
        ->and($preload['enabled_configurations'])->toBe(['faculty']);
});

it('saves a value through a detached fieldtype from the definition defaults', function (): void {
    $out = new RoleTitle()->process(['role' => 'professor']);

    expect($out['configuration'])->toBe('faculty')
        ->and($out['mode'])->toBe('structured')
        ->and($out['role'])->toBe('professor');
});

it('keeps a set honorific over a legacy flag naming the other form', function (): void {
    $out = roleTitleNormFieldtype()->process([
        'configuration' => 'faculty',
        'role' => 'professor',
        'honorific' => 'emeritus',
        'title_options' => ['emerita' => true],
    ]);

    expect($out['honorific'])->toBe('emeritus');
});

it('migrates a legacy honorific over a stored honorific that is not a string', function (): void {
    $out = roleTitleNormFieldtype()->process([
        'configuration' => 'faculty',
        'role' => 'professor',
        'honorific' => true,
        'title_options' => ['emerita' => true],
    ]);

    expect($out['honorific'])->toBe('emerita');
});

it('saves unstructured text that is not a string as empty', function (): void {
    $out = roleTitleNormFieldtype(roleTitleNormBoth())->process([
        'mode' => 'unstructured',
        'value' => 7,
    ]);

    expect($out['value'])->toBe('');
});

it('saves a structure that is not enabled as the only enabled one', function (mixed $default): void {
    $out = roleTitleNormFieldtype([
        'enabled_title_structures' => ['unstructured'],
        'default_title_structure' => $default,
    ])->process([
        'mode' => 'structured',
        'value' => 'Chief',
    ]);

    expect($out['mode'])->toBe('unstructured');
})->with([
    'no default' => [null],
    'a default that is not enabled' => ['structured'],
]);
