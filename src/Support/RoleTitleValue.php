<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

/**
 * Domain logic for the `role_title` fieldtype: stored shape, option keys,
 * label maps for the CP, one-line composition, and helpers shared with
 * validation.
 *
 * Consumed by `UAMSWeb\Aether\People\Fieldtypes\RoleTitle` (process, preload, rules),
 * `UAMSWeb\Aether\People\Rules\RoleTitlePerConfigurationOptionRule`, `RoleTitleSchemaOutputComputer` for
 * `schema`-driven `occupation` / `occupational_field`, and
 * the host's role-title computed-field registrar
 * for optional computed mirrors of the composed title.
 *
 * Ordered keys, defaults, inactive clears, and preload wiring are loaded from
 * `config('role_title')` via `RoleTitleDefinition`. Changing modes or options
 * updates that config plus translations under `lang/.../fieldtypes.php`, then
 * tests under `tests/Unit/Fieldtypes/` and `tests/Unit/Support/`.
 */
final class RoleTitleValue
{
    /**
     * Stored `mode` value: structured faculty/leadership composer.
     */
    public const string MODE_STRUCTURED = 'structured';

    /**
     * Stored `mode` value: free-text title plus occupation metadata lists.
     */
    public const string MODE_UNSTRUCTURED = 'unstructured';

    /**
     * Stored `configuration` value for faculty titles.
     */
    public const string CONFIGURATION_FACULTY = 'faculty';

    /**
     * Stored `configuration` value for academic administrative titles.
     */
    public const string CONFIGURATION_ACADEMIC_LEADERSHIP = 'academic_leadership';

    /**
     * Stored `configuration` value for institutional administrative titles.
     */
    public const string CONFIGURATION_INSTITUTIONAL_LEADERSHIP = 'institutional_leadership';

    /**
     * Stored `configuration` value for clinical leadership titles.
     */
    public const string CONFIGURATION_CLINICAL_LEADERSHIP = 'clinical_leadership';

    /**
     * Meta key for the shared Title Options checkbox label rows in
     * `option_label_maps` (each row includes `available_in` for the CP).
     */
    public const string PRELOAD_KEY_TITLE_OPTIONS = 'title_options';

    /**
     * Meta key for faculty scope qualifier radio labels in `option_label_maps`.
     */
    public const string PRELOAD_KEY_SCOPE_QUALIFIER = 'scope_qualifier';

    /**
     * Meta key for hierarchical modifier radio labels in `option_label_maps`.
     */
    public const string PRELOAD_KEY_HIERARCHICAL_MODIFIER = 'hierarchical_modifier';

    /**
     * Meta key for academic administrative object radio labels in
     * `option_label_maps`.
     */
    public const string PRELOAD_KEY_OBJECT = 'object';

    /**
     * Meta key for positional qualifier radio labels in `option_label_maps`.
     */
    public const string PRELOAD_KEY_POSITIONAL_QUALIFIER = 'positional_qualifier';

    /**
     * Meta key for scope joiner radio labels in `option_label_maps`.
     */
    public const string PRELOAD_KEY_SCOPE_JOINER = 'scope_joiner';

    /**
     * Meta key for unified role radio label rows in `option_label_maps` (each row
     * includes `available_in`; CP filters by active `configuration`).
     */
    public const string PRELOAD_KEY_ROLE = 'role';

    /**
     * Meta key for shared default subfields (no `configuration`) in fieldtype preload.
     */
    public const string PRELOAD_KEY_EMPTY_VALUE_SHARED = 'empty_value_shared';

    /**
     * Meta key for ordered `configuration` handles (same order as `configurationHandles()`).
     */
    public const string PRELOAD_KEY_CONFIGURATION_HANDLES_CANONICAL = 'configuration_handles_canonical';

    /**
     * Meta key for translated configuration picker labels keyed by handle.
     */
    public const string PRELOAD_KEY_CONFIGURATION_LABELS = 'configuration_labels';

    /**
     * Meta key for CP editor block lists keyed by `configuration` handle.
     */
    public const string PRELOAD_KEY_CP_EDITOR_BLOCKS_BY_CONFIGURATION = 'cp_editor_blocks_by_configuration';

    /**
     * Meta key for compose-checklist rules keyed by `configuration` handle.
     */
    public const string PRELOAD_KEY_COMPOSE_CHECKLIST_BY_CONFIGURATION = 'compose_checklist_by_configuration';

    /**
     * Meta key for optional-radio “none” labels keyed by top-level radios subfield.
     */
    public const string PRELOAD_KEY_OPTIONAL_RADIO_NONE_LABELS_BY_SUBFIELD = 'optional_radio_none_labels_by_subfield';

    /**
     * Meta key for CP field labels keyed by subfield storage key.
     */
    public const string PRELOAD_KEY_CP_SUBFIELD_LABELS = 'cp_subfield_labels';

    /**
     * Meta key for nested `group.fields` CP labels (`{group}.{nested}` paths).
     */
    public const string PRELOAD_KEY_CP_NESTED_FIELD_LABELS = 'cp_nested_field_labels';

    /**
     * Meta key for the translated configuration row on the compose checklist.
     */
    public const string PRELOAD_KEY_COMPOSE_CHECKLIST_CONFIGURATION_ALERT_LABEL = 'compose_checklist_configuration_alert_label';

    /**
     * Meta key for the configuration picker field label in the CP.
     */
    public const string PRELOAD_KEY_CONFIGURATION_FIELD_LABEL = 'configuration_field_label';

    /**
     * Meta key for top-level object keys shallow-merged in the CP value shape.
     */
    public const string PRELOAD_KEY_STRUCTURED_MERGE_TOP_LEVEL_KEYS = 'structured_merge_top_level_keys';

    /**
     * Compose segment: `subfields.*.options.*.position` = `prefix` (faculty:
     * before role block).
     */
    public const string COMPOSE_POSITION_PREFIX = 'prefix';

    /**
     * Compose segment: `position` = `suffix` (faculty: after role block).
     */
    public const string COMPOSE_POSITION_SUFFIX = 'suffix';

    /**
     * Compose segment: missing `position`, unknown value, or explicit `inline`
     * (in config order relative to other inline keys).
     */
    public const string COMPOSE_POSITION_INLINE = 'inline';

    /**
     * Resolve the cached definition from application config.
     */
    private static function definition(): RoleTitleDefinition
    {
        // Return the singleton definition loaded from `config('role_title')`
        return RoleTitleDefinition::instance();
    }

    /**
     * Sentinel stored by the CP when an optional radio means “no selection”.
     *
     * Normalized to null in `RoleTitle::process()` / `preProcess()`.
     *
     * @throws \RuntimeException When `role_title.sentinel.optional_radio_none` is
     *                           invalid (see `RoleTitleDefinition::optionalRadioNone()`).
     */
    public static function optionalSelectionNone(): string
    {
        // Read the optional-none sentinel from the role_title definition
        return self::definition()->optionalRadioNone();
    }

    /**
     * Valid `configuration` values for stored payloads (canonical order).
     *
     * @return list<string>
     */
    public static function configurationHandles(): array
    {
        // Delegate to the definition’s ordered configuration handles
        return self::definition()->configurationHandles();
    }

    /**
     * Default configuration handle (`configurations.default` in `role_title.php`).
     *
     * @return string|null Resolved default handle, or null when none applies.
     */
    public static function defaultConfigurationHandle(): ?string
    {
        return self::definition()->defaultConfigurationHandle();
    }

    /**
     * Canonical blueprint / stored handles for title structure (`mode`).
     *
     * @return list<string>
     */
    public static function titleStructureHandles(): array
    {
        return [self::MODE_STRUCTURED, self::MODE_UNSTRUCTURED];
    }

    /**
     * Ordered handles for the `required_unstructured_subfields` blueprint checkboxes.
     *
     * @var list<string>
     */
    public const array REQUIRED_UNSTRUCTURED_SUBFIELD_HANDLES = [
        'value',
        'occupation_same_as',
        'occupation_onet_soc',
        'occupation_isco_08',
        'occupational_field_same_as',
    ];

    /**
     * Default `required_unstructured_subfields` when unstructured is enabled: only
     * the free-text title is treated as required (when the fieldtype is required).
     *
     * @return list<string>
     */
    public static function defaultRequiredUnstructuredSubfieldHandles(): array
    {
        return ['value'];
    }

    /**
     * Resolve which unstructured segments are required when the blueprint marks the
     * fieldtype required (checkboxes under `required_unstructured_subfields`).
     *
     * @param  array<string, mixed>  $config  Blueprint fieldtype config
     * @return list<string> Canonical ordered handles
     */
    public static function requiredUnstructuredSubfieldHandlesFromConfig(
        array $config
    ): array {
        $raw = $config['required_unstructured_subfields'] ?? null;

        if (! \is_array($raw)) {
            return self::defaultRequiredUnstructuredSubfieldHandles();
        }

        $ordered = [];

        foreach (self::REQUIRED_UNSTRUCTURED_SUBFIELD_HANDLES as $handle) {
            if (\in_array($handle, $raw, true)) {
                $ordered[] = $handle;
            }
        }

        return $ordered !== []
            ? $ordered
            : self::defaultRequiredUnstructuredSubfieldHandles();
    }

    /**
     * Default nested shape for unstructured occupation / field lists (no CP keys).
     *
     * @return array<string, mixed>
     */
    public static function emptyUnstructuredStorableSlice(): array
    {
        return [
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
        ];
    }

    /**
     * Merge canonical unstructured occupation branches with a normalized slice so
     * Antlers/API paths match structured schema output (`same_as`, `category_code`,
     * `onet_soc`, `isco_08` lists).
     *
     * @param  array<string, mixed>  $payload  Normalized role_title payload
     * @return array<string, mixed> Payload with aligned `occupation` / `occupational_field`
     */
    public static function normalizeUnstructuredOccupationBranchesForSchemaParity(
        array $payload
    ): array {
        if (($payload['mode'] ?? null) !== self::MODE_UNSTRUCTURED) {
            return $payload;
        }

        $occIn = $payload['occupation'] ?? null;
        $occIn = \is_array($occIn) ? $occIn : [];

        $fieldIn = $payload['occupational_field'] ?? null;
        $fieldIn = \is_array($fieldIn) ? $fieldIn : [];

        $occNorm = RoleTitleSchemaOutputComputer::normalizeSchemaOutputFragment($occIn);
        $fieldNorm = RoleTitleSchemaOutputComputer::normalizeSchemaOutputFragment($fieldIn);

        $emptySlice = self::emptyUnstructuredStorableSlice();
        /** @var array<string, mixed> $occCanonical */
        $occCanonical = $emptySlice['occupation'];
        /** @var array<string, mixed> $fieldCanonical */
        $fieldCanonical = $emptySlice['occupational_field'];

        $payload['occupation'] = \array_replace_recursive(
            $occCanonical,
            \is_array($occNorm) ? $occNorm : []
        );
        $payload['occupational_field'] = \array_replace_recursive(
            $fieldCanonical,
            \is_array($fieldNorm) ? $fieldNorm : []
        );

        return $payload;
    }

    /**
     * Raw `subfields.{key}` row from config when present.
     *
     * @return array<string, mixed>|null
     */
    public static function topLevelSubfieldRow(
        string $subfieldKey
    ): ?array {
        return self::definition()->topLevelSubfieldRow($subfieldKey);
    }

    /**
     * Whether a top-level `radios` subfield uses `RoleTitlePerConfigurationOptionRule`.
     */
    public static function radiosSubfieldUsesPerConfigurationOptionValidation(
        string $subfieldKey
    ): bool {
        return self::definition()->radiosSubfieldUsesPerConfigurationOptionValidation(
            $subfieldKey
        );
    }

    /**
     * Top-level `subfields` keys with `type` `checkboxes`.
     *
     * @return list<string>
     */
    public static function checkboxSubfieldKeys(): array
    {
        return self::definition()->checkboxSubfieldKeys();
    }

    /**
     * Top-level `subfields` keys with `type` `radios`.
     *
     * @return list<string>
     */
    public static function topLevelRadiosSubfieldKeys(): array
    {
        return self::definition()->topLevelRadiosSubfieldKeys();
    }

    /**
     * Top-level `subfields` keys with `type` `group`.
     *
     * @return list<string>
     */
    public static function groupSubfieldKeys(): array
    {
        return self::definition()->groupSubfieldKeys();
    }

    /**
     * Ordered option storage keys from `config('role_title')`: a top-level
     * `subfields` entry with `radios` or `checkboxes`, or a nested field under a
     * `group` subfield.
     *
     * @param  string  $subfieldKey  Top-level `subfields` key (e.g. `scope_qualifier`, `scope`)
     * @param  string|null  $fieldWithinGroup  Nested field under `group.fields` (e.g. `scope_joiner` when `$subfieldKey` is `scope`)
     * @return list<string>
     */
    public static function orderedOptionKeysForSubfield(
        string $subfieldKey,
        ?string $fieldWithinGroup = null
    ): array {
        // Delegate to the definition’s ordered option keys
        return self::definition()->orderedOptionKeysForSubfield(
            $subfieldKey,
            $fieldWithinGroup
        );
    }

    /**
     * Validation attribute names that use `role_title.field.option.{key}`, derived
     * from Title Options checkbox key lists so labels stay aligned.
     *
     * @return list<string> Distinct keys whose labels use `field.option` translations
     */
    public static function validationAttributeOptionLangKeys(): array
    {
        // Merge per-mode Title Options key lists into one flat list using spread
        $byConfiguration = self::mapOptionKeysByConfigurationHandles(
            self::PRELOAD_KEY_TITLE_OPTIONS
        );

        // Initialize an empty array to store the merged list
        $merged = [];

        // Loop through each configuration and add its option keys to the merged list
        foreach ($byConfiguration as $optionKeys) {
            // Use the spread operator to add the option keys to the merged list
            $merged = [...$merged, ...$optionKeys];
        }

        // Return the merged list as a distinct array of unique keys
        return \array_values(
            \array_unique($merged)
        );
    }

    /**
     * Title Options boolean storage keys (same union as `validationAttributeOptionLangKeys()`).
     *
     * @return list<string> Distinct checkbox keys coerced in `RoleTitle::normalizeStored()`
     */
    public static function storedTitleOptionBooleanKeys(): array
    {
        // Reuse the validation attribute option key union for storage
        return self::validationAttributeOptionLangKeys();
    }

    /**
     * Validation / CP: relative dotted path => lang suffix under `role_title.field` or
     * `role_title.field.option`.
     *
     * @return array<string, string> Relative path to lang key fragment for labels
     */
    public static function validationAttributeRelativePathToLangSuffix(): array
    {
        // Initialize structural and scalar field paths (non-checkbox)
        $out = [
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
        ];

        // Title Options validate under `title_options.{key}`; labels use `field.option.*`
        foreach (self::storedTitleOptionBooleanKeys() as $key) {
            $out['title_options.'.$key] = $key;
        }

        $out['occupation.same_as'] = 'unstructured.occupation_same_as';
        $out['occupation.category_code.isco_08'] = 'unstructured.occupation_isco_08';
        $out['occupation.category_code.onet_soc'] = 'unstructured.occupation_onet_soc';
        $out['occupational_field.same_as'] = 'unstructured.occupational_field_same_as';

        return $out;
    }

    /**
     * Default subfields shared by all configuration modes (excludes `configuration`).
     *
     * Keys and order follow `config/role_title.php` `subfields`; each entry is
     * `RoleTitleDefinition::subfieldDefault()` for that key. `value` uses the
     * `composed_line` row (null until `RoleTitle::process()` composes a line).
     *
     * @return array<string, mixed> Shape merged with `configuration` in `emptyValueTemplate()`
     */
    public static function emptyValueSharedPayload(): array
    {
        // Build shared defaults from the role_title definition
        $shared = self::definition()->emptyValueSharedPayload();

        // Merge schema-driven output placeholders for CP defaults and preload
        $out = $shared + RoleTitleSchemaOutputComputer::emptyPlaceholderSlice();

        unset($shared);

        return $out;
    }

    /**
     * Default boolean map for Title Options (nested under `title_options`).
     *
     * Same keys as `emptyValueSharedPayload()['title_options']` from config.
     *
     * @return array<string, bool> Checkbox key => false for empty template
     */
    public static function emptyTitleOptionsPayload(): array
    {
        // Read the title_options slice from the shared empty template
        /** @var array<string, bool>|null $payload */
        $payload = self::emptyValueSharedPayload()['title_options'] ?? null;

        // Return the map when the shared payload yields an array
        return \is_array($payload) ? $payload : [];
    }

    /**
     * Whether a Title Options checkbox is on under nested `title_options`.
     *
     * @param  array<string, mixed>  $data  Normalized role_title payload
     * @return bool True when the checkbox is enabled
     */
    public static function titleOptionIsSet(
        array $data,
        string $key
    ): bool {
        // Read the nested Title Options map when present
        $nested = $data['title_options'] ?? null;

        // Treat missing maps or unknown keys as off. Equivalent without the return:
        // `empty()` reads a missing key, or an offset of a non-array, as empty too.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! \is_array($nested) || ! \array_key_exists($key, $nested)) {
            return false;
        }

        // Return whether the stored boolean is truthy
        return ! empty($nested[$key]);
    }

    /**
     * Option keys for a stored `configuration` that compose in a given segment
     * (`prefix`, `suffix`, or `inline`), in encountered order within that
     * segment. Uses `subfields.*.options.*.position` for any field type with an
     * options table.
     *
     * @param  string  $subfieldKey  Top-level `subfields` key
     * @param  string  $configuration  Stored `configuration` handle
     * @param  string  $segment  `COMPOSE_POSITION_*` constant value
     * @return list<string>
     *
     * @throws \InvalidArgumentException When `$segment` is not a known compose
     *                                   segment.
     */
    public static function subfieldOptionKeysForComposeSegment(
        string $subfieldKey,
        string $configuration,
        string $segment
    ): array {
        // Delegate to the definition’s compose-segment filter
        return self::definition()->subfieldOptionKeysForComposeSegment(
            $subfieldKey,
            $configuration,
            $segment
        );
    }

    /**
     * Faculty Title Options keys in label-map / storage order (prefix, inline,
     * then suffix).
     *
     * @return list<string> Ordered faculty checkbox keys for labels and compose
     */
    public static function facultyCheckboxOptionKeyOrder(): array
    {
        // Merge compose segments for faculty mode in phrase order
        return [
            ...self::subfieldOptionKeysForComposeSegment(
                self::PRELOAD_KEY_TITLE_OPTIONS,
                self::CONFIGURATION_FACULTY,
                self::COMPOSE_POSITION_PREFIX
            ),
            ...self::subfieldOptionKeysForComposeSegment(
                self::PRELOAD_KEY_TITLE_OPTIONS,
                self::CONFIGURATION_FACULTY,
                self::COMPOSE_POSITION_INLINE
            ),
            ...self::subfieldOptionKeysForComposeSegment(
                self::PRELOAD_KEY_TITLE_OPTIONS,
                self::CONFIGURATION_FACULTY,
                self::COMPOSE_POSITION_SUFFIX
            ),
        ];
    }

    /**
     * Ordered option keys for a subfield that apply to one stored `configuration`.
     *
     * @param  string  $subfieldKey  Top-level `subfields` key with an `options` map
     * @param  string  $configuration  Stored `configuration` handle
     * @return list<string>
     */
    public static function optionKeysForSubfieldAndConfiguration(
        string $subfieldKey,
        string $configuration
    ): array {
        // Delegate to the definition’s per-mode option list
        return self::definition()->optionKeysForSubfieldAndConfiguration(
            $subfieldKey,
            $configuration
        );
    }

    /**
     * Map each configured mode handle to ordered option keys for a subfield.
     *
     * @param  string  $subfieldKey  Top-level `subfields` key with an `options` map
     * @return array<string, list<string>> Configuration handle to ordered keys
     */
    public static function mapOptionKeysByConfigurationHandles(
        string $subfieldKey
    ): array {
        // Delegate to the definition’s handle-to-keys map
        return self::definition()->mapOptionKeysByConfigurationHandles(
            $subfieldKey
        );
    }

    /**
     * Stored key to localized CP label map using `role_title.field.option.{key}`.
     *
     * @param  list<string>  $keys  Storage keys in display order
     * @return array<string, string> Stored key to localized label for each key
     */
    public static function fieldOptionLabelsForKeys(
        array $keys
    ): array {
        $out = [];

        foreach ($keys as $key) {
            $out[$key] = __('aether-people::fieldtypes.role_title.field.option.'.$key);
        }

        return $out;
    }

    /**
     * Allowed values for `Rule::in` when the optional-none sentinel is allowed.
     *
     * @param  array<string, string>  $optionLabels  Option key to label map for the field
     * @return list<string> Allowed stored values plus optional-none sentinel
     */
    public static function ruleInValuesWithOptionalNone(
        array $optionLabels
    ): array {
        return [
            ...\array_keys($optionLabels),
            self::optionalSelectionNone(),
        ];
    }

    /**
     * Label rows for the CP (`RoleTitle::preload()` → `meta.option_label_maps`).
     *
     * Each preload slot is a list of `{ key, label, available_in }` where
     * `available_in` is empty for “all configured modes”, otherwise an explicit
     * list of `configuration` handles (see `RoleTitleDefinition`).
     *
     * @return array<string, list<array{key: string, label: string, available_in: list<string>}>>
     */
    public static function optionLabelMapsForPreload(): array
    {
        // Read row metadata (keys + availability) for each preload slot
        $metaRows = self::definition()->optionLabelOptionMetadataRowsForPreload();

        // Attach localized labels for each option row
        $out = [];

        foreach ($metaRows as $preloadKey => $rows) {
            // Map each metadata row to a labeled wire row
            $labeled = [];

            foreach ($rows as $row) {
                // Read the option storage key from the metadata row
                $key = $row['key'];

                // Append one `{ key, label, available_in }` row for this slot
                $labeled[] = [
                    'key' => $key,
                    'label' => __('aether-people::fieldtypes.role_title.field.option.'.$key),
                    'available_in' => $row['available_in'],
                ];
            }

            // Store the labeled row list for this preload slot
            $out[$preloadKey] = $labeled;
        }

        return $out;
    }

    /**
     * CP preload payload derived from `config/role_title.php` via
     * `RoleTitleDefinition` (option label rows, shared empty defaults,
     * optional-radio sentinel, configuration labels/handles, editor blocks,
     * compose-checklist rules, CP field labels, configuration field label,
     * and structured merge keys for the Vue shell).
     *
     * @return array<string, mixed> Meta merged into `RoleTitle::preload()`
     */
    public static function fieldtypePreloadMetaFromDefinition(): array
    {
        // Resolve the cached definition for derived preload slices
        $definition = self::definition();

        // Resolve one translated string for configuration UI and checklist rows
        $configurationFieldLabel = __(
            'aether-people::fieldtypes.role_title.field.configuration'
        );

        // Assemble definition-backed preload slices for the Vue fieldtype
        $out = [
            'option_label_maps' => self::optionLabelMapsForPreload(),
            self::PRELOAD_KEY_EMPTY_VALUE_SHARED => self::emptyValueSharedPayload(),
            ...RoleTitleUnstructuredReplicatorSupport::preloadReplicatorSlice(),
            'optional_radio_none' => self::optionalSelectionNone(),
            self::PRELOAD_KEY_CONFIGURATION_HANDLES_CANONICAL => self::configurationHandles(),
            self::PRELOAD_KEY_CONFIGURATION_LABELS => $definition->configurationLabelsForCpPreload(),
            self::PRELOAD_KEY_CP_EDITOR_BLOCKS_BY_CONFIGURATION => $definition->cpEditorBlocksByConfigurationForPreload(),
            self::PRELOAD_KEY_COMPOSE_CHECKLIST_BY_CONFIGURATION => $definition->composeChecklistRulesByConfigurationForPreload(),
            self::PRELOAD_KEY_OPTIONAL_RADIO_NONE_LABELS_BY_SUBFIELD => $definition->optionalRadioNoneLabelsByTopLevelRadiosSubfield(),
            self::PRELOAD_KEY_CP_SUBFIELD_LABELS => $definition->cpSubfieldLabelsForPreload(),
            self::PRELOAD_KEY_CP_NESTED_FIELD_LABELS => $definition->cpNestedFieldLabelsForPreload(),
            self::PRELOAD_KEY_COMPOSE_CHECKLIST_CONFIGURATION_ALERT_LABEL => $configurationFieldLabel,
            self::PRELOAD_KEY_CONFIGURATION_FIELD_LABEL => $configurationFieldLabel,
            self::PRELOAD_KEY_STRUCTURED_MERGE_TOP_LEVEL_KEYS => $definition->structuredTopLevelKeysForCpValueMerge(),
        ];

        unset(
            $configurationFieldLabel,
            $definition
        );

        return $out;
    }

    /**
     * Build a single-line title from a normalized role_title payload.
     *
     * @param  array<string, mixed>  $data  Normalized role_title field payload
     * @return string Composed display title, or empty when configuration is unknown.
     */
    public static function compose(
        array $data
    ): string {
        if (($data['mode'] ?? null) === self::MODE_UNSTRUCTURED) {
            return '';
        }

        // Dispatch to the composer for the active configuration mode; `match` is
        // strict, so a missing or non-string configuration takes the default arm
        return match ($data['configuration'] ?? null) {
            self::CONFIGURATION_FACULTY => self::composeFaculty($data),
            self::CONFIGURATION_ACADEMIC_LEADERSHIP => self::composeAcademicAdministrative(
                $data
            ),
            self::CONFIGURATION_INSTITUTIONAL_LEADERSHIP => self::composeInstitutionalAdministrative(
                $data
            ),
            self::CONFIGURATION_CLINICAL_LEADERSHIP => self::composeClinicalLeadership(
                $data
            ),
            default => '',
        };
    }

    /**
     * Compose from raw entry data; non-arrays yield an empty string.
     *
     * @param  mixed  $raw  Entry field value (array payload or other).
     * @return string Composed title, or empty when input is not an array.
     */
    public static function composeFromMixed(
        mixed $raw
    ): string {
        // Check if the raw value is a structured payload
        if (! \is_array($raw)) {
            // Return an empty string to signal that the input is not an array
            return '';
        }

        // Return the composed title
        /** @var array<string, mixed> $raw */
        return self::compose($raw);
    }

    /**
     * Default stored shape for a new role_title value (publish + CP `emptyRoleTitle`).
     *
     * @param  string|null  $configuration  Initial `configuration` key, or null
     *                                      when no default mode applies
     * @return array<string, mixed> Initial subfields for the given configuration
     */
    public static function emptyValueTemplate(
        ?string $configuration,
        ?string $mode = null
    ): array {
        $resolvedMode = $mode ?? self::MODE_STRUCTURED;
        $out = [
            'configuration' => $configuration,
            'mode' => $resolvedMode,
        ] + self::emptyValueSharedPayload();

        if ($resolvedMode === self::MODE_UNSTRUCTURED) {
            /** @var array<string, mixed> $out */
            $out = \array_replace_recursive($out, self::emptyUnstructuredStorableSlice());
        }

        return $out;
    }

    /**
     * First ordered option key for a nested field under a `group` subfield.
     *
     * @param  string  $groupSubfieldKey  Top-level `subfields` key for the group
     * @param  string  $nestedFieldKey  Key under `group.fields`
     * @return string First key, or empty string when none are configured
     */
    public static function firstOrderedOptionKeyForNestedField(
        string $groupSubfieldKey,
        string $nestedFieldKey
    ): string {
        // Delegate to the definition’s nested option order
        return self::definition()->firstOrderedOptionKeyForNestedField(
            $groupSubfieldKey,
            $nestedFieldKey
        );
    }

    /**
     * Normalize the composed one-line title for storage and validation.
     *
     * @param  mixed  $value  Raw `value` key from the payload
     * @return string Empty string when unset or non-string
     */
    public static function normalizedComposedTitleValue(
        mixed $value
    ): string {
        return \is_string($value) ? $value : '';
    }

    /**
     * Subfield values to apply for `stripKeysForConfiguration` so inactive mode fields
     * stay cleared for the active `configuration`.
     *
     * @param  string  $configuration  Active `configuration` value
     * @return array<string, mixed> Overrides merged in `RoleTitle::stripKeysForConfiguration()`
     */
    public static function inactiveSubfieldClearsForConfiguration(
        string $configuration
    ): array {
        // Delegate inactive clears to the definition-driven merge map
        return self::definition()->inactiveSubfieldClearsForConfiguration(
            $configuration
        );
    }

    /**
     * Compose a faculty-mode title from normalized subfields.
     *
     * @param  array<string, mixed>  $data  Normalized role_title payload for faculty mode
     * @return string Single-line composed faculty title.
     */
    private static function composeFaculty(
        array $data
    ): string {
        // Initialize an empty array to store the segments
        $segments = [];

        // Append prefix Title Options labels when each toggle is on
        foreach (
            self::subfieldOptionKeysForComposeSegment(
                self::PRELOAD_KEY_TITLE_OPTIONS,
                self::CONFIGURATION_FACULTY,
                self::COMPOSE_POSITION_PREFIX
            ) as $key
        ) {
            // Add the segment to the array when the checkbox is on
            $segments[] = self::titleOptionIsSet($data, $key) ? self::optionDisplay($key) : '';
        }

        // Append inline Title Options labels when each toggle is on
        foreach (
            self::subfieldOptionKeysForComposeSegment(
                self::PRELOAD_KEY_TITLE_OPTIONS,
                self::CONFIGURATION_FACULTY,
                self::COMPOSE_POSITION_INLINE
            ) as $key
        ) {
            // Add the segment to the array when the checkbox is on
            $segments[] = self::titleOptionIsSet($data, $key) ? self::optionDisplay($key) : '';
        }

        // Add the segment to the array when the scope qualifier is set
        $segments[] = self::optionSegment(
            self::normalizeOptionalString($data['scope_qualifier'] ?? null),
            self::orderedOptionKeysForSubfield('scope_qualifier')
        );

        // Add the segment to the array when the hierarchical modifier is set
        $segments[] = self::optionSegment(
            self::normalizeOptionalString($data['hierarchical_modifier'] ?? null),
            self::orderedOptionKeysForSubfield('hierarchical_modifier')
        );

        // Add the segment to the array when the role is set
        $segments[] = self::bindHyphenPrefixesToRole(
            $segments,
            self::optionSegment(
                self::normalizeOptionalString($data['role'] ?? null),
                self::optionKeysForSubfieldAndConfiguration(
                    self::PRELOAD_KEY_ROLE,
                    self::CONFIGURATION_FACULTY
                )
            )
        );

        // Append the honorific (emeritus / emerita) immediately after the role.
        // It was a `position: suffix` pair of `title_options` checkboxes; it is now a
        // single-choice radios slot, composed here so it still precedes the scope
        // (UAMS-Web/uams-statamic-archive#2138).
        $segments[] = self::optionSegment(
            self::normalizeOptionalString($data['honorific'] ?? null),
            self::orderedOptionKeysForSubfield('honorific')
        );

        // ---
        // The scope composes LAST, after the suffix title options, so an honorific
        // precedes it: `professor emeritus of medicine`, not
        // `professor of medicine emeritus` (UAMS-Web/uams-statamic-archive#2145).
        //
        // A scope with no name still yields an empty segment and drops the phrase
        // entirely, so a faculty title without one is byte-identical to before.
        // ---
        $segments[] = self::composeScopeSuffix($data['scope'] ?? null);

        // Join the segments with a space
        return self::joinSegments($segments);
    }

    /**
     * Compose an academic administrative title from normalized subfields.
     *
     * @param  array<string, mixed>  $data  Normalized payload for academic administrative mode
     * @return string Single-line composed academic administrative title.
     */
    private static function composeAcademicAdministrative(
        array $data
    ): string {
        // Initialize an empty array to store the segments
        $segments = [];

        // Append academic Title Options checkbox labels in compose order
        foreach (
            self::optionKeysForSubfieldAndConfiguration(
                self::PRELOAD_KEY_TITLE_OPTIONS,
                self::CONFIGURATION_ACADEMIC_LEADERSHIP
            ) as $key
        ) {
            $segments[] = self::titleOptionIsSet($data, $key) ? self::optionDisplay($key) : '';
        }

        // Add the segment to the array when the hierarchical modifier is set
        $segments[] = self::optionSegment(
            self::normalizeOptionalString($data['hierarchical_modifier'] ?? null),
            self::orderedOptionKeysForSubfield('hierarchical_modifier')
        );

        // Add the segment to the array when the object is set (UAMS-Web/uams-statamic-archive#2145).
        //
        // An object names the unit generically (`department chair`) and a
        // unit-naming scope names the same unit specifically (`of the Department
        // of Internal Medicine`), so the two cannot both appear. The specific
        // naming wins, which makes `department chair of the Department of
        // Internal Medicine` not composable.
        //
        // A discipline scope names no unit and composes beside the object freely:
        // `section chief of endocrinology`. Academic leadership composed the
        // object unconditionally before this, while clinical leadership dropped
        // it for ANY scope -- the two now apply the same rule.
        $segments[] = self::scopeNamesAUnit($data['scope'] ?? null)
            ? ''
            : self::optionSegment(
                self::normalizeOptionalString($data['object'] ?? null),
                self::optionKeysForSubfieldAndConfiguration(
                    self::PRELOAD_KEY_OBJECT,
                    self::CONFIGURATION_ACADEMIC_LEADERSHIP
                )
            );

        // Add the segment to the array when the positional qualifier is set
        $segments[] = self::optionSegment(
            self::normalizeOptionalString($data['positional_qualifier'] ?? null),
            self::orderedOptionKeysForSubfield('positional_qualifier')
        );

        // Add the segment to the array when the role is set
        $segments[] = self::bindHyphenPrefixesToRole(
            $segments,
            self::optionSegment(
                self::normalizeOptionalString($data['role'] ?? null),
                self::optionKeysForSubfieldAndConfiguration(
                    self::PRELOAD_KEY_ROLE,
                    self::CONFIGURATION_ACADEMIC_LEADERSHIP
                )
            )
        );

        // Add the honorific (emeritus / emerita) immediately after the role, so it
        // precedes the scope rather than composing in list order before the modifier
        // (UAMS-Web/uams-statamic-archive#2138).
        $segments[] = self::optionSegment(
            self::normalizeOptionalString($data['honorific'] ?? null),
            self::orderedOptionKeysForSubfield('honorific')
        );

        // Add the segment to the array when the scope is set
        $segments[] = self::composeScopeSuffix($data['scope'] ?? null);

        // Join the segments with a space
        return self::joinSegments($segments);
    }

    /**
     * Compose an institutional administrative title from normalized subfields.
     *
     * @param  array<string, mixed>  $data  Normalized payload for institutional administrative mode
     * @return string Single-line composed institutional administrative title.
     */
    private static function composeInstitutionalAdministrative(
        array $data
    ): string {
        // Initialize an empty array to store the segments
        $segments = [];

        // Append institutional Title Options checkbox labels in compose order
        foreach (
            self::optionKeysForSubfieldAndConfiguration(
                self::PRELOAD_KEY_TITLE_OPTIONS,
                self::CONFIGURATION_INSTITUTIONAL_LEADERSHIP
            ) as $key
        ) {
            $segments[] = self::titleOptionIsSet($data, $key) ? self::optionDisplay($key) : '';
        }

        // Add the segment to the array when the hierarchical modifier is set
        $segments[] = self::optionSegment(
            self::normalizeOptionalString($data['hierarchical_modifier'] ?? null),
            self::orderedOptionKeysForSubfield('hierarchical_modifier')
        );

        // Add the segment to the array when the positional qualifier is set
        $segments[] = self::optionSegment(
            self::normalizeOptionalString($data['positional_qualifier'] ?? null),
            self::orderedOptionKeysForSubfield('positional_qualifier')
        );

        // Add the segment to the array when the role is set
        $segments[] = self::bindHyphenPrefixesToRole(
            $segments,
            self::optionSegment(
                self::normalizeOptionalString($data['role'] ?? null),
                self::optionKeysForSubfieldAndConfiguration(
                    self::PRELOAD_KEY_ROLE,
                    self::CONFIGURATION_INSTITUTIONAL_LEADERSHIP
                )
            )
        );

        // Add the honorific (emeritus / emerita) immediately after the role, so it
        // precedes the scope rather than composing in list order before the modifier
        // (UAMS-Web/uams-statamic-archive#2138).
        $segments[] = self::optionSegment(
            self::normalizeOptionalString($data['honorific'] ?? null),
            self::orderedOptionKeysForSubfield('honorific')
        );

        // Add the scope suffix last, after the honorific.
        $segments[] = self::composeScopeSuffix($data['scope'] ?? null);

        // Join the segments with a space
        return self::joinSegments($segments);
    }

    /**
     * Compose a clinical leadership title from normalized subfields.
     *
     * Reads only the three slots `available_in` grants this mode, the Title
     * Options prefixes, the hierarchical modifier, and the role. `object`,
     * `positional_qualifier`, `scope_qualifier` and the `scope` group are
     * scoped to other configurations in `config/role_title.php`, so a payload
     * carrying them stale composes as though they were absent.
     *
     * Unlike the academic and institutional composers this composes no honorific:
     * the `honorific` radios slot (`emeritus` / `emerita`) does not list
     * `clinical_leadership` in its `available_in`, so this mode offers neither form
     * and reaches none of the after-role placement the other composers apply.
     *
     * @param  array<string, mixed>  $data  Normalized payload for clinical leadership mode
     * @return string Single-line composed clinical leadership title.
     */
    private static function composeClinicalLeadership(
        array $data
    ): string {
        // Initialize an empty array to store the segments
        $segments = [];

        // Append clinical Title Options checkbox labels in compose order
        foreach (
            self::optionKeysForSubfieldAndConfiguration(
                self::PRELOAD_KEY_TITLE_OPTIONS,
                self::CONFIGURATION_CLINICAL_LEADERSHIP
            ) as $key
        ) {
            $segments[] = self::titleOptionIsSet($data, $key) ? self::optionDisplay($key) : '';
        }

        // Add the segment to the array when the hierarchical modifier is set
        $segments[] = self::optionSegment(
            self::normalizeOptionalString($data['hierarchical_modifier'] ?? null),
            self::orderedOptionKeysForSubfield('hierarchical_modifier')
        );

        // Add the segment to the array when the object is set (UAMS-Web/uams-statamic-archive#2143, narrowed by UAMS-Web/uams-statamic-archive#2145).
        //
        // The scope names the unit specifically and the object names it generically, so the
        // two are alternatives rather than an addition: `UAMS-Web/uams-statamic-archive#2143` requires that a clinical
        // title cannot carry both for the same unit. The specific naming wins, which makes
        // `service line medical director of the Cancer Service Line` not composable.
        //
        // UAMS-Web/uams-statamic-archive#2143 suppressed the object for ANY non-empty scope, which over-blocked: a
        // discipline scope names no unit, so `division chief of breast surgery` lost its
        // object for no reason. The test is now whether the scope names a UNIT.
        $segments[] = self::scopeNamesAUnit($data['scope'] ?? null)
            ? ''
            : self::optionSegment(
                self::normalizeOptionalString($data['object'] ?? null),
                self::optionKeysForSubfieldAndConfiguration(
                    self::PRELOAD_KEY_OBJECT,
                    self::CONFIGURATION_CLINICAL_LEADERSHIP
                )
            );

        // Add the segment to the array when the role is set
        $segments[] = self::bindHyphenPrefixesToRole(
            $segments,
            self::optionSegment(
                self::normalizeOptionalString($data['role'] ?? null),
                self::optionKeysForSubfieldAndConfiguration(
                    self::PRELOAD_KEY_ROLE,
                    self::CONFIGURATION_CLINICAL_LEADERSHIP
                )
            )
        );

        // Add the segment to the array when the scope is set (UAMS-Web/uams-statamic-archive#2143).
        $segments[] = self::composeScopeSuffix($data['scope'] ?? null);

        // Join the segments with a space
        return self::joinSegments($segments);
    }

    /**
     * Build the trailing scope phrase (joiner + name) for administrative modes.
     *
     * @param  mixed  $scope  `scope` sub-payload or non-array.
     * @return string Localized suffix, or empty when the scope name is blank.
     */
    private static function composeScopeSuffix(
        mixed $scope
    ): string {
        // Resolve the effective joiner, or null when there is no scope phrase
        $joiner = self::resolvedScopeJoiner($scope);

        // Emit nothing when the scope contributes no phrase
        if ($joiner === null) {
            // Return an empty string to signal that the scope is absent
            return '';
        }

        // Read the scope name through the same guard the resolver used
        $name = self::scopeName($scope);

        // The article is a separate choice (UAMS-Web/uams-statamic-archive#2546): a linked unit always takes it,
        // and a typed scope takes it only when its own `scope_article` says so
        $withArticle = self::scopeNamesAUnit($scope) || self::scopeArticle($scope) === 'the';

        // Interpolate the scoped phrase from the `compose.scope_{joiner}[_the]` template
        $templateKey = 'aether-people::fieldtypes.role_title.compose.scope_'.$joiner.($withArticle ? '_the' : '');

        // Interpolate the scoped phrase from the template
        return __(
            $templateKey,
            [
                'name' => $name,
            ]
        );
    }

    /**
     * The name a scope composes: the linked unit's display name, else the
     * trimmed typed `scope_name`, else an empty string.
     *
     * One guarded read shared by the resolver and the rendered phrase, so
     * neither can disagree with the other about whether a name is present.
     *
     * A linked unit wins outright (UAMS-Web/uams-statamic-archive#2146). Its name is read at compose time, so
     * renaming the unit renames the title. A link that does not resolve to a
     * named unit composes NO name rather than falling back to the typed text:
     * falling back would let a row link one unit and print another, which is
     * the disagreement the link exists to remove. `role-title:link-scope-units`
     * reports such rows.
     *
     * @param  mixed  $scope  `scope` sub-payload or non-array
     * @return string Trimmed name, or '' when absent or not a string
     */
    private static function scopeName(
        mixed $scope
    ): string {
        // Only an array payload can carry a name. Unreachable: both callers pass a
        // scope that `resolvedScopeJoiner()` has already found to be an array.
        // @pest-mutate-ignore: EmptyStringToNotEmpty, RemoveEarlyReturn
        if (! \is_array($scope)) {
            // Report no name
            return '';
        }

        // A linked unit names the scope, and only it does
        $unitId = RoleTitleScopeUnit::idFromScope($scope);

        if ($unitId !== null) {
            return RoleTitleScopeUnit::displayName($unitId) ?? '';
        }

        // Read the raw name from the scope data
        $name = $scope['scope_name'] ?? null;

        // Return the trimmed name when it is a string
        return \is_string($name) ? \trim($name) : '';
    }

    /**
     * The joiner a scope payload effectively composes with, or null when it
     * composes nothing at all.
     *
     * Both the rendered phrase and the object/scope exclusivity rule read this,
     * so the two cannot disagree about which joiner a payload means. An unknown
     * or absent joiner resolves to the configured default exactly as the
     * rendered phrase does.
     *
     * @param  mixed  $scope  `scope` sub-payload or non-array
     * @return string|null Resolved joiner storage key, or null when the scope is absent
     */
    private static function resolvedScopeJoiner(
        mixed $scope
    ): ?string {
        // Check if scope data is a usable array.
        //
        // Kept for locality even though it cannot be killed: `scopeName()` also
        // rejects a non-array, so falling through returns null one branch later
        // either way. Removing it would make this method's correctness depend on
        // that method's internals, and the array read below on a future edit.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! \is_array($scope)) {
            // Report no scope when the payload is not an array
            return null;
        }

        // A scope with no name composes nothing, whatever its joiner says
        if (self::scopeName($scope) === '') {
            // Report no scope
            return null;
        }

        // Read the scope joiner from the scope data. Equivalent for the '' fallback:
        // no joiner option is keyed by an empty or any other unknown text.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $joiner = isset($scope['scope_joiner']) && \is_string($scope['scope_joiner'])
            ? \trim($scope['scope_joiner'])
            : '';

        // Check if the scope joiner is not a known stored key
        if (
            ! \in_array(
                $joiner,
                self::orderedOptionKeysForSubfield(
                    'scope',
                    'scope_joiner'
                ),
                true
            )
        ) {
            // Fall back to the default joiner key
            return self::firstOrderedOptionKeyForNestedField(
                'scope',
                'scope_joiner'
            );
        }

        return $joiner;
    }

    /**
     * Whether a scope payload names an organizational UNIT rather than a
     * discipline or portfolio (UAMS-Web/uams-statamic-archive#2145, UAMS-Web/uams-statamic-archive#2546).
     *
     * A scope names a unit when it LINKS one and that link composes a name. Before
     * UAMS-Web/uams-statamic-archive#2546 this read a `names_a_unit` flag on the joiner, because a typed name is
     * free text whose capitalization cannot be trusted; a link is a typed reference
     * instead, so unit-ness now follows it. A typed scope names no unit, whatever
     * its article or casing, and composes beside an object freely.
     *
     * An object names a unit generically and a linked unit names the same one
     * specifically, so the two cannot both appear.
     *
     * @param  mixed  $scope  `scope` sub-payload or non-array
     * @return bool True when the scope composes a phrase from a linked unit
     */
    public static function scopeNamesAUnit(
        mixed $scope
    ): bool {
        // A scope that composes nothing names nothing, linked or not
        if (self::resolvedScopeJoiner($scope) === null) {
            return false;
        }

        // Only a linked unit names a unit
        return \is_array($scope) && RoleTitleScopeUnit::idFromScope($scope) !== null;
    }

    /**
     * Whether a title payload selects an object that composes (UAMS-Web/uams-statamic-archive#2546).
     *
     * Only academic and clinical leadership compose an object, so an object left
     * in any other configuration (stale data the Control Panel resets on a switch)
     * selects nothing. The optional-none sentinel and a blank value select nothing.
     *
     * @param  mixed  $title  role_title payload or non-array
     * @return bool True when an object composes in this configuration
     */
    public static function objectSelected(
        mixed $title
    ): bool {
        if (! \is_array($title)) {
            return false;
        }

        // Only these configurations compose an object segment
        if (! \in_array(
            $title['configuration'] ?? null,
            [self::CONFIGURATION_ACADEMIC_LEADERSHIP, self::CONFIGURATION_CLINICAL_LEADERSHIP],
            true
        )) {
            return false;
        }

        $object = $title['object'] ?? null;

        return \is_string($object)
            && \trim($object) !== ''
            && \trim($object) !== self::optionalSelectionNone();
    }

    /**
     * The article a TYPED scope asks for, or null when it asks for none (UAMS-Web/uams-statamic-archive#2546).
     *
     * A linked unit ignores this and always takes the article.
     *
     * @param  mixed  $scope  `scope` sub-payload or non-array
     * @return string|null `the`, or null
     */
    private static function scopeArticle(
        mixed $scope
    ): ?string {
        // Only an array payload carries an article choice. Unreachable: the only
        // caller passes a scope `resolvedScopeJoiner()` has already found to be an array.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! \is_array($scope)) {
            return null;
        }

        $article = $scope['scope_article'] ?? null;

        return \is_string($article) && \trim($article) === 'the' ? 'the' : null;
    }

    /**
     * Join non-empty trimmed segments with single spaces, then collapse `- ` at joins.
     *
     * @param  list<string>  $segments  Ordered phrase fragments from compose steps
     * @return string Space-delimited composed line.
     */
    private static function joinSegments(
        array $segments
    ): string {
        // Initialize an empty array to store the trimmed segments
        $out = [];

        // Collect trimmed non-empty segment strings
        foreach ($segments as $segment) {
            // Trim the segment
            $segment = \trim($segment);

            // Check if the segment is not empty
            if ($segment !== '') {
                // Add the segment to the array
                $out[] = $segment;
            }
        }

        // Join the segments with a space. Hyphen-terminated fragments are bound to the
        // role by `bindHyphenPrefixesToRole()` before they reach here, so nothing in the
        // finished line needs rewriting - which is what kept editor-typed scope names
        // intact (UAMS-Web/uams-statamic-archive#2139).
        return \implode(' ', $out);
    }

    /**
     * Move hyphen-terminated fragments out of the segment list and onto the role.
     *
     * A fragment whose display ends in `-` (today only `co-`) is a bound prefix of the
     * ROLE, not of whichever fragment happens to sit next to it. Binding it here rather
     * than collapsing `- ` in the finished line fixes two things at once: the prefix can
     * no longer capture an object, hierarchical modifier or positional qualifier that sits
     * between it and the role, and an editor-typed string containing `- ` is left alone,
     * because nothing rewrites the composed output any more (UAMS-Web/uams-statamic-archive#2139).
     *
     * Returns '' when the role is empty, so a selected prefix never composes on its own.
     *
     * @param  list<string>  $segments  Segment list, mutated to blank the moved fragments.
     * @param  string  $role  The role fragment the prefixes bind to.
     * @return string The role with its bound prefixes, or '' when there is no role.
     */
    private static function bindHyphenPrefixesToRole(
        array &$segments,
        string $role
    ): string {
        $prefixes = '';

        foreach ($segments as $index => $segment) {
            if (\str_ends_with($segment, '-')) {
                $prefixes .= $segment;
                $segments[$index] = '';
            }
        }

        if (\trim($role) === '') {
            return '';
        }

        return $prefixes.$role;
    }

    /**
     * Trim a string subfield, or null when it is not a string.
     *
     * A blank value trims to `''`, which `optionSegment()` rejects like any other
     * key that is not an option.
     *
     * @param  mixed  $value  Raw subfield value.
     * @return string|null Trimmed string, or null.
     */
    private static function normalizeOptionalString(
        mixed $value
    ): ?string {
        // Reject non-strings as unset optional values
        return \is_string($value) ? \trim($value) : null;
    }

    /**
     * Localized segment when the stored value is one of the allowed option keys.
     *
     * @param  ?string  $normalized  Trimmed stored key, or null when unset
     * @param  list<string>  $allowedSuffixes  Allowed storage keys for this subfield
     * @return string Localized label, or empty when not allowed or unset
     */
    private static function optionSegment(
        ?string $normalized,
        array $allowedSuffixes
    ): string {
        // Check if the normalized value is unset or not in the allowed suffixes
        if ($normalized === null || ! \in_array($normalized, $allowedSuffixes, true)) {
            // ---
            // Return an empty string to signal that the normalized value is not in the
            // allowed suffixes
            // ---
            return '';
        }

        // Return the localized option label
        return self::optionDisplay($normalized);
    }

    /**
     * Localized option label for composed title output (same strings as CP radios).
     *
     * @param  string  $key  `role_title.field.option.{key}` suffix
     * @return string Translated label for the option key
     */
    private static function optionDisplay(
        string $key
    ): string {
        return __('aether-people::fieldtypes.role_title.field.option.'.$key);
    }
}
