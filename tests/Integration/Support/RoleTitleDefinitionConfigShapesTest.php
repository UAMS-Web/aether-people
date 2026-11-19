<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\RoleTitleDefinition;

/**
 * `RoleTitleDefinition` against hand-built `role_title` configs: missing
 * sections, rows that are not arrays, and values of the wrong type, next to
 * well-formed rows, so each guard and each skipped row is seen to change the
 * result (#129).
 *
 * A row that is not an array is placed before a valid row on purpose: a skip
 * that stopped the walk instead of moving past the row would drop the valid one.
 *
 * Missing translation keys come back as the key itself, so the expected labels
 * below are the full keys the class builds.
 *
 * @command  vendor/bin/pest tests/Integration/Support/RoleTitleDefinitionConfigShapesTest.php
 */
beforeEach(function (): void {
    RoleTitleDefinition::forgetInstance();
});

afterEach(function (): void {
    RoleTitleDefinition::forgetInstance();
});

/**
 * Build a definition from a hand-written `role_title` config.
 *
 * @param  array<string, mixed>  $raw  The config to load
 */
function roleTitleDefinitionFromConfig(
    array $raw
): RoleTitleDefinition {
    config()->set('role_title', $raw);
    RoleTitleDefinition::forgetInstance();

    return RoleTitleDefinition::instance();
}

/**
 * The full translation key under `role_title.field`, as `__()` returns it when missing.
 */
function roleTitleFieldLabelKey(
    string $suffix
): string {
    return 'aether-people::fieldtypes.role_title.field.'.$suffix;
}

/**
 * The full translation key for an optional radio's none label, as `__()` returns it when missing.
 */
function roleTitleNoneLabelKey(
    string $key
): string {
    return 'aether-people::fieldtypes.role_title.option.none_'.$key;
}

it('reuses the cached definition until it is forgotten', function (): void {
    $first = RoleTitleDefinition::instance();

    config()->set('role_title', ['configurations' => ['handles' => ['other']]]);

    expect(RoleTitleDefinition::instance())->toBe($first);

    RoleTitleDefinition::forgetInstance();

    expect(RoleTitleDefinition::instance()->configurationHandles())->toBe(['other']);
});

it('refuses a sentinel that is not a non-empty string', function (mixed $sentinel): void {
    $definition = roleTitleDefinitionFromConfig(['sentinel' => $sentinel]);

    expect(fn (): string => $definition->optionalRadioNone())
        ->toThrow(RuntimeException::class, 'role_title.sentinel.optional_radio_none');
})->with([
    'an empty string' => [['optional_radio_none' => '']],
    'a number' => [['optional_radio_none' => 5]],
    'a section that is not a map' => ['text'],
    'no section' => [null],
]);

it('reads configuration handles only from a list of non-empty strings', function (mixed $configurations, array $expected): void {
    expect(roleTitleDefinitionFromConfig(['configurations' => $configurations])->configurationHandles())
        ->toBe($expected);
})->with([
    'no section' => [null, []],
    'a section that is not a map' => ['text', []],
    'handles that are not an array' => [['handles' => 'text'], []],
    'handles that are not a list' => [['handles' => ['first' => 'a']], []],
    'blank and non-string handles among real ones' => [['handles' => ['', 5, 'a', null, 'b']], ['a', 'b']],
]);

it('picks the default configuration handle', function (array $configurations, ?string $expected): void {
    expect(roleTitleDefinitionFromConfig(['configurations' => $configurations])->defaultConfigurationHandle())
        ->toBe($expected);
})->with([
    'a known default that is not first' => [['handles' => ['a', 'b'], 'default' => 'b'], 'b'],
    'an unknown default' => [['handles' => ['a', 'b'], 'default' => 'z'], 'a'],
    'a default that matches a handle only loosely' => [['handles' => ['1', 'b'], 'default' => '01'], '1'],
    'no default' => [['handles' => ['a', 'b']], 'a'],
    'an empty default' => [['handles' => ['a', 'b'], 'default' => ''], 'a'],
    'a default with no handles' => [['handles' => [], 'default' => 'z'], 'z'],
    'an empty default with no handles' => [['handles' => [], 'default' => ''], null],
    'a non-string default with no handles' => [['handles' => [], 'default' => 5], null],
    'no default and no handles' => [['handles' => []], null],
]);

it('keeps only array scenario rows from the schema', function (mixed $schema, array $expected): void {
    expect(roleTitleDefinitionFromConfig(['schema' => $schema])->schemaScenarioRows())->toBe($expected);
})->with([
    'a schema that is not a list' => ['text', []],
    'a row that is not an array before a row' => [['text', ['conditions' => []]], [['conditions' => []]]],
]);

it('enables per-configuration radios validation only on a radios row that sets it to true', function (string $key, bool $expected): void {
    $definition = roleTitleDefinitionFromConfig(['subfields' => [
        'checkboxes' => ['type' => 'checkboxes', 'validation' => ['per_configuration_options' => true]],
        'object validation' => ['type' => 'radios', 'validation' => new ArrayObject(['per_configuration_options' => true])],
        'empty validation' => ['type' => 'radios', 'validation' => []],
        'enabled' => ['type' => 'radios', 'validation' => ['per_configuration_options' => true]],
    ]]);

    expect($definition->radiosSubfieldUsesPerConfigurationOptionValidation($key))->toBe($expected);
})->with([
    'a checkboxes row' => ['checkboxes', false],
    'a validation section that is not an array' => ['object validation', false],
    'a validation section without the flag' => ['empty validation', false],
    'a radios row with the flag' => ['enabled', true],
]);

it('lists top-level subfield keys by type, skipping rows that are not arrays', function (): void {
    $definition = roleTitleDefinitionFromConfig(['subfields' => [
        'junk' => 'text',
        'boxes' => ['type' => 'checkboxes'],
        'choice' => ['type' => 'radios'],
        'nest' => ['type' => 'group'],
        'line' => ['type' => 'text'],
        'typeless' => [],
        'more boxes' => ['type' => 'checkboxes'],
        'more choice' => ['type' => 'radios'],
        'more nest' => ['type' => 'group'],
    ]]);

    expect($definition->checkboxSubfieldKeys())->toBe(['boxes', 'more boxes'])
        ->and($definition->topLevelRadiosSubfieldKeys())->toBe(['choice', 'more choice'])
        ->and($definition->groupSubfieldKeys())->toBe(['nest', 'more nest']);
});

it('returns empty results for every derived map when there are no subfields', function (mixed $subfields): void {
    $definition = roleTitleDefinitionFromConfig([
        'configurations' => ['handles' => ['a']],
        'subfields' => $subfields,
    ]);

    expect($definition->checkboxSubfieldKeys())->toBe([])
        ->and($definition->topLevelRadiosSubfieldKeys())->toBe([])
        ->and($definition->groupSubfieldKeys())->toBe([])
        ->and($definition->emptyValueSharedPayload())->toBe([])
        ->and($definition->inactiveSubfieldClearsForConfiguration('a'))->toBe([])
        ->and($definition->preloadSlotOrderFromSubfields())->toBe([])
        ->and($definition->structuredTopLevelKeysForCpValueMerge())->toBe([])
        ->and($definition->cpEditorBlocksByConfigurationForPreload())->toBe([])
        ->and($definition->cpSubfieldLabelsForPreload())->toBe([])
        ->and($definition->composeChecklistRulesByConfigurationForPreload())->toBe([]);
})->with([
    'no subfields' => [null],
    'subfields that are not a map' => ['text'],
]);

it('derives the empty value from each subfield row', function (): void {
    $definition = roleTitleDefinitionFromConfig(['subfields' => [
        'junk' => 'text',
        'explicit boxes' => ['type' => 'checkboxes', 'default' => ['a' => true], 'options' => ['a' => [], 'b' => []]],
        'empty boxes default' => ['type' => 'checkboxes', 'default' => [], 'options' => ['a' => [], 'b' => []]],
        'first choice' => ['type' => 'radios', 'options' => ['a' => [], 'b' => []]],
        'no choices' => ['type' => 'radios'],
        'known default' => ['type' => 'radios', 'default' => 'b', 'options' => ['a' => [], 'b' => []]],
        'empty default' => ['type' => 'radios', 'default' => '', 'options' => ['a' => [], '' => []]],
        'optional empty default' => ['type' => 'radios', 'allow_optional_none' => true, 'options' => ['a' => []]],
        'number default' => ['type' => 'radios', 'default' => 5, 'options' => ['a' => [], 5 => []]],
        'optional number default' => ['type' => 'radios', 'allow_optional_none' => true, 'default' => 5, 'options' => ['a' => []]],
        'unknown default' => ['type' => 'radios', 'default' => 'z', 'options' => ['a' => []]],
        'optional unknown default' => ['type' => 'radios', 'allow_optional_none' => true, 'default' => 'z', 'options' => ['a' => []]],
        'loose default' => ['type' => 'radios', 'default' => '1', 'options' => ['a' => [], 1 => []]],
        'text default' => ['type' => 'text', 'default' => 'hi'],
        'no text default' => ['type' => 'text'],
        'number text default' => ['type' => 'text', 'default' => 5],
        'line' => ['type' => 'composed_line', 'default' => 'composed'],
        'toggle' => ['type' => 'toggle', 'default' => true],
        'nest' => ['type' => 'group', 'fields' => [
            'junk' => 'text',
            'set' => ['type' => 'text', 'default' => 'v'],
            'unset' => ['type' => 'text'],
        ]],
        'nest without fields' => ['type' => 'group', 'fields' => 'text'],
        'nest with no fields' => ['type' => 'group', 'fields' => []],
    ]]);

    expect($definition->emptyValueSharedPayload())->toBe([
        'junk' => null,
        'explicit boxes' => ['a' => true],
        'empty boxes default' => ['a' => false, 'b' => false],
        'first choice' => 'a',
        'no choices' => '',
        'known default' => 'b',
        'empty default' => 'a',
        'optional empty default' => null,
        'number default' => 'a',
        'optional number default' => null,
        'unknown default' => 'a',
        'optional unknown default' => null,
        'loose default' => 'a',
        'text default' => 'hi',
        'no text default' => '',
        'number text default' => '',
        'line' => 'composed',
        'toggle' => true,
        'nest' => ['set' => 'v', 'unset' => ''],
        'nest without fields' => [],
        'nest with no fields' => [],
    ]);
});

it('clears the subfields a configuration excludes', function (): void {
    $definition = roleTitleDefinitionFromConfig(['subfields' => [
        'junk' => 'text',
        'boxes off' => ['type' => 'checkboxes', 'available_in' => ['other'], 'options' => ['a' => [], 'b' => []]],
        'boxes mixed' => ['type' => 'checkboxes', 'options' => [
            'a' => ['available_in' => ['mine']],
            'b' => ['available_in' => ['other']],
        ]],
        'line' => ['type' => 'composed_line', 'available_in' => ['other']],
        'choice off' => ['type' => 'radios', 'available_in' => ['other']],
        'nest off' => ['type' => 'group', 'available_in' => ['other'], 'fields' => ['set' => ['type' => 'text', 'default' => 'v']]],
        'choice on' => ['type' => 'radios', 'available_in' => ['mine']],
        'typeless off' => ['available_in' => ['other']],
    ]]);

    expect($definition->inactiveSubfieldClearsForConfiguration('mine'))->toBe([
        'boxes off' => ['a' => false, 'b' => false],
        'boxes mixed' => ['b' => false],
        'choice off' => null,
        'nest off' => ['set' => 'v'],
        'typeless off' => null,
    ]);
});

it('derives preload slots and their option keys from top-level and nested option fields', function (): void {
    $definition = roleTitleDefinitionFromConfig(['subfields' => [
        'junk' => 'text',
        'choice' => ['type' => 'radios', 'preload_slot' => 'choice slot', 'options' => ['a' => [], 'b' => []]],
        'number slot' => ['type' => 'radios', 'preload_slot' => 5, 'options' => ['a' => []]],
        'line' => ['type' => 'text'],
        'boxes' => ['type' => 'checkboxes', 'options' => ['x' => []]],
        'nest' => ['type' => 'group', 'fields' => [
            'junk' => 'text',
            'line' => ['type' => 'text'],
            'nested choice' => ['type' => 'radios', 'preload_slot' => 'nested slot', 'options' => ['p' => [], 'q' => []]],
            0 => ['type' => 'checkboxes', 'preload_slot' => 7, 'options' => ['z' => []]],
            'nested boxes' => ['type' => 'checkboxes', 'options' => ['y' => []]],
        ]],
        'nest without fields' => ['type' => 'group', 'fields' => 'text'],
        'last' => ['type' => 'radios', 'options' => ['l' => []]],
    ]]);

    expect($definition->preloadSlotOrderFromSubfields())->toBe([
        'choice slot',
        'number slot',
        'boxes',
        'nested slot',
        '0',
        'nested boxes',
        'last',
    ])->and($definition->optionLabelMapKeyListsByPreloadKey())->toBe([
        'choice slot' => ['a', 'b'],
        // A non-string `preload_slot` names the slot by its key, which no declared slot then matches
        'number slot' => [],
        'boxes' => ['x'],
        'nested slot' => ['p', 'q'],
        '0' => [],
        'nested boxes' => ['y'],
        'last' => ['l'],
    ]);
});

it('lists each preload option with the configurations it applies to', function (): void {
    $definition = roleTitleDefinitionFromConfig([
        'configurations' => ['handles' => ['a', 'b']],
        'subfields' => [
            'choice' => ['type' => 'radios', 'options' => [
                'everywhere' => [],
                'only b' => ['available_in' => ['b']],
                'nowhere' => ['available_in' => ['z']],
                'after' => [],
            ]],
            'nest' => ['type' => 'group', 'fields' => [
                'nested b' => ['type' => 'radios', 'available_in' => ['b'], 'options' => ['p' => []]],
                'nested all' => ['type' => 'radios', 'options' => [
                    'q' => [],
                    'not a row' => 'text',
                    'w' => ['available_in' => ['a']],
                ]],
            ]],
            'nest b' => ['type' => 'group', 'available_in' => ['b'], 'fields' => [
                'grouped' => ['type' => 'radios', 'options' => ['m' => []]],
            ]],
        ],
    ]);

    expect($definition->optionLabelOptionMetadataRowsForPreload())->toBe([
        'choice' => [
            ['key' => 'everywhere', 'available_in' => []],
            ['key' => 'only b', 'available_in' => ['b']],
            ['key' => 'after', 'available_in' => []],
        ],
        'nested b' => [
            ['key' => 'p', 'available_in' => ['b']],
        ],
        'nested all' => [
            ['key' => 'q', 'available_in' => []],
            ['key' => 'w', 'available_in' => ['a']],
        ],
        'grouped' => [
            ['key' => 'm', 'available_in' => ['b']],
        ],
    ]);
});

it('filters and places option keys by configuration and compose position', function (): void {
    $definition = roleTitleDefinitionFromConfig(['subfields' => [
        'choice' => ['type' => 'radios', 'options' => [
            'first' => ['position' => 'prefix'],
            'not a row' => 'text',
            'middle' => ['position' => 'middle'],
            'blank' => ['position' => ''],
            'number' => ['position' => 5],
            'last' => ['position' => 'suffix'],
            'number handle' => ['available_in' => [1]],
            'loose handle' => ['available_in' => ['01']],
            'object handle' => ['available_in' => [['x']]],
            'any' => ['available_in' => 'text'],
        ]],
    ]]);

    expect($definition->optionKeysForSubfieldAndConfiguration('choice', '1'))
        ->toBe(['first', 'middle', 'blank', 'number', 'last', 'number handle', 'any'])
        ->and($definition->optionKeysForSubfieldAndConfiguration('choice', ''))
        ->toBe(['first', 'middle', 'blank', 'number', 'last', 'object handle', 'any'])
        ->and($definition->subfieldOptionKeysForComposeSegment('choice', '1', 'prefix'))->toBe(['first'])
        ->and($definition->subfieldOptionKeysForComposeSegment('choice', '1', 'suffix'))->toBe(['last'])
        ->and($definition->subfieldOptionKeysForComposeSegment('choice', '1', 'inline'))
        ->toBe(['middle', 'blank', 'number', 'number handle', 'any']);
});

it('reads ordered option keys only from radios and checkboxes rows', function (): void {
    $definition = roleTitleDefinitionFromConfig(['subfields' => [
        'choice' => ['type' => 'radios', 'options' => ['a' => [], 'b' => []], 'fields' => [
            'nested' => ['type' => 'radios', 'options' => ['z' => []]],
        ]],
        'line' => ['type' => 'text', 'options' => ['o' => []]],
        'options not a map' => ['type' => 'radios', 'options' => 'text'],
        'nest' => ['type' => 'group', 'fields' => [
            'nested' => ['type' => 'radios', 'options' => [
                'a' => ['requires_article' => true],
                'b' => ['requires_article' => 'yes'],
                'c' => [],
                'd' => 'text',
            ]],
            'empty' => ['type' => 'radios'],
        ]],
        'object fields' => ['type' => 'group', 'fields' => new ArrayObject([
            'nested' => ['type' => 'radios', 'options' => ['k' => []]],
        ])],
    ]]);

    expect($definition->orderedOptionKeysForSubfield('missing'))->toBe([])
        ->and($definition->orderedOptionKeysForSubfield('choice'))->toBe(['a', 'b'])
        ->and($definition->orderedOptionKeysForSubfield('choice', 'nested'))->toBe([])
        ->and($definition->orderedOptionKeysForSubfield('line'))->toBe([])
        ->and($definition->orderedOptionKeysForSubfield('options not a map'))->toBe([])
        ->and($definition->orderedOptionKeysForSubfield('nest', 'missing'))->toBe([])
        ->and($definition->orderedOptionKeysForSubfield('object fields', 'nested'))->toBe([])
        ->and($definition->orderedOptionKeysForSubfield('nest', 'nested'))->toBe(['a', 'b', 'c', 'd'])
        ->and($definition->firstOrderedOptionKeyForNestedField('nest', 'nested'))->toBe('a')
        ->and($definition->firstOrderedOptionKeyForNestedField('nest', 'empty'))->toBe('')
        ->and($definition->articleRequiringOptionKeysForNestedField('nest', 'nested'))->toBe(['a']);
});

it('labels each configuration from the add-on translations', function (): void {
    expect(RoleTitleDefinition::instance()->configurationLabelsForCpPreload())->toBe([
        'faculty' => 'Faculty Role Title',
        'academic_leadership' => 'Academic Leadership Role Title',
        'institutional_leadership' => 'Institutional Leadership Role Title',
        'clinical_leadership' => 'Clinical Leadership Role Title',
    ]);
});

it('lists checkbox and group keys for the CP value merge, skipping rows that are not arrays', function (): void {
    $definition = roleTitleDefinitionFromConfig(['subfields' => [
        'junk' => 'text',
        'boxes' => ['type' => 'checkboxes'],
        'choice' => ['type' => 'radios'],
        'nest' => ['type' => 'group'],
    ]]);

    expect($definition->structuredTopLevelKeysForCpValueMerge())
        ->toBe(['boxes', 'nest', 'occupation', 'occupational_field']);
});

it('builds the CP editor blocks for each configuration', function (): void {
    $definition = roleTitleDefinitionFromConfig([
        'configurations' => ['handles' => ['a']],
        'subfields' => [
            'junk' => 'text',
            'value' => ['type' => 'composed_line'],
            'excluded' => ['type' => 'radios', 'available_in' => ['z']],
            'line' => ['type' => 'text'],
            'boxes' => ['type' => 'checkboxes', 'preload_slot' => 'boxes slot'],
            'more boxes' => ['type' => 'checkboxes'],
            'optional' => ['type' => 'radios', 'allow_optional_none' => true],
            'optional text' => ['type' => 'radios', 'allow_optional_none' => 'yes'],
            'required' => ['type' => 'radios'],
            'nest' => ['type' => 'group', 'fields' => [
                'junk' => 'text',
                'boxes' => ['type' => 'checkboxes'],
                'joiner' => [
                    'type' => 'radios',
                    'allow_optional_none' => true,
                    'preload_slot' => 'joiner slot',
                    'required_with_nested_field' => 'name',
                ],
                'blank sibling' => ['type' => 'radios', 'required_with_nested_field' => ''],
                'optional text' => ['type' => 'radios', 'allow_optional_none' => 'yes'],
                'name' => ['type' => 'text'],
                'link' => ['type' => 'entries'],
            ]],
            'nest without fields' => ['type' => 'group', 'fields' => 'text'],
        ],
    ]);

    expect($definition->cpEditorBlocksByConfigurationForPreload())->toBe(['a' => [
        ['type' => 'checkboxes', 'key' => 'boxes', 'option_map_key' => 'boxes slot'],
        ['type' => 'checkboxes', 'key' => 'more boxes', 'option_map_key' => 'more boxes'],
        ['type' => 'radios', 'key' => 'optional', 'allow_optional_none' => true],
        ['type' => 'radios', 'key' => 'optional text', 'allow_optional_none' => false],
        ['type' => 'radios', 'key' => 'required', 'allow_optional_none' => false],
        ['type' => 'group', 'key' => 'nest', 'nested' => [
            [
                'type' => 'radios',
                'nested_key' => 'joiner',
                'storage_path' => 'nest.joiner',
                'allow_optional_none' => true,
                'option_map_key' => 'joiner slot',
                'label_required_when_sibling_path_nonempty' => 'nest.name',
            ],
            [
                'type' => 'radios',
                'nested_key' => 'blank sibling',
                'storage_path' => 'nest.blank sibling',
                'allow_optional_none' => false,
                'option_map_key' => 'blank sibling',
            ],
            [
                'type' => 'radios',
                'nested_key' => 'optional text',
                'storage_path' => 'nest.optional text',
                'allow_optional_none' => false,
                'option_map_key' => 'optional text',
            ],
            ['type' => 'text', 'nested_key' => 'name', 'storage_path' => 'nest.name'],
            ['type' => 'entries', 'nested_key' => 'link', 'storage_path' => 'nest.link'],
        ]],
        ['type' => 'group', 'key' => 'nest without fields', 'nested' => []],
    ]]);
});

it('labels the none option of each optional radios field, top-level and nested', function (): void {
    $definition = roleTitleDefinitionFromConfig(['subfields' => [
        'optional' => ['type' => 'radios', 'allow_optional_none' => true],
        'required' => ['type' => 'radios'],
        'optional text' => ['type' => 'radios', 'allow_optional_none' => 'yes'],
        'nest' => ['type' => 'group', 'fields' => [
            'junk' => 'text',
            'line' => ['type' => 'text', 'allow_optional_none' => true],
            'first' => ['type' => 'radios', 'allow_optional_none' => true],
            'required' => ['type' => 'radios'],
            'second' => ['type' => 'radios', 'allow_optional_none' => true],
        ]],
        'nest without fields' => ['type' => 'group', 'fields' => 'text'],
        'last nest' => ['type' => 'group', 'fields' => [
            'inner' => ['type' => 'radios', 'allow_optional_none' => true],
        ]],
    ]]);

    expect($definition->optionalRadioNoneLabelsByTopLevelRadiosSubfield())->toBe([
        'optional' => roleTitleNoneLabelKey('optional'),
        'nest.first' => roleTitleNoneLabelKey('first'),
        'nest.second' => roleTitleNoneLabelKey('second'),
        'last nest.inner' => roleTitleNoneLabelKey('inner'),
    ]);
});

it('labels top-level option subfields from their declared label keys', function (): void {
    $definition = roleTitleDefinitionFromConfig(['subfields' => [
        'junk' => 'text',
        'line' => ['type' => 'text'],
        'nest' => ['type' => 'group'],
        'full key' => ['type' => 'checkboxes', 'cp_label' => 'custom.label.key'],
        'blank full key' => ['type' => 'checkboxes', 'cp_label' => '', 'cp_label_lang_suffix' => 'suffix'],
        'blank suffix' => ['type' => 'radios', 'cp_label_lang_suffix' => ''],
        'plain' => ['type' => 'radios'],
    ]]);

    expect($definition->cpSubfieldLabelsForPreload())->toBe([
        'full key' => 'custom.label.key',
        'blank full key' => roleTitleFieldLabelKey('suffix'),
        'blank suffix' => roleTitleFieldLabelKey('blank suffix'),
        'plain' => roleTitleFieldLabelKey('plain'),
    ]);
});

it('labels nested group fields by their dotted storage path', function (): void {
    $definition = roleTitleDefinitionFromConfig(['subfields' => [
        'nest' => ['type' => 'group', 'fields' => [
            'junk' => 'text',
            'boxes' => ['type' => 'checkboxes'],
            'choice' => ['type' => 'radios'],
            'name' => ['type' => 'text', 'cp_label_lang_suffix' => 'name suffix'],
            'link' => ['type' => 'entries'],
        ]],
        'nest without fields' => ['type' => 'group', 'fields' => 'text'],
        'last nest' => ['type' => 'group', 'fields' => ['inner' => ['type' => 'radios']]],
    ]]);

    expect($definition->cpNestedFieldLabelsForPreload())->toBe([
        'nest.choice' => roleTitleFieldLabelKey('choice'),
        'nest.name' => roleTitleFieldLabelKey('name suffix'),
        'nest.link' => roleTitleFieldLabelKey('link'),
        'last nest.inner' => roleTitleFieldLabelKey('inner'),
    ]);
});

it('builds the compose checklist rules for each configuration', function (): void {
    $definition = roleTitleDefinitionFromConfig([
        'configurations' => ['handles' => ['a']],
        'subfields' => [
            'junk' => 'text',
            'excluded' => ['type' => 'radios', 'available_in' => ['z']],
            'required' => ['type' => 'radios'],
            'optional' => ['type' => 'radios', 'allow_optional_none' => true],
            'line' => ['type' => 'text'],
            'nest without fields' => ['type' => 'group', 'fields' => 'text'],
            'nest' => ['type' => 'group', 'fields' => [
                'junk' => 'text',
                'name' => ['type' => 'text'],
                'excluded' => ['type' => 'radios', 'available_in' => ['z'], 'required_with_nested_field' => 'name'],
                'number sibling' => ['type' => 'radios', 'required_with_nested_field' => 5],
                'blank sibling' => ['type' => 'radios', 'required_with_nested_field' => ''],
                'no sibling' => ['type' => 'radios'],
                'joiner' => ['type' => 'radios', 'required_with_nested_field' => 'name', 'preload_slot' => 'joiner slot'],
                'second joiner' => ['type' => 'radios', 'required_with_nested_field' => 'name'],
            ]],
            'after' => ['type' => 'radios'],
        ],
    ]);

    expect($definition->composeChecklistRulesByConfigurationForPreload())->toBe(['a' => [
        ['path' => 'required', 'kind' => 'required_option', 'alert_label' => roleTitleFieldLabelKey('required')],
        ['path' => 'optional', 'kind' => 'optional_radio', 'alert_label' => roleTitleFieldLabelKey('optional')],
        [
            'path' => 'nest.joiner',
            'kind' => 'required_when_sibling_nonempty',
            'sibling_path' => 'nest.name',
            'option_preload_slot' => 'joiner slot',
            'alert_label' => roleTitleFieldLabelKey('joiner'),
        ],
        [
            'path' => 'nest.second joiner',
            'kind' => 'required_when_sibling_nonempty',
            'sibling_path' => 'nest.name',
            'option_preload_slot' => 'second joiner',
            'alert_label' => roleTitleFieldLabelKey('second joiner'),
        ],
        ['path' => 'after', 'kind' => 'required_option', 'alert_label' => roleTitleFieldLabelKey('after')],
    ]]);
});
