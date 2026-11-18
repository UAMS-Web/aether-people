<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\RoleTitleDefinition;
use UAMSWeb\Aether\People\Support\RoleTitleValue;

/**
 * Parity tests for `RoleTitleDefinition` vs `config/role_title.php` and
 * `RoleTitleValue` delegation.
 *
 * Mutation: 100% on UAMSWeb\Aether\People\Support\RoleTitleDefinition (551 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Integration/Support/RoleTitleDefinitionTest.php
 */
it('locks the v3 unified-subfields schema marker in config', function (): void {
    expect(config('role_title.schema_version'))->toBe(3);
});

it('shares the optional-none sentinel between the definition and RoleTitleValue', function (): void {
    expect(RoleTitleDefinition::instance()->optionalRadioNone())->toBe(RoleTitleValue::optionalSelectionNone());
    expect(RoleTitleDefinition::instance()->optionalRadioNone())->toBe('__role_title_none__');
});

it('returns configuration handles in the ordered configurations map', function (): void {
    expect(RoleTitleDefinition::instance()->configurationHandles())->toBe([
        'faculty',
        'academic_leadership',
        'institutional_leadership',
        'clinical_leadership',
    ]);
});

it('reads the per-configuration radios validation flag from config', function (): void {
    $def = RoleTitleDefinition::instance();

    expect($def->radiosSubfieldUsesPerConfigurationOptionValidation('role'))->toBeTrue();
    expect($def->radiosSubfieldUsesPerConfigurationOptionValidation('scope_qualifier'))->toBeFalse();
    expect($def->radiosSubfieldUsesPerConfigurationOptionValidation('nonexistent_subfield'))->toBeFalse();
});

it('matches the default configuration handle to config', function (): void {
    expect(RoleTitleDefinition::instance()->defaultConfigurationHandle())->toBe(config('role_title.configurations.default'));
    expect(RoleTitleValue::defaultConfigurationHandle())->toBe(config('role_title.configurations.default'));
});

it('matches the faculty inactive-clears legacy shape', function (): void {
    $expected = [
        'title_options' => [
            'interim' => false,
            'senior' => false,
            'co_prefix' => false,
            'regional' => false,
            'executive' => false,
        ],
        'object' => null,
        'positional_qualifier' => null,
        // ---
        // `scope` is deliberately absent (UAMS-Web/uams-statamic-archive#2145). This method returns the subfields
        // CLEARED on save because the configuration's `available_in` excludes them,
        // and faculty now carries a scope, so it is active rather than cleared.
        //
        // Its presence here was correct until faculty gained the slot; removing it
        // is the whole visible effect of that change on this shape, and a faculty
        // title with no scope name still composes byte-identically.
        // ---
    ];

    expect(RoleTitleDefinition::instance()->inactiveSubfieldClearsForConfiguration(
        RoleTitleValue::CONFIGURATION_FACULTY
    ))->toEqual($expected);
});

it('matches the academic-leadership inactive-clears legacy shape', function (): void {
    $expected = [
        'title_options' => [
            'visiting' => false,
            'distinguished' => false,
            'adjunct' => false,
            'senior' => false,
        ],
        'scope_qualifier' => null,
    ];

    expect(RoleTitleDefinition::instance()->inactiveSubfieldClearsForConfiguration(
        RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP
    ))->toEqual($expected);
});

it('matches the institutional-leadership inactive-clears legacy shape', function (): void {
    $expected = [
        'title_options' => [
            'visiting' => false,
            'distinguished' => false,
            'adjunct' => false,
        ],
        'object' => null,
        'scope_qualifier' => null,
    ];

    expect(RoleTitleDefinition::instance()->inactiveSubfieldClearsForConfiguration(
        RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP
    ))->toEqual($expected);
});

it('delegates inactive-clears from RoleTitleValue to the definition unchanged', function (): void {
    foreach ([
        RoleTitleValue::CONFIGURATION_FACULTY,
        RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP,
    ] as $configuration) {
        expect(RoleTitleValue::inactiveSubfieldClearsForConfiguration($configuration))
            ->toEqual(RoleTitleDefinition::instance()->inactiveSubfieldClearsForConfiguration($configuration));
    }
});

it('matches preload slot order to the CP label-map keys', function (): void {
    $def = RoleTitleDefinition::instance();

    expect($def->preloadSlotOrderFromSubfields())->toBe(array_keys(RoleTitleValue::optionLabelMapsForPreload()));
});

it('derives per-mode Title Options keys from available_in', function (): void {
    $map = RoleTitleDefinition::instance()->mapOptionKeysByConfigurationHandles('title_options');

    expect($map[RoleTitleValue::CONFIGURATION_FACULTY])
        ->toBe(['visiting', 'distinguished', 'adjunct']);
    expect($map[RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP])
        ->toBe(['interim', 'co_prefix', 'regional', 'executive']);
    expect($map[RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP])
        ->toBe(['interim', 'co_prefix', 'regional', 'senior', 'executive']);
});

it('aligns preload available_in wiring with the per-mode key maps', function (): void {
    $def = RoleTitleDefinition::instance();

    $titleMeta = $def->optionLabelOptionMetadataRowsForPreload()[RoleTitleValue::PRELOAD_KEY_TITLE_OPTIONS];
    $titleByCfg = $def->mapOptionKeysByConfigurationHandles(RoleTitleValue::PRELOAD_KEY_TITLE_OPTIONS);

    foreach ($titleMeta as $row) {
        $key = $row['key'];
        $wire = $row['available_in'];

        foreach ($def->configurationHandles() as $handle) {
            $allowed = $titleByCfg[$handle] ?? [];
            $inMode = in_array($key, $allowed, true);
            $wireAllows = $wire === [] || in_array($handle, $wire, true);
            expect($inMode)->toBe($wireAllows, "Title option `{$key}` vs mode `{$handle}`");
        }
    }

    $roleMeta = $def->optionLabelOptionMetadataRowsForPreload()[RoleTitleValue::PRELOAD_KEY_ROLE];
    $roleByCfg = $def->mapOptionKeysByConfigurationHandles(RoleTitleValue::PRELOAD_KEY_ROLE);

    foreach ($roleMeta as $row) {
        $key = $row['key'];
        $wire = $row['available_in'];

        foreach ($def->configurationHandles() as $handle) {
            $allowed = $roleByCfg[$handle] ?? [];
            $inMode = in_array($key, $allowed, true);
            $wireAllows = $wire === [] || in_array($handle, $wire, true);
            expect($inMode)->toBe($wireAllows, "Role option `{$key}` vs mode `{$handle}`");
        }
    }
});

it('composes faculty Title Options into prefix/inline/suffix segments in config order', function (): void {
    $def = RoleTitleDefinition::instance();
    $cfg = RoleTitleValue::CONFIGURATION_FACULTY;
    $sub = RoleTitleValue::PRELOAD_KEY_TITLE_OPTIONS;

    expect($def->subfieldOptionKeysForComposeSegment($sub, $cfg, RoleTitleValue::COMPOSE_POSITION_PREFIX))
        ->toBe(['visiting', 'distinguished', 'adjunct']);
    expect($def->subfieldOptionKeysForComposeSegment($sub, $cfg, RoleTitleValue::COMPOSE_POSITION_INLINE))
        ->toBe([]);
    expect($def->subfieldOptionKeysForComposeSegment($sub, $cfg, RoleTitleValue::COMPOSE_POSITION_SUFFIX))
        ->toBe([]);

    expect(RoleTitleValue::facultyCheckboxOptionKeyOrder())->toBe([
        ...$def->subfieldOptionKeysForComposeSegment($sub, $cfg, RoleTitleValue::COMPOSE_POSITION_PREFIX),
        ...$def->subfieldOptionKeysForComposeSegment($sub, $cfg, RoleTitleValue::COMPOSE_POSITION_INLINE),
        ...$def->subfieldOptionKeysForComposeSegment($sub, $cfg, RoleTitleValue::COMPOSE_POSITION_SUFFIX),
    ]);
});

it('derives per-mode role keys from available_in', function (): void {
    $map = RoleTitleDefinition::instance()->mapOptionKeysByConfigurationHandles('role');

    expect($map[RoleTitleValue::CONFIGURATION_FACULTY])->toBe(['instructor', 'lecturer', 'professor']);
    expect($map[RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP])
        ->toBe(['chair', 'chairperson', 'chairman', 'chairwoman', 'director']);
    expect($map[RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP])->toBe([
        'chancellor',
        'chief_communications_officer',
        'chief_executive_officer',
        'chief_financial_officer',
        'chief_human_resources_officer',
        'chief_information_officer',
        'chief_legal_counsel',
        'chief_legal_officer',
        'chief_marketing_officer',
        'chief_of_staff',
        'chief_operating_officer',
        'chief_people_and_culture_officer',
        'chief_strategy_officer',
        'chief_technology_officer',
        'dean',
        'director',
        'general_counsel',
        'president',
        'provost',
        'public_relations_officer',
    ]);
});

it('aligns option-label map key lists between definition and assembled preload maps', function (): void {
    $keyLists = RoleTitleDefinition::instance()->optionLabelMapKeyListsByPreloadKey();
    $maps = RoleTitleValue::optionLabelMapsForPreload();

    foreach ($keyLists as $preloadKey => $keys) {
        expect($maps)->toHaveKey($preloadKey);
        expect(array_column($maps[$preloadKey], 'key'))->toBe($keys);
    }
});

it('covers every canonical configuration handle in the CP preload labels', function (): void {
    $def = RoleTitleDefinition::instance();
    $labels = $def->configurationLabelsForCpPreload();

    foreach ($def->configurationHandles() as $handle) {
        expect($labels)->toHaveKey($handle);
        expect($labels[$handle])->not->toBe('');
    }
});

it('starts CP editor blocks with Title Options checkboxes for each mode', function (): void {
    $blocks = RoleTitleDefinition::instance()->cpEditorBlocksByConfigurationForPreload();

    foreach (RoleTitleValue::configurationHandles() as $handle) {
        expect($blocks)->toHaveKey($handle);
        expect($blocks[$handle])->not->toBe([]);
        expect($blocks[$handle][0]['type'])->toBe('checkboxes');
        expect($blocks[$handle][0]['key'])->toBe('title_options');
    }
});

it('includes the faculty scope qualifier and role rows in the compose checklist', function (): void {
    $rules = RoleTitleDefinition::instance()->composeChecklistRulesByConfigurationForPreload();
    $faculty = $rules[RoleTitleValue::CONFIGURATION_FACULTY] ?? [];

    expect($faculty)->not->toBe([]);
    expect($faculty[0]['path'])->toBe('scope_qualifier');
    expect($faculty[0]['kind'])->toBe('optional_radio');
    expect(array_column($faculty, 'path'))->toContain('role');
});

it('resolves CP subfield labels from config for the Title Options toggle', function (): void {
    $labels = RoleTitleDefinition::instance()->cpSubfieldLabelsForPreload();

    expect($labels['title_options'])->toBe(__('aether-people::fieldtypes.role_title.field.toggle_options'));
});

it('uses dotted paths for nested CP group field labels', function (): void {
    $nested = RoleTitleDefinition::instance()->cpNestedFieldLabelsForPreload();

    expect($nested['scope.scope_joiner'])->toBe(__('aether-people::fieldtypes.role_title.field.scope_joiner'));
    expect($nested['scope.scope_name'])->toBe(__('aether-people::fieldtypes.role_title.field.scope_name'));
});

it('lists checkbox and group top-level storage keys for the CP value merge', function (): void {
    $keys = RoleTitleDefinition::instance()->structuredTopLevelKeysForCpValueMerge();

    expect($keys)->toContain('title_options');
    expect($keys)->toContain('scope');
    expect($keys)->toContain('occupation');
    expect($keys)->toContain('occupational_field');
});

it('includes nested scope rows in the CP editor group block', function (): void {
    $blocks = RoleTitleDefinition::instance()->cpEditorBlocksByConfigurationForPreload();
    $cfg = RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP;
    $list = $blocks[$cfg] ?? [];
    $group = null;

    foreach ($list as $row) {
        if (($row['type'] ?? '') === 'group' && ($row['key'] ?? '') === 'scope') {
            $group = $row;

            break;
        }
    }

    expect($group)->not->toBeNull();
    expect($group)->toHaveKey('nested');

    $nestedRaw = data_get($group, 'nested');
    expect($nestedRaw)->toBeArray();

    $nested = collect(is_array($nestedRaw) ? $nestedRaw : []);
    $paths = $nested->pluck('storage_path')->all();
    expect($paths)->toContain('scope.scope_joiner');
    expect($paths)->toContain('scope.scope_name');

    $joiner = $nested->firstWhere('storage_path', 'scope.scope_joiner');

    expect($joiner)->not->toBeNull();
    expect(data_get($joiner, 'option_map_key'))->toBe('scope_joiner');
    expect(data_get($joiner, 'label_required_when_sibling_path_nonempty'))->toBe('scope.scope_name');
});

/**
 * `__()` returns the key itself when a translation is missing, so a subfield added to
 * `config/role_title.php` without its lang lines reaches the CP as a raw key (UAMS-Web/uams-statamic-archive#2545).
 * Each label is read through the same method that builds the CP preload, so the test
 * sees exactly what the editor renders.
 *
 * @return array<string, string> Where the label is used, to the label itself
 */
function roleTitleCpLabelsForTranslationCheck(): array
{
    $def = RoleTitleDefinition::instance();
    $labels = [];

    foreach ($def->cpSubfieldLabelsForPreload() as $key => $label) {
        $labels['subfield '.$key] = $label;
    }

    foreach ($def->cpNestedFieldLabelsForPreload() as $path => $label) {
        $labels['nested field '.$path] = $label;
    }

    foreach ($def->optionalRadioNoneLabelsByTopLevelRadiosSubfield() as $key => $label) {
        $labels['no-selection option for '.$key] = $label;
    }

    foreach ($def->optionLabelMapKeyListsByPreloadKey() as $slot => $optionKeys) {
        foreach ($optionKeys as $optionKey) {
            $labels[sprintf('option %s.%s', $slot, $optionKey)] = __('aether-people::fieldtypes.role_title.field.option.'.$optionKey);
        }
    }

    return $labels;
}

it('translates every role_title subfield, nested field, option and no-selection label', function (): void {
    $labels = roleTitleCpLabelsForTranslationCheck();

    $untranslated = array_keys(array_filter(
        $labels,
        static fn (string $label): bool => str_starts_with($label, 'statamic::')
    ));

    // The honorific rows are the case UAMS-Web/uams-statamic-archive#2545 was filed for, so they must be in the set checked
    expect($labels)->toHaveKeys(['subfield honorific', 'no-selection option for honorific'])
        ->and($untranslated)->toBe([]);
});

it('labels the honorific group and its no-selection option in words', function (): void {
    $def = RoleTitleDefinition::instance();

    expect($def->cpSubfieldLabelsForPreload()['honorific'])->toBe('Honorific')
        ->and($def->optionalRadioNoneLabelsByTopLevelRadiosSubfield()['honorific'])->toBe('No Honorific');
});
