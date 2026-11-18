<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;
use Statamic\Entries\Entry;
use Statamic\Fields\Field;
use UAMSWeb\Aether\People\Contracts\OrganizationDisplayName;
use UAMSWeb\Aether\People\Fieldtypes\RoleTitle;
use UAMSWeb\Aether\People\Rules\RoleTitlePerConfigurationOptionRule;
use UAMSWeb\Aether\People\Support\RoleTitleDefinition;
use UAMSWeb\Aether\People\Support\RoleTitleSchemaOutputComputer;
use UAMSWeb\Aether\People\Support\RoleTitleValue;
use UAMSWeb\Aether\People\Tests\Fixtures\HostStandIns\OrganizationDisplayNameStandIn;

/**
 * Unit tests for `RoleTitle` fieldtype processing and `RoleTitleValue` compose helpers.
 *
 * Mutation: 100% on UAMSWeb\Aether\People\Fieldtypes\RoleTitle (482 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Integration/Fieldtypes/RoleTitleFieldtypeTest.php
 */
it('composes a faculty visiting associate professor emeritus title', function (): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'title_options' => [
            'visiting' => true,
            'distinguished' => false,
            'adjunct' => false,
        ],
        'honorific' => 'emeritus',
        'scope_qualifier' => null,
        'hierarchical_modifier' => 'associate',
        'role' => 'professor',
    ]);

    expect($out)->toBe('visiting associate professor emeritus');
});

it('collapses the space after a hyphenated prefix segment', function (): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        'title_options' => [
            'interim' => false,
            'co_prefix' => true,
            'regional' => false,
            'executive' => false,
        ],
        'hierarchical_modifier' => null,
        'object' => null,
        'positional_qualifier' => null,
        'role' => 'director',
        'scope' => [
            'scope_joiner' => 'for', 'scope_article' => 'the',
            'scope_name' => 'Academic Affairs',
        ],
    ]);

    expect($out)->toBe('co-director for the Academic Affairs');
});

/**
 * UAMS-Web/uams-statamic-archive#2145 dropped the object here and UAMS-Web/uams-statamic-archive#2546 restores it. `department` names the
 * vice chair's own unit while `Academic Affairs` names a portfolio, so nothing is
 * said twice -- the cross-type pairing UAMS-Web/uams-statamic-archive#2145 measured at 9 occurrences across 7
 * exports, which it over-blocked because an article-bearing joiner stood for
 * "names a unit". Since UAMS-Web/uams-statamic-archive#2546 only a linked unit names a unit, so a typed scope
 * that takes the article composes beside the object, and the check still cannot
 * be defeated by typing.
 */
it('composes an academic administrative title with scope joiner and name', function (): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        'title_options' => [
            'interim' => true,
            'co_prefix' => false,
            'regional' => false,
            'executive' => false,
        ],
        'hierarchical_modifier' => null,
        'object' => 'department',
        'positional_qualifier' => 'vice',
        'role' => 'chair',
        'scope' => [
            'scope_joiner' => 'for', 'scope_article' => 'the',
            'scope_name' => 'Academic Affairs',
        ],
    ]);

    expect($out)->toBe('interim department vice chair for the Academic Affairs');
});

it('composes an institutional administrative title with senior and of scope', function (): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP,
        'title_options' => [
            'interim' => false,
            'senior' => true,
            'co_prefix' => false,
            'regional' => false,
            'executive' => false,
        ],
        'hierarchical_modifier' => null,
        'positional_qualifier' => null,
        'role' => 'director',
        'scope' => [
            'scope_joiner' => 'of',
            'scope_name' => 'College of Medicine',
        ],
    ]);

    expect($out)->toBe('senior director of College of Medicine');
});

it('persists academic mode and strips faculty-only keys for academic', function (): void {
    $field = new Field(
        'academic_title',
        [
            'type' => 'role_title',
        ]
    );

    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'configuration' => RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        'title_options' => [
            'visiting' => true,
            'interim' => true,
        ],
        'role' => 'director',
        'object' => 'section',
        'scope' => [
            'scope_joiner' => 'for',
            'scope_name' => 'Clinical Education',
        ],
    ]);

    expect(data_get($out, 'title_options.visiting'))->toBeFalse();
    expect(data_get($out, 'title_options.interim'))->toBeTrue();
    expect(data_get($out, 'object'))->toBe('section');
    expect($out)->not->toHaveKey('value');

    expect(data_get($fieldtype->augment($out), 'value'))->toBe('interim section director for Clinical Education');
});

it('returns an empty string when compose input is not an array', function (): void {
    expect(RoleTitleValue::composeFromMixed(null))->toBe('');
});

it('maps optional-radio sentinel values to null during process', function (): void {
    $field = new Field(
        'faculty_title',
        [
            'type' => 'role_title',
        ]
    );

    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    $none = RoleTitleValue::optionalSelectionNone();

    $out = $fieldtype->process([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'scope_qualifier' => $none,
        'hierarchical_modifier' => $none,
        'role' => 'professor',
    ]);

    expect(data_get($out, 'scope_qualifier'))->toBeNull();
    expect(data_get($out, 'hierarchical_modifier'))->toBeNull();
    expect($out)->not->toHaveKey('value');

    expect(data_get($fieldtype->augment($out), 'value'))->toBe('professor');
});

it('coerces default configuration when only one mode is enabled in the blueprint', function (): void {
    $field = new Field(
        'primary_role',
        [
            'type' => 'role_title',
            'enabled_configurations' => [
                RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
            ],
            'default_configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        ]
    );

    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    $defaults = $fieldtype->defaultValue();

    expect(data_get($defaults, 'configuration'))->toBe(RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP);

    $preload = $fieldtype->preload();

    expect(data_get($preload, 'enabled_configurations'))->toBe([RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP]);
});

it('exposes php option label maps on fieldtype preload meta', function (): void {
    $field = new Field(
        'primary_role',
        [
            'type' => 'role_title',
        ]
    );

    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    $preload = $fieldtype->preload();

    $fromDefinition = RoleTitleValue::fieldtypePreloadMetaFromDefinition();

    // Build the preload slice in the same key order as the definition payload
    $slice = [];

    foreach (\array_keys($fromDefinition) as $metaKey) {
        $slice[$metaKey] = $preload[$metaKey];
    }

    expect($slice)->toBe($fromDefinition);
});

it('includes the configuration label and structured merge keys in preload meta', function (): void {
    $meta = RoleTitleValue::fieldtypePreloadMetaFromDefinition();

    expect($meta)->toHaveKey(RoleTitleValue::PRELOAD_KEY_CONFIGURATION_FIELD_LABEL);
    expect($meta[RoleTitleValue::PRELOAD_KEY_CONFIGURATION_FIELD_LABEL])->not->toBe('');
    expect($meta)->toHaveKey(RoleTitleValue::PRELOAD_KEY_STRUCTURED_MERGE_TOP_LEVEL_KEYS);
    expect($meta[RoleTitleValue::PRELOAD_KEY_STRUCTURED_MERGE_TOP_LEVEL_KEYS])->toContain('title_options');
    expect($meta[RoleTitleValue::PRELOAD_KEY_STRUCTURED_MERGE_TOP_LEVEL_KEYS])->toContain('scope');
    expect($meta[RoleTitleValue::PRELOAD_KEY_STRUCTURED_MERGE_TOP_LEVEL_KEYS])->toContain('occupation');
    expect($meta)->toHaveKey('unstructured_replicator_isco_config');
    expect($meta)->toHaveKey('unstructured_replicator_isco_meta');
});

it('keeps preload map key order aligned with declared preload order', function (): void {
    $maps = RoleTitleValue::optionLabelMapsForPreload();

    expect(\array_keys($maps))->toBe(RoleTitleDefinition::instance()->preloadSlotOrderFromSubfields());
});

it('builds title options preload as key label available_in rows in union order', function (): void {
    $maps = RoleTitleValue::optionLabelMapsForPreload();

    $rows = $maps[RoleTitleValue::PRELOAD_KEY_TITLE_OPTIONS];
    expect($rows)->not->toBe([]);

    $expectedKeys = RoleTitleValue::orderedOptionKeysForSubfield(
        RoleTitleValue::PRELOAD_KEY_TITLE_OPTIONS
    );
    expect(\array_column($rows, 'key'))->toBe($expectedKeys);

    $labels = RoleTitleValue::fieldOptionLabelsForKeys($expectedKeys);

    foreach ($rows as $row) {
        expect($row)->toHaveKey('key');
        expect($row)->toHaveKey('label');
        expect($row)->toHaveKey('available_in');
        expect($row['label'])->toBe($labels[$row['key']] ?? null);
    }
});

it('builds role preload as key label available_in rows', function (): void {
    $maps = RoleTitleValue::optionLabelMapsForPreload();

    $rows = $maps[RoleTitleValue::PRELOAD_KEY_ROLE];
    expect($rows)->not->toBe([]);

    $expectedKeys = RoleTitleValue::orderedOptionKeysForSubfield(
        RoleTitleValue::PRELOAD_KEY_ROLE
    );
    expect(\array_column($rows, 'key'))->toBe($expectedKeys);

    $labels = RoleTitleValue::fieldOptionLabelsForKeys($expectedKeys);

    foreach ($rows as $row) {
        expect($row)->toHaveKey('key');
        expect($row)->toHaveKey('label');
        expect($row)->toHaveKey('available_in');
        expect($row['label'])->toBe($labels[$row['key']] ?? null);
    }
});

it('aligns scope joiner option labels with option key order', function (): void {
    $joinerKeys = RoleTitleValue::orderedOptionKeysForSubfield(
        'scope',
        'scope_joiner'
    );

    $labels = RoleTitleValue::fieldOptionLabelsForKeys(
        $joinerKeys
    );

    expect(\array_keys($labels))->toBe($joinerKeys);
});

it('matches stored title option boolean keys to validation attribute option keys', function (): void {
    expect(RoleTitleValue::storedTitleOptionBooleanKeys())
        ->toBe(RoleTitleValue::validationAttributeOptionLangKeys());
});

it('maps validation attribute relative paths to lang suffixes', function (): void {
    $map = RoleTitleValue::validationAttributeRelativePathToLangSuffix();

    expect($map['value'])->toBe('composed_value');
    expect($map['scope.scope_joiner'])->toBe('scope_joiner');
    expect($map['title_options.visiting'])->toBe('visiting');
});

it('covers title options checkbox keys in validation attribute lang keys', function (): void {
    $keys = RoleTitleValue::validationAttributeOptionLangKeys();

    foreach (RoleTitleValue::facultyCheckboxOptionKeyOrder() as $key) {
        expect($keys)->toContain($key);
    }

    $byConfiguration = RoleTitleValue::mapOptionKeysByConfigurationHandles(
        RoleTitleValue::PRELOAD_KEY_TITLE_OPTIONS
    );

    foreach (
        $byConfiguration[RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP] as $key
    ) {
        expect($keys)->toContain($key);
    }

    foreach (
        $byConfiguration[RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP] as $key
    ) {
        expect($keys)->toContain($key);
    }
});

it('matches the empty value template to the fieldtype default value shape', function (): void {
    $field = new Field(
        'primary_role',
        [
            'type' => 'role_title',
        ]
    );

    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    expect($fieldtype->defaultValue())
        ->toBe(RoleTitleValue::emptyValueTemplate(RoleTitleValue::CONFIGURATION_FACULTY));
});

it('keeps optional radios nullable when the parent field is required', function (): void {
    $field = new Field(
        'primary_role',
        [
            'type' => 'role_title',
            'required' => true,
        ]
    );

    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    $rules = $fieldtype->extraRules();

    expect($rules['primary_role.scope_qualifier'])->toContain('nullable');
    expect($rules['primary_role.scope_qualifier'])->not->toContain('required_if:');
    expect($rules['primary_role.hierarchical_modifier'])->toContain('nullable');
    expect($rules['primary_role.hierarchical_modifier'])->not->toContain('required_if:');
    expect($rules['primary_role.object'][0])->toBe('nullable');
    expect($rules['primary_role.object'])->not->toContain('required_if:');
    expect($rules['primary_role.positional_qualifier'][0])->toBe('nullable');
    expect($rules['primary_role.positional_qualifier'])->not->toContain('required_if:');
    expect($rules['primary_role.scope.scope_name'])->toContain('nullable');
    expect($rules['primary_role.scope.scope_name'])->toContain('string');
    expect($rules['primary_role.scope.scope_joiner'])
        ->toContain('required_with:{this}.primary_role.scope.scope_name');
    expect($rules['primary_role.configuration'])
        ->toContain('required_if:{this}.primary_role.mode,structured');
    expect($rules['primary_role.title_options.visiting'])->toBe(['boolean']);
});

it('uses this placeholder for cross-field targets when nested under a replicator', function (): void {
    $replicator = new Field(
        'title_replicator',
        [
            'type' => 'replicator',
        ]
    );

    $title = new Field(
        'title',
        [
            'type' => 'role_title',
            'required' => true,
        ]
    );

    $title->setParentField(
        $replicator,
        0
    );

    $fieldtype = $title->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($title);

    $rules = $fieldtype->extraRules();

    expect($rules['title.scope_qualifier'])->toContain('nullable');
    expect($rules['title.scope.scope_joiner'])
        ->toContain('required_with:{this}.title.scope.scope_name');
});

it('reads the parent payload via a dotted path for the role rule', function (): void {
    $rule = new RoleTitlePerConfigurationOptionRule(
        'title_replicator.0.title',
        true,
        'role'
    );

    $rule->setData([
        'title_replicator' => [
            [
                'title' => [
                    'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
                    'role' => 'professor',
                ],
            ],
        ],
    ]);

    $failed = false;

    $rule->validate(
        'title_replicator.0.title.role',
        'professor',
        static function (string $attribute, ?string $message = null) use (&$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString((string) $message, app(Translator::class));
        }
    );

    expect($failed)->toBeFalse();
});

it('requires the role for institutional mode when the parent is required', function (): void {
    $rule = new RoleTitlePerConfigurationOptionRule(
        'title',
        true,
        'role'
    );

    $rule->setData([
        'title' => [
            'configuration' => RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP,
            'role' => null,
        ],
    ]);

    $failed = false;

    $rule->validate(
        'title.role',
        null,
        static function (string $attribute, ?string $message = null) use (&$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString((string) $message, app(Translator::class));
        }
    );

    expect($failed)->toBeTrue();
});

it('requires the role for faculty mode when the parent is required', function (): void {
    $rule = new RoleTitlePerConfigurationOptionRule(
        'title',
        true,
        'role'
    );

    $rule->setData([
        'title' => [
            'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
            'role' => null,
        ],
    ]);

    $failed = false;

    $rule->validate(
        'title.role',
        null,
        static function (string $attribute, ?string $message = null) use (&$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString((string) $message, app(Translator::class));
        }
    );

    expect($failed)->toBeTrue();
});

it('hides default configuration until more than one mode is enabled', function (): void {
    $reflection = new ReflectionClass(RoleTitle::class);
    $method = $reflection->getMethod('configFieldItems');

    /** @var array<int, array<string, mixed>> $configFieldItems */
    $configFieldItems = $method->invoke(new RoleTitle);

    $defaultConfiguration = null;

    foreach ($configFieldItems as $section) {
        if (! isset($section['fields']) || ! \is_array($section['fields'])) {
            continue;
        }

        if (isset($section['fields']['default_configuration'])) {
            $defaultConfiguration = $section['fields']['default_configuration'];

            break;
        }
    }

    expect($defaultConfiguration)->toBeArray();
    expect($defaultConfiguration)->toHaveKey('if');
    expect(data_get($defaultConfiguration, 'if'))->toBe([
        'enabled_title_structures' => 'custom roleTitleDefaultConfigurationVisible',
    ]);
});

it('requires structured mode for the enabled configurations blueprint field', function (): void {
    $reflection = new ReflectionClass(RoleTitle::class);
    $method = $reflection->getMethod('configFieldItems');

    /** @var array<int, array<string, mixed>> $configFieldItems */
    $configFieldItems = $method->invoke(new RoleTitle);

    $enabledConfigurations = null;

    foreach ($configFieldItems as $section) {
        if (! isset($section['fields']) || ! \is_array($section['fields'])) {
            continue;
        }

        if (isset($section['fields']['enabled_configurations'])) {
            $enabledConfigurations = $section['fields']['enabled_configurations'];

            break;
        }
    }

    expect($enabledConfigurations)->toBeArray();
    expect($enabledConfigurations)->toHaveKey('if');
    expect(data_get($enabledConfigurations, 'if'))->toBe([
        'enabled_title_structures' => 'custom selectionContains:structured',
    ]);
});

it('persists the unstructured value and strips cp keys on process', function (): void {
    $field = new Field(
        'title',
        [
            'type' => 'role_title',
            'enabled_title_structures' => [
                RoleTitleValue::MODE_UNSTRUCTURED,
            ],
        ]
    );

    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'mode' => RoleTitleValue::MODE_UNSTRUCTURED,
        'value' => ' Media Relations Manager ',
        'occupation' => [
            'same_as' => ['https://example.com/a'],
            'category_code' => [
                'onet_soc' => ['11-1011.00'],
                'isco_08' => ['1112'],
            ],
        ],
        'occupational_field' => [
            'same_as' => ['https://example.com/b'],
        ],
        '__cp_role_title_unstructured_isco_08' => [],
    ]);

    expect(data_get($out, 'mode'))->toBe(RoleTitleValue::MODE_UNSTRUCTURED);
    expect(data_get($out, 'value'))->toBe('Media Relations Manager');
    expect(data_get($out, 'occupation.same_as'))->toBe(['https://example.com/a']);
    expect(data_get($out, 'occupation.category_code.onet_soc'))->toBe(['11-1011.00']);
    expect(data_get($out, 'occupation.category_code.isco_08'))->toBe([]);
    expect(data_get($out, 'occupational_field.same_as'))->toBe(['https://example.com/b']);
    expect(data_get($out, 'occupational_field.category_code'))->toBe([]);
    expect($out)->not->toHaveKey('__cp_role_title_unstructured_isco_08');
});

it('strips the value and includes the mode on structured process', function (): void {
    $field = new Field('title', ['type' => 'role_title']);
    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'mode' => RoleTitleValue::MODE_STRUCTURED,
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'value' => 'should not persist',
        'role' => 'professor',
    ]);

    expect(data_get($out, 'mode'))->toBe(RoleTitleValue::MODE_STRUCTURED);
    expect($out)->not->toHaveKey('value');
});

it('includes title structure keys in preload', function (): void {
    $field = new Field(
        'title',
        [
            'type' => 'role_title',
            'enabled_title_structures' => [
                RoleTitleValue::MODE_STRUCTURED,
                RoleTitleValue::MODE_UNSTRUCTURED,
            ],
            'default_title_structure' => RoleTitleValue::MODE_UNSTRUCTURED,
        ]
    );

    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    $preload = $fieldtype->preload();

    expect(data_get($preload, 'enabled_title_structures'))
        ->toBe([RoleTitleValue::MODE_STRUCTURED, RoleTitleValue::MODE_UNSTRUCTURED]);
    expect(data_get($preload, 'default_title_structure'))->toBe(RoleTitleValue::MODE_UNSTRUCTURED);
    expect(data_get($preload, 'required_unstructured_subfields'))->toBe(['value']);
});

it('defaults required unstructured subfields to value only', function (): void {
    expect(RoleTitleValue::requiredUnstructuredSubfieldHandlesFromConfig([]))->toBe(['value']);
    expect(RoleTitleValue::requiredUnstructuredSubfieldHandlesFromConfig([
        'required_unstructured_subfields' => [],
    ]))->toBe(['value']);
    expect(RoleTitleValue::requiredUnstructuredSubfieldHandlesFromConfig([
        'required_unstructured_subfields' => [
            'occupation_same_as',
            'value',
        ],
    ]))->toBe([
        'value',
        'occupation_same_as',
    ]);
});

it('makes the required unstructured subfields blueprint field conditional', function (): void {
    $reflection = new ReflectionClass(RoleTitle::class);
    $method = $reflection->getMethod('configFieldItems');

    /** @var array<int, array<string, mixed>> $configFieldItems */
    $configFieldItems = $method->invoke(new RoleTitle);

    $row = null;

    foreach ($configFieldItems as $section) {
        if (! isset($section['fields']) || ! \is_array($section['fields'])) {
            continue;
        }

        if (isset($section['fields']['required_unstructured_subfields'])) {
            $row = $section['fields']['required_unstructured_subfields'];

            break;
        }
    }

    expect($row)->toBeArray();
    expect(data_get($row, 'if'))->toBe([
        'enabled_title_structures' => 'custom selectionContains:unstructured',
    ]);
});

it('composes a clinical leadership service line medical director title', function (): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        'title_options' => [
            'interim' => false,
            'co_prefix' => false,
        ],
        'hierarchical_modifier' => null,
        'object' => 'service_line',
        'role' => 'medical_director',
    ]);

    // Since UAMS-Web/uams-statamic-archive#2143 the unit is an object rather than part of the role key. The composed
    // output is unchanged, which is the point of the decomposition.
    expect($out)->toBe('service line medical director');
});

it('composes clinical prefix options ahead of the hierarchical modifier', function (): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        'title_options' => [
            'interim' => true,
            'co_prefix' => false,
        ],
        'hierarchical_modifier' => 'associate',
        'object' => 'service_line',
        'role' => 'medical_director',
    ]);

    expect($out)->toBe('interim associate service line medical director');
});

it('composes every clinical leadership role option to its label', function (string $role, string $expected): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        'title_options' => [
            'interim' => false,
            'co_prefix' => false,
        ],
        'hierarchical_modifier' => null,
        'object' => 'service_line',
        'role' => $role,
    ]);

    expect($out)->toBe($expected);
})->with([
    ['administrator', 'service line administrator'],
    ['director', 'service line director'],
    ['medical_director', 'service line medical director'],
    ['nursing_director', 'service line nursing director'],
]);

it('ignores subfields excluded from clinical leadership by available_in', function (): void {
    $payload = [
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        'title_options' => [
            'interim' => false,
            'co_prefix' => false,
        ],
        'hierarchical_modifier' => 'associate',
        'object' => 'service_line',
        'role' => 'director',
    ];

    // Neither `scope` nor `object` belongs in this list since UAMS-Web/uams-statamic-archive#2143: both rows are now
    // available to clinical leadership, so a value in either composes rather than being
    // ignored. This case is about SUBFIELDS the configuration excludes; an academic object
    // VALUE under clinical is a different exclusion and has its own case below.
    $stale = [
        ...$payload,
        'scope_qualifier' => 'clinical',
        'positional_qualifier' => 'vice',
    ];

    // Both arms assert the literal line rather than only each other: two empty
    // strings compare equal and would pass while proving nothing.
    // `department` is academic-only, so it stays ignored here even though UAMS-Web/uams-statamic-archive#2143 made the
    // clinical composer read the object slot: `service_line` is the only option that
    // composes under this configuration.
    expect(RoleTitleValue::compose($payload))->toBe('associate service line director');
    expect(RoleTitleValue::compose($stale))->toBe('associate service line director');
});

it('ignores an object value belonging to another configuration', function (): void {
    $out = RoleTitleValue::compose([
        'mode' => 'structured',
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        'hierarchical_modifier' => 'associate',
        // `department` is academic-only. Since UAMS-Web/uams-statamic-archive#2143 the object ROW is available to clinical
        // leadership, so the exclusion that matters moved from the row to the option: the
        // segment must drop rather than compose an academic unit under a clinical title.
        'object' => 'department',
        'role' => 'director',
    ]);

    expect($out)->toBe('associate director');
});

it('ignores title options another configuration owns', function (): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        'title_options' => [
            // `senior` is institutional-only and the `honorific` slot excludes clinical,
            // so a payload carrying them stale must compose as though they were absent.
            'senior' => true,
            'interim' => false,
        ],
        'honorific' => 'emeritus',
        'hierarchical_modifier' => null,
        'object' => 'service_line',
        'role' => 'director',
    ]);

    expect($out)->toBe('service line director');
});

it('drops a role belonging to another configuration', function (): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        'title_options' => [
            'interim' => false,
            'co_prefix' => false,
        ],
        'hierarchical_modifier' => 'associate',
        // `dean` is an institutional role and is not in clinical's option list.
        'role' => 'dean',
    ]);

    expect($out)->toBe('associate');
});

it('binds the co- prefix to a clinical role behind its service line object', function (): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        'title_options' => [
            'interim' => false,
            'co_prefix' => true,
        ],
        'hierarchical_modifier' => null,
        'object' => 'service_line',
        'role' => 'director',
    ]);

    // INVERTED from UAMS-Web/uams-statamic-archive#2136's characterization, which pinned `co-service line director` and
    // named UAMS-Web/uams-statamic-archive#2139 as the owner. Two changes were needed and only together: UAMS-Web/uams-statamic-archive#2139 bound the
    // prefix to the role rather than to the next fragment, and UAMS-Web/uams-statamic-archive#2143 decomposed the unit
    // into an object so there is a fragment between them at all. UAMS-Web/uams-statamic-archive#2143 records that the
    // pinned form has zero attestations in the content exports against 23 for this one.
    expect($out)->toBe('service line co-director');
});

it('composes a non-empty title for every configured configuration handle', function (): void {
    $payloads = [
        RoleTitleValue::CONFIGURATION_FACULTY => [
            'title_options' => ['visiting' => true],
            'honorific' => 'emeritus',
            'scope_qualifier' => 'clinical',
            'hierarchical_modifier' => 'associate',
            'role' => 'professor',
        ],
        RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP => [
            'title_options' => ['interim' => true],
            'hierarchical_modifier' => 'associate',
            'object' => 'department',
            'positional_qualifier' => 'vice',
            'role' => 'chair',
            'scope' => ['scope_joiner' => 'for', 'scope_article' => 'the', 'scope_name' => 'Academic Affairs'],
        ],
        RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP => [
            'title_options' => ['senior' => true],
            'hierarchical_modifier' => 'associate',
            'positional_qualifier' => 'vice',
            'role' => 'director',
            'scope' => ['scope_joiner' => 'of', 'scope_name' => 'College of Medicine'],
        ],
        RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP => [
            'title_options' => ['interim' => true],
            'hierarchical_modifier' => 'associate',
            'object' => 'service_line',
            'role' => 'medical_director',
        ],
    ];

    /** @var list<string> $handles */
    $handles = config('role_title.configurations.handles');

    // A fifth handle added without a composer reds here, before the loop runs.
    expect(array_keys($payloads))->toEqualCanonicalizing($handles);

    foreach ($handles as $handle) {
        expect(RoleTitleValue::compose([...$payloads[$handle], 'configuration' => $handle]))
            ->not->toBe('');
    }
});

it('resolves health administrator schema output for every service line role', function (string $role): void {
    $out = RoleTitleSchemaOutputComputer::computeFromNormalizedPayload([
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        'hierarchical_modifier' => 'associate',
        'object' => 'service_line',
        'role' => $role,
    ]);

    // Since UAMS-Web/uams-statamic-archive#2143 the rule keys on the role AND the `service_line` object. `director` is a
    // bare key shared with academic and institutional leadership, so the role alone can no
    // longer identify a clinical title.
    expect(data_get($out, 'occupation.same_as'))
        ->toContain('https://www.wikidata.org/wiki/Q26701778');
    expect(data_get($out, 'occupation.category_code.onet_soc'))
        ->toContain('11-9111.00');
})->with([
    'administrator',
    'director',
    'medical_director',
    'nursing_director',
]);

it('does not give an academic or institutional director the clinical occupation', function (string $configuration): void {
    $out = RoleTitleSchemaOutputComputer::computeFromNormalizedPayload([
        'configuration' => $configuration,
        'role' => 'director',
    ]);

    // The bare `director` key is shared across three configurations since UAMS-Web/uams-statamic-archive#2143. Without the
    // `service_line` object condition, matching on the role alone would hand a department
    // director the health-administrator occupation.
    expect(data_get($out, 'occupation.same_as') ?? [])
        ->not->toContain('https://www.wikidata.org/wiki/Q26701778');
})->with([
    RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
    RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP,
]);

it('composes a clinical title from the decomposed service line object', function (array $payload, string $expected): void {
    /** @var array<string, mixed> $composed */
    $composed = [
        'mode' => 'structured',
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        ...$payload,
    ];

    expect(RoleTitleValue::compose($composed))->toBe($expected);
})->with([
    'plain' => [['object' => 'service_line', 'role' => 'medical_director'], 'service line medical director'],
    'plain administrator' => [['object' => 'service_line', 'role' => 'administrator'], 'service line administrator'],
    'shared' => [
        ['object' => 'service_line', 'title_options' => ['co_prefix' => true], 'role' => 'director'],
        'service line co-director',
    ],
    'interim shared' => [
        ['object' => 'service_line', 'title_options' => ['interim' => true, 'co_prefix' => true], 'role' => 'medical_director'],
        'interim service line co-medical director',
    ],
    'named through the scope instead' => [
        ['role' => 'medical_director', 'scope' => ['scope_name' => 'Cancer Service Line', 'scope_joiner' => 'of', 'scope_article' => 'the']],
        'medical director of the Cancer Service Line',
    ],
]);

it('does not compose the object and the scope for the same unit', function (): void {
    // A linked service line: the unit the scope names (UAMS-Web/uams-statamic-archive#2546)
    $unit = new class extends Entry
    {
        public function __construct() {}

        /**
         * @param  string  $key
         * @return mixed
         */
        public function value($key)
        {
            return $key === 'public_label' ? 'Cancer Service Line' : null;
        }

        /**
         * @param  string|null  $id
         * @return string
         */
        public function id($id = null)
        {
            return 'cancer';
        }

        public function collectionHandle(): string
        {
            return 'organizations';
        }
    };
    app()->bind(OrganizationDisplayName::class, OrganizationDisplayNameStandIn::class);
    Statamic\Facades\Entry::partialMock()
        ->shouldReceive('find')
        ->andReturnUsing(static fn (string $id): ?Entry => $id === 'cancer' ? $unit : null);

    $out = RoleTitleValue::compose([
        'mode' => 'structured',
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        'object' => 'service_line',
        'role' => 'medical_director',
        'scope' => ['scope_joiner' => 'of', 'scope_unit' => 'cancer', 'scope_name' => ''],
    ]);

    // UAMS-Web/uams-statamic-archive#2143 makes the scope an alternative to the object rather than an addition to it. The
    // specific naming wins, so the doubled form is not composable.
    expect($out)->toBe('medical director of the Cancer Service Line');
    expect($out)->not->toContain('service line medical director');
});

it('binds the co- prefix to the role across intervening slots', function (string $slot, string $value, string $expected): void {
    $out = RoleTitleValue::compose([
        'mode' => 'structured',
        'configuration' => RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        'title_options' => ['co_prefix' => true],
        $slot => $value,
        'role' => 'director',
    ]);

    // `co-` is a bound prefix of the ROLE. Before UAMS-Web/uams-statamic-archive#2139 it glued to whichever fragment
    // followed it in the segment list, so an object, hierarchical modifier or positional
    // qualifier sitting between the two captured it instead.
    expect($out)->toBe($expected);
})->with([
    'object' => ['object', 'department', 'department co-director'],
    'hierarchical modifier' => ['hierarchical_modifier', 'associate', 'associate co-director'],
    'positional qualifier' => ['positional_qualifier', 'vice', 'vice co-director'],
]);

it('leaves an editor-typed scope name with its own hyphens intact', function (): void {
    $out = RoleTitleValue::compose([
        'mode' => 'structured',
        'configuration' => RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        'role' => 'chair',
        'scope' => ['scope_name' => 'Jones Eye - Little Rock'],
    ]);

    // Before UAMS-Web/uams-statamic-archive#2139 the glue ran over the finished line, so any `- ` an editor typed was
    // closed up: "Jones Eye -Little Rock". Silent corruption of a human-supplied string.
    expect($out)->toContain('Jones Eye - Little Rock');
});

it('composes nothing from a co- prefix with no role to bind to', function (): void {
    $out = RoleTitleValue::compose([
        'mode' => 'structured',
        'configuration' => RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        'title_options' => ['co_prefix' => true],
    ]);

    // A bound prefix is not a title on its own; it must not compose to a bare `co-`.
    expect($out)->not->toContain('co-');
});

/*
 | Faculty scope and the `in the` joiner (UAMS-Web/uams-statamic-archive#2145).
 |
 | Faculty carries a scope more often than any other configuration and had no
 | slot for one. Its dominant phrasing, `in the department of ...`, was absent
 | from a vocabulary that offered only `of` and `for`.
 */

it('composes a faculty title with the in-the joiner', function (): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'role' => 'professor',
        'scope' => [
            'scope_joiner' => 'in', 'scope_article' => 'the',
            'scope_name' => 'Department of Internal Medicine',
        ],
    ]);

    expect($out)->toBe('professor in the Department of Internal Medicine');
});

it('orders a faculty scope after the honorific, not before it', function (): void {
    // The honorific composes immediately after the role and the scope last, so the
    // reading is `professor emeritus of medicine`, not `professor of medicine emeritus`.
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'role' => 'professor',
        'honorific' => 'emeritus',
        'scope' => [
            'scope_joiner' => 'of',
            'scope_name' => 'medicine',
        ],
    ]);

    expect($out)->toBe('professor emeritus of medicine');
});

it('leaves a faculty title unchanged when the scope has no name', function (): void {
    // An empty scope name drops the phrase entirely, so every faculty title that
    // predates this change composes byte-identically.
    $withEmptyScope = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'role' => 'professor',
        'scope' => ['scope_joiner' => 'in', 'scope_article' => 'the', 'scope_name' => ''],
    ]);

    $withNoScopeKey = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'role' => 'professor',
    ]);

    expect($withEmptyScope)->toBe('professor')
        ->and($withNoScopeKey)->toBe('professor');
});

it('keeps the four original scope phrasings rendering as they did', function (): void {
    // A regression guard on the vocabulary rather than on faculty: the four phrasings
    // the five combined joiners composed still compose from a preposition and an
    // article (UAMS-Web/uams-statamic-archive#2546).
    $compose = fn (string $joiner, ?string $article): string => RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'role' => 'professor',
        'scope' => ['scope_joiner' => $joiner, 'scope_article' => $article, 'scope_name' => 'medicine'],
    ]);

    expect($compose('of', null))->toBe('professor of medicine')
        ->and($compose('of', 'the'))->toBe('professor of the medicine')
        ->and($compose('for', null))->toBe('professor for medicine')
        ->and($compose('for', 'the'))->toBe('professor for the medicine');
});

it('composes the honorific immediately after the role in every configuration that offers it', function (string $configuration, array $payload, string $expected): void {
    // UAMS-Web/uams-statamic-archive#2138: `emeritus` / `emerita` compose right after the role in each configuration
    // that offers the slot -- faculty, academic leadership and institutional leadership.
    $out = RoleTitleValue::compose(['configuration' => $configuration, 'honorific' => 'emeritus'] + $payload);

    expect($out)->toBe($expected);
})->with([
    'faculty' => [
        RoleTitleValue::CONFIGURATION_FACULTY,
        ['role' => 'professor'],
        'professor emeritus',
    ],
    'academic leadership' => [
        RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        ['role' => 'chair', 'scope' => ['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_name' => 'College of Medicine']],
        'chair emeritus of the College of Medicine',
    ],
    'institutional leadership' => [
        RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP,
        ['role' => 'dean', 'scope' => ['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_name' => 'College of Pharmacy']],
        'dean emeritus of the College of Pharmacy',
    ],
]);

it('rejects a honorific value naming both gendered forms, so the slot holds one or none', function (): void {
    // UAMS-Web/uams-statamic-archive#2138: the honorific is a single-choice radios slot. Where the former
    // `title_options` checkboxes accepted `emeritus` and `emerita` together, the
    // radios validation now accepts one gendered form or none and rejects both.
    $field = new Field('primary_role', ['type' => 'role_title']);

    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    $honorificRules = $fieldtype->extraRules()['primary_role.honorific'];

    $passes = fn (mixed $value): bool => Validator::make(
        ['primary_role' => ['honorific' => $value]],
        ['primary_role.honorific' => $honorificRules]
    )->passes();

    expect($passes('emeritus'))->toBeTrue();
    expect($passes('emerita'))->toBeTrue();
    expect($passes(null))->toBeTrue();
    expect($passes(['emeritus', 'emerita']))->toBeFalse();
    expect($passes('emeritus emerita'))->toBeFalse();
});

it('reads a legacy stored honorific from title_options into the honorific slot (#2138)', function (): void {
    // Entries stored before the honorific moved out of `title_options` carry
    // `title_options.emeritus` / `.emerita` and no `honorific`. The fieldtype migrates
    // the legacy key on read, so a stored honorific is not silently dropped -- the
    // exact defect on `patricia-cowan.md` (faculty, professor emeritus). On the next
    // save the migrated value persists and the stale checkbox key falls away.
    $field = new Field('primary_role', ['type' => 'role_title']);

    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'mode' => 'structured',
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'title_options' => ['emeritus' => true, 'emerita' => false],
        'role' => 'professor',
    ]);

    expect($out['honorific'])->toBe('emeritus');
    expect($out['title_options'] ?? [])->not->toHaveKey('emeritus');
    expect(RoleTitleValue::compose($out))->toBe('professor emeritus');
});

it('migrates a legacy emerita, and prefers emeritus when a stale payload names both (#2138)', function (): void {
    $field = new Field('primary_role', ['type' => 'role_title']);

    $fieldtype = $field->fieldtype();
    expect($fieldtype)->toBeInstanceOf(RoleTitle::class);

    /** @var RoleTitle $fieldtype */
    $fieldtype->setField($field);

    $emerita = $fieldtype->process([
        'mode' => 'structured',
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'title_options' => ['emeritus' => false, 'emerita' => true],
        'role' => 'professor',
    ]);
    expect($emerita['honorific'])->toBe('emerita');
    expect(RoleTitleValue::compose($emerita))->toBe('professor emerita');

    // A stored `honorific` already set is authoritative; the legacy keys do not override it.
    $explicit = $fieldtype->process([
        'mode' => 'structured',
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'honorific' => 'emerita',
        'title_options' => ['emeritus' => true],
        'role' => 'professor',
    ]);
    expect($explicit['honorific'])->toBe('emerita');
});
