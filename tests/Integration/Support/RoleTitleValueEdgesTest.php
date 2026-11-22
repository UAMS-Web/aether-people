<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\RoleTitleDefinition;
use UAMSWeb\Aether\People\Support\RoleTitleUnstructuredReplicatorSupport;
use UAMSWeb\Aether\People\Support\RoleTitleValue;

/**
 * `RoleTitleValue` edges: the unstructured required-subfield list and storable
 * slice, the validation and preload key maps, composing each configuration
 * from padded, unknown and non-string values, and the object check (#130).
 *
 * Some cases override a translation, to show a composed line trims each
 * segment and binds every hyphen prefix to the role.
 *
 * @command  vendor/bin/pest tests/Integration/Support/RoleTitleValueEdgesTest.php
 */
beforeEach(function (): void {
    RoleTitleDefinition::forgetInstance();
});

afterEach(function (): void {
    RoleTitleDefinition::forgetInstance();
});

/**
 * Label new `role_title.field.option.*` keys for this test, after the add-on's own lines have loaded.
 *
 * The add-on's keys are flat dotted strings, which an added line cannot shadow, so
 * each case labels an option key the add-on does not ship.
 *
 * @param  array<string, string>  $labels  Option key to label
 */
function roleTitleValueLabelOptions(
    array $labels
): void {
    // Load the add-on's lines first, so the added lines join them instead of standing in for the file
    __('aether-people::fieldtypes.role_title.field.option.professor');

    $lines = [];
    foreach ($labels as $key => $label) {
        $lines['fieldtypes.role_title.field.option.'.$key] = $label;
    }

    app('translator')->addLines($lines, 'en', 'aether-people');
}

/**
 * Add an option row to a subfield of the loaded `role_title` config.
 *
 * @param  array<string, mixed>  $row  Option row
 */
function roleTitleValueAddOption(
    string $path,
    int|string $key,
    array $row = []
): void {
    /** @var array<int|string, mixed> $options */
    $options = config('role_title.subfields.'.$path.'.options');
    $options[$key] = $row;
    config()->set('role_title.subfields.'.$path.'.options', $options);
    RoleTitleDefinition::forgetInstance();
}

it('orders the required unstructured subfields canonically, or falls back to the free-text title', function (mixed $configured, array $expected): void {
    expect(RoleTitleValue::requiredUnstructuredSubfieldHandlesFromConfig(['required_unstructured_subfields' => $configured]))
        ->toBe($expected);
})->with([
    'every handle, shuffled, with an unknown one' => [
        ['occupational_field_same_as', 'value', 'occupation_isco_08', 'unknown', 'occupation_onet_soc', 'occupation_same_as'],
        ['value', 'occupation_same_as', 'occupation_onet_soc', 'occupation_isco_08', 'occupational_field_same_as'],
    ],
    'a value that matches every handle only loosely' => [[true], ['value']],
    'no known handle' => [['unknown'], ['value']],
    'a list that is not an array' => ['value', ['value']],
]);

it('builds the empty unstructured storable slice', function (): void {
    expect(RoleTitleValue::emptyUnstructuredStorableSlice())->toBe([
        'value' => '',
        'occupation' => [
            'same_as' => [],
            'category_code' => [
                'isco_08' => [],
                'onet_soc' => [],
            ],
        ],
        'occupational_field' => [
            'same_as' => [],
            'category_code' => [],
        ],
    ]);
});

it('normalizes the occupation branches of an unstructured payload only', function (): void {
    $structured = ['mode' => 'structured', 'occupation' => 'text'];
    $modeless = ['occupation' => 'text'];
    $slice = RoleTitleValue::emptyUnstructuredStorableSlice();

    $normalized = RoleTitleValue::normalizeUnstructuredOccupationBranchesForSchemaParity(
        ['mode' => 'unstructured', 'occupation' => 'text', 'occupational_field' => 5]
    );

    expect(RoleTitleValue::normalizeUnstructuredOccupationBranchesForSchemaParity($structured))->toBe($structured)
        ->and(RoleTitleValue::normalizeUnstructuredOccupationBranchesForSchemaParity($modeless))->toBe($modeless)
        ->and($normalized['occupation'])->toBe($slice['occupation'])
        ->and($normalized['occupational_field'])->toBe($slice['occupational_field']);
});

it('merges the Title Options keys of every configuration once each, in order', function (): void {
    config()->set('role_title.configurations.handles', ['a', 'b']);
    config()->set('role_title.subfields.title_options.options', [
        'shared' => ['available_in' => []],
        'only a' => ['available_in' => ['a']],
        'only b' => ['available_in' => ['b']],
    ]);
    RoleTitleDefinition::forgetInstance();

    expect(RoleTitleValue::validationAttributeOptionLangKeys())->toBe(['shared', 'only a', 'only b'])
        ->and(RoleTitleValue::storedTitleOptionBooleanKeys())->toBe(['shared', 'only a', 'only b'])
        ->and(RoleTitleValue::validationAttributeRelativePathToLangSuffix())->toBe([
            'mode' => 'mode',
            'configuration' => 'configuration',
            'value' => 'composed_value',
            'scope_qualifier' => 'scope_qualifier',
            'hierarchical_modifier' => 'hierarchical_modifier',
            'role' => 'role',
            'object' => 'object',
            'positional_qualifier' => 'positional_qualifier',
            'scope.scope_joiner' => 'scope_joiner',
            'scope.scope_article' => 'scope_article',
            'scope.scope_unit' => 'scope_unit',
            'scope.scope_name' => 'scope_name',
            'title_options.shared' => 'shared',
            'title_options.only a' => 'only a',
            'title_options.only b' => 'only b',
            'occupation.same_as' => 'unstructured.occupation_same_as',
            'occupation.category_code.isco_08' => 'unstructured.occupation_isco_08',
            'occupation.category_code.onet_soc' => 'unstructured.occupation_onet_soc',
            'occupational_field.same_as' => 'unstructured.occupational_field_same_as',
        ]);
});

it('orders the faculty Title Options prefix, inline, then suffix', function (): void {
    config()->set('role_title.subfields.title_options.options', [
        'after' => ['available_in' => ['faculty'], 'position' => 'suffix'],
        'within' => ['available_in' => ['faculty']],
        'before' => ['available_in' => ['faculty'], 'position' => 'prefix'],
        'not faculty' => ['available_in' => ['academic_leadership'], 'position' => 'prefix'],
    ]);
    RoleTitleDefinition::forgetInstance();

    expect(RoleTitleValue::facultyCheckboxOptionKeyOrder())->toBe(['before', 'within', 'after']);
});

it('adds the optional-none sentinel to the option keys a rule allows', function (): void {
    expect(RoleTitleValue::ruleInValuesWithOptionalNone(['a' => 'A', 'b' => 'B']))
        ->toBe(['a', 'b', RoleTitleValue::optionalSelectionNone()]);
});

it('preloads every slice the Vue fieldtype reads', function (): void {
    $meta = RoleTitleValue::fieldtypePreloadMetaFromDefinition();
    $definition = RoleTitleDefinition::instance();
    $configurationLabel = __('aether-people::fieldtypes.role_title.field.configuration');

    expect(array_keys($meta))->toBe([
        'option_label_maps',
        RoleTitleValue::PRELOAD_KEY_EMPTY_VALUE_SHARED,
        ...array_keys(RoleTitleUnstructuredReplicatorSupport::preloadReplicatorSlice()),
        'optional_radio_none',
        RoleTitleValue::PRELOAD_KEY_CONFIGURATION_HANDLES_CANONICAL,
        RoleTitleValue::PRELOAD_KEY_CONFIGURATION_LABELS,
        RoleTitleValue::PRELOAD_KEY_CP_EDITOR_BLOCKS_BY_CONFIGURATION,
        RoleTitleValue::PRELOAD_KEY_COMPOSE_CHECKLIST_BY_CONFIGURATION,
        RoleTitleValue::PRELOAD_KEY_OPTIONAL_RADIO_NONE_LABELS_BY_SUBFIELD,
        RoleTitleValue::PRELOAD_KEY_CP_SUBFIELD_LABELS,
        RoleTitleValue::PRELOAD_KEY_CP_NESTED_FIELD_LABELS,
        RoleTitleValue::PRELOAD_KEY_COMPOSE_CHECKLIST_CONFIGURATION_ALERT_LABEL,
        RoleTitleValue::PRELOAD_KEY_CONFIGURATION_FIELD_LABEL,
        RoleTitleValue::PRELOAD_KEY_STRUCTURED_MERGE_TOP_LEVEL_KEYS,
    ])
        ->and($meta['optional_radio_none'])->toBe(RoleTitleValue::optionalSelectionNone())
        ->and($meta[RoleTitleValue::PRELOAD_KEY_CONFIGURATION_LABELS])->toBe($definition->configurationLabelsForCpPreload())
        ->and($meta[RoleTitleValue::PRELOAD_KEY_COMPOSE_CHECKLIST_CONFIGURATION_ALERT_LABEL])->toBe($configurationLabel)
        ->and($meta[RoleTitleValue::PRELOAD_KEY_CONFIGURATION_FIELD_LABEL])->toBe($configurationLabel)
        ->and($meta[RoleTitleValue::PRELOAD_KEY_STRUCTURED_MERGE_TOP_LEVEL_KEYS])->toBe($definition->structuredTopLevelKeysForCpValueMerge());
});

it('composes nothing for an unstructured, unknown or missing configuration', function (array $data): void {
    /** @var array<string, mixed> $data */
    expect(RoleTitleValue::compose($data))->toBe('');
})->with([
    'an unstructured title' => [['mode' => 'unstructured', 'configuration' => 'faculty', 'role' => 'professor']],
    'an unknown configuration' => [['configuration' => 'unknown', 'role' => 'professor']],
    'a configuration that is not text' => [['configuration' => 5, 'role' => 'professor']],
]);

it('composes a faculty title from every slot, trimming each stored key', function (): void {
    roleTitleValueAddOption('title_options', 'tenured', ['available_in' => ['faculty']]);
    roleTitleValueAddOption('title_options', 'unused', ['available_in' => ['faculty']]);
    roleTitleValueLabelOptions(['tenured' => 'tenured', 'unused' => 'unused']);

    expect(RoleTitleValue::compose([
        'configuration' => 'faculty',
        'title_options' => ['visiting' => true, 'adjunct' => false, 'tenured' => true, 'unused' => false],
        'scope_qualifier' => 'clinical',
        'hierarchical_modifier' => ' associate ',
        'role' => ' professor ',
        'honorific' => 'emeritus',
        'scope' => ['scope_joiner' => ' of ', 'scope_name' => 'Medicine'],
    ]))->toBe('visiting tenured clinical associate professor emeritus of Medicine');
});

it('takes the article for a typed scope that chose it', function (string $article, string $expected): void {
    expect(RoleTitleValue::compose([
        'configuration' => 'faculty',
        'role' => 'professor',
        'scope' => ['scope_joiner' => 'of', 'scope_name' => 'Medicine', 'scope_article' => $article],
    ]))->toBe($expected);
})->with([
    'the article' => ['the', 'professor of the Medicine'],
    'a padded article' => [' the ', 'professor of the Medicine'],
    'another article' => ['a', 'professor of Medicine'],
]);

it('rejects a blank stored key and a blank joiner even when a host keys an option by it', function (): void {
    roleTitleValueAddOption('honorific', '');
    /** @var array<int|string, mixed> $joiners */
    $joiners = config('role_title.subfields.scope.fields.scope_joiner.options');
    $joiners[''] = ['available_in' => []];
    config()->set('role_title.subfields.scope.fields.scope_joiner.options', $joiners);
    RoleTitleDefinition::forgetInstance();

    expect(RoleTitleValue::compose([
        'configuration' => 'faculty',
        'role' => 'professor',
        'honorific' => '   ',
        'scope' => ['scope_joiner' => '  ', 'scope_name' => 'Medicine'],
    ]))->toBe('professor for Medicine');
});

it('reads a Title Option as on only when its map stores a truthy value', function (mixed $options, bool $expected): void {
    expect(RoleTitleValue::titleOptionIsSet(['title_options' => $options], 'visiting'))->toBe($expected);
})->with([
    'on' => [['visiting' => true], true],
    'off' => [['visiting' => false], false],
    'not stored' => [['adjunct' => true], false],
    'a map that is an object' => [new stdClass, false],
    'no map' => [null, false],
]);

it('composes an institutional leadership title from its modifiers', function (): void {
    expect(RoleTitleValue::compose([
        'configuration' => 'institutional_leadership',
        'hierarchical_modifier' => 'associate',
        'positional_qualifier' => 'vice',
        'role' => 'chancellor',
    ]))->toBe('associate vice chancellor');
});

it('falls back to the default joiner for one that is not an option', function (mixed $joiner): void {
    expect(RoleTitleValue::compose([
        'configuration' => 'faculty',
        'role' => 'professor',
        'scope' => ['scope_joiner' => $joiner, 'scope_name' => 'Medicine'],
    ]))->toBe('professor for Medicine');
})->with([
    'an unknown joiner' => ['across'],
    'a joiner that is not text' => [5],
    'a blank joiner' => ['  '],
]);

it('matches stored keys to option keys strictly', function (): void {
    roleTitleValueAddOption('honorific', 1);
    /** @var array<int|string, mixed> $joiners */
    $joiners = config('role_title.subfields.scope.fields.scope_joiner.options');
    $joiners[1] = ['available_in' => []];
    config()->set('role_title.subfields.scope.fields.scope_joiner.options', $joiners);
    RoleTitleDefinition::forgetInstance();

    expect(RoleTitleValue::compose([
        'configuration' => 'faculty',
        'role' => 'professor',
        'honorific' => '1',
        'scope' => ['scope_joiner' => '1', 'scope_name' => 'Medicine'],
    ]))->toBe('professor for Medicine');
});

it('binds every hyphen prefix to the role', function (): void {
    roleTitleValueAddOption('title_options', 'pro_prefix', ['available_in' => ['institutional_leadership'], 'position' => 'prefix']);
    roleTitleValueLabelOptions(['pro_prefix' => 'pro-']);

    expect(RoleTitleValue::compose([
        'configuration' => 'institutional_leadership',
        'title_options' => ['co_prefix' => true, 'pro_prefix' => true],
        'role' => 'director',
    ]))->toBe('co-pro-director');
});

it('drops the hyphen prefixes when the role label is blank', function (): void {
    roleTitleValueAddOption('role', 'blank_role', ['available_in' => ['institutional_leadership']]);
    roleTitleValueLabelOptions(['blank_role' => '  ']);

    expect(RoleTitleValue::compose([
        'configuration' => 'institutional_leadership',
        'title_options' => ['co_prefix' => true],
        'role' => 'blank_role',
    ]))->toBe('');
});

it('trims each segment before joining', function (): void {
    roleTitleValueAddOption('title_options', 'padded', ['available_in' => ['faculty'], 'position' => 'prefix']);
    roleTitleValueLabelOptions(['padded' => '  padded  ']);

    expect(RoleTitleValue::compose([
        'configuration' => 'faculty',
        'title_options' => ['padded' => true],
        'role' => 'professor',
    ]))->toBe('padded professor');
});

it('builds the empty value template for each mode', function (): void {
    $structured = RoleTitleValue::emptyValueTemplate('faculty');
    $unstructured = RoleTitleValue::emptyValueTemplate('faculty', RoleTitleValue::MODE_UNSTRUCTURED);
    $slice = RoleTitleValue::emptyUnstructuredStorableSlice();

    expect(array_slice($structured, 0, 2))->toBe(['configuration' => 'faculty', 'mode' => RoleTitleValue::MODE_STRUCTURED])
        ->and($structured['occupation'])->toBeNull()
        ->and($structured['value'])->toBeNull()
        ->and($unstructured['mode'])->toBe(RoleTitleValue::MODE_UNSTRUCTURED)
        ->and($unstructured['occupation'])->toBe($slice['occupation'])
        ->and($unstructured['occupational_field'])->toBe($slice['occupational_field'])
        ->and($unstructured['value'])->toBe('');
});

it('counts an object only in the configurations that compose one', function (mixed $title, bool $expected): void {
    expect(RoleTitleValue::objectSelected($title))->toBe($expected);
})->with([
    'academic leadership' => [['configuration' => 'academic_leadership', 'object' => 'department'], true],
    'clinical leadership' => [['configuration' => 'clinical_leadership', 'object' => 'service_line'], true],
    'faculty' => [['configuration' => 'faculty', 'object' => 'department'], false],
    'a configuration that matches only loosely' => [['configuration' => true, 'object' => 'department'], false],
    'no configuration' => [['object' => 'department'], false],
    'no object' => [['configuration' => 'academic_leadership'], false],
    'a blank object' => [['configuration' => 'academic_leadership', 'object' => '  '], false],
    'the optional-none sentinel' => [['configuration' => 'academic_leadership', 'object' => ' __role_title_none__ '], false],
    'an object that is not text' => [['configuration' => 'academic_leadership', 'object' => 5], false],
    'a title that is not an array' => ['text', false],
    'a title that is an object' => [new stdClass, false],
]);
