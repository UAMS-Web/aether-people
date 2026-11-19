<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Fieldtypes;

use Illuminate\Validation\Rule;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use UAMSWeb\Aether\Enums\Isco08;
use UAMSWeb\Aether\People\Rules\OnetSocOccupationCode;
use UAMSWeb\Aether\People\Rules\RoleTitlePerConfigurationOptionRule;
use UAMSWeb\Aether\People\Rules\RoleTitleScopeJoinerRule;
use UAMSWeb\Aether\People\Rules\RoleTitleScopeUnitRule;
use UAMSWeb\Aether\People\Support\RoleTitleDefinition;
use UAMSWeb\Aether\People\Support\RoleTitleSchemaOutputComputer;
use UAMSWeb\Aether\People\Support\RoleTitleScopeUnit;
use UAMSWeb\Aether\People\Support\RoleTitleUnstructuredCpBridge;
use UAMSWeb\Aether\People\Support\RoleTitleValue;

/**
 * Statamic `role_title` fieldtype: exposes subfields in the CP, preloads meta
 * for the Vue component, normalizes and validates stored editor input, and
 * exposes virtual derived data (composed one-line `value` and `schema` output
 * slices) through `preProcess()`, `augment()`, and validation, matching Statamic
 * computed-value style behavior without persisting those derived keys on the
 * entry (see Statamic computed values docs).
 *
 * Stored keys, label maps, composition, empty-template shape, and validation
 * helpers are defined on `RoleTitleValue`. See that class when adding modes,
 * radios, checkboxes, or scope behavior. Blueprint mode keys come from
 * `RoleTitleValue::configurationHandles()` (`config/role_title.php`).
 */
class RoleTitle extends Fieldtype
{
    /**
     * Display title in the fieldtype picker.
     *
     * @var string
     */
    protected static $title = 'Role Title';

    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string
     */
    protected $component = 'role_title';

    /**
     * Whether this fieldtype appears in the fieldtype picker.
     *
     * @var bool
     */
    // The coverage driver attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Flipped by hand, it fails "declares its picker metadata".
    // @pest-mutate-ignore: TrueToFalse
    protected $selectable = true;

    /**
     * Whether this fieldtype can be chosen inside form builder UIs.
     *
     * Disabled: the CP component is a structured/unstructured subsystem with
     * per-configuration radios, checkboxes, group fields, and schema.org-aware
     * outputs that don't translate to a single Antlers template. UAMS forms
     * collect typed job titles via a plain text field instead. Re-enabling
     * requires the work scoped in the (closed) follow-up issue that was
     * spun off from UAMS-Web/uams-statamic-archive#670.
     *
     * @var bool
     */
    // The coverage driver attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Flipped by hand, it fails "declares its picker metadata".
    // @pest-mutate-ignore: FalseToTrue
    protected $selectableInForms = false;

    /**
     * Fieldtype picker search keywords.
     *
     * @var array<int, string>
     */
    // The coverage driver attributes no coverage to a property default, so Pest selects no test for its mutants.
    // Removing any entry by hand, it fails "declares its picker metadata".
    // @pest-mutate-ignore: RemoveArrayItem
    protected $keywords = [
        'role',
        'title',
        'position',
    ];

    /**
     * Fieldtype picker category tags.
     *
     * @var array<int, string>
     */
    // The coverage driver attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Removing the entry by hand, it fails "declares its picker metadata".
    // @pest-mutate-ignore: RemoveArrayItem
    protected $categories = [
        'text',
    ];

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'text-formatting';
    }

    /**
     * Blueprint editor: which configurations exist and defaults.
     *
     * @return array<int, array<string, mixed>> Blueprint editor sections for this fieldtype
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Load default Statamic fieldtype config rows from the parent
        $parent = parent::configFieldItems();

        // Build configuration mode labels from `config/role_title.php` handles
        $configurationOptions = [];

        foreach (RoleTitleValue::configurationHandles() as $handle) {
            $configurationOptions[$handle] = __(
                'aether-people::fieldtypes.role_title.configuration.'.$handle
            );
        }

        // Default: all configured modes enabled for new blueprints
        $defaultEnabledConfigurations = \array_keys($configurationOptions);

        $requiredUnstructuredSubfieldOptions = [
            'value' => __(
                'aether-people::fieldtypes.role_title.field.composed_value'
            ),
            'occupation_same_as' => __(
                'aether-people::fieldtypes.role_title.field.unstructured.occupation_same_as'
            ),
            'occupation_onet_soc' => __(
                'aether-people::fieldtypes.role_title.field.unstructured.occupation_onet_soc'
            ),
            'occupation_isco_08' => __(
                'aether-people::fieldtypes.role_title.field.unstructured.occupation_isco_08'
            ),
            'occupational_field_same_as' => __(
                'aether-people::fieldtypes.role_title.field.unstructured.occupational_field_same_as'
            ),
        ];

        // Build role-title specific fields (modes, default, border)
        $custom = [
            [
                'display' => __('aether-people::fieldtypes.role_title.config.section_heading'),
                'fields' => [
                    'enabled_title_structures' => [
                        'type' => 'checkboxes',
                        'display' => __(
                            'aether-people::fieldtypes.role_title.config.enabled_title_structures_display'
                        ),
                        'instructions' => __(
                            'aether-people::fieldtypes.role_title.config.enabled_title_structures_instructions'
                        ),
                        'options' => [
                            RoleTitleValue::MODE_STRUCTURED => __(
                                'aether-people::fieldtypes.role_title.structure.structured'
                            ),
                            RoleTitleValue::MODE_UNSTRUCTURED => __(
                                'aether-people::fieldtypes.role_title.structure.unstructured'
                            ),
                        ],
                        'default' => RoleTitleValue::titleStructureHandles(),
                        'validate' => [
                            'required',
                            'array',
                            'min:1',
                        ],
                    ],
                    'default_title_structure' => [
                        'type' => 'select',
                        'display' => __(
                            'aether-people::fieldtypes.role_title.config.default_title_structure_display'
                        ),
                        'instructions' => __(
                            'aether-people::fieldtypes.role_title.config.default_title_structure_instructions'
                        ),
                        'options' => [
                            RoleTitleValue::MODE_STRUCTURED => __(
                                'aether-people::fieldtypes.role_title.structure.structured'
                            ),
                            RoleTitleValue::MODE_UNSTRUCTURED => __(
                                'aether-people::fieldtypes.role_title.structure.unstructured'
                            ),
                        ],
                        'default' => RoleTitleValue::MODE_STRUCTURED,
                        'if' => [
                            'enabled_title_structures' => 'custom selectionCount:gt,1',
                        ],
                    ],
                    'enabled_configurations' => [
                        'type' => 'checkboxes',
                        'display' => __(
                            'aether-people::fieldtypes.role_title.config.enabled_configurations_display'
                        ),
                        'instructions' => __(
                            'aether-people::fieldtypes.role_title.config.enabled_configurations_instructions'
                        ),
                        'options' => $configurationOptions,
                        'default' => $defaultEnabledConfigurations,
                        'validate' => [
                            'required',
                            'array',
                            'min:1',
                        ],
                        'if' => [
                            'enabled_title_structures' => 'custom selectionContains:structured',
                        ],
                    ],
                    'default_configuration' => [
                        'type' => 'select',
                        'display' => __(
                            'aether-people::fieldtypes.role_title.config.default_configuration_display'
                        ),
                        'instructions' => __(
                            'aether-people::fieldtypes.role_title.config.default_configuration_instructions'
                        ),
                        'options' => $configurationOptions,
                        'default' => RoleTitleValue::defaultConfigurationHandle(),
                        'if' => [
                            'enabled_title_structures' => 'custom roleTitleDefaultConfigurationVisible',
                        ],
                    ],
                    'required_unstructured_subfields' => [
                        'type' => 'checkboxes',
                        'display' => __(
                            'aether-people::fieldtypes.role_title.config.required_unstructured_subfields_display'
                        ),
                        'instructions' => __(
                            'aether-people::fieldtypes.role_title.config.required_unstructured_subfields_instructions'
                        ),
                        'options' => $requiredUnstructuredSubfieldOptions,
                        'default' => RoleTitleValue::defaultRequiredUnstructuredSubfieldHandles(),
                        'validate' => [
                            'nullable',
                            'array',
                        ],
                        'if' => [
                            'enabled_title_structures' => 'custom selectionContains:unstructured',
                        ],
                    ],
                    'scope_unit_row_link' => [
                        'type' => 'text',
                        'display' => __(
                            'aether-people::fieldtypes.role_title.config.scope_unit_row_link_display'
                        ),
                        'instructions' => __(
                            'aether-people::fieldtypes.role_title.config.scope_unit_row_link_instructions'
                        ),
                        'if' => [
                            'enabled_title_structures' => 'custom selectionContains:structured',
                        ],
                    ],
                    'border' => [
                        'type' => 'toggle',
                        'display' => __(
                            'aether-people::fieldtypes.role_title.config.border_display'
                        ),
                        'instructions' => __(
                            'aether-people::fieldtypes.role_title.config.border_instructions'
                        ),
                        'default' => true,
                    ],
                ],
            ],
        ];

        // Prepend parent rows, then append custom blueprint fields
        /** @var array<int, array<string, mixed>> $merged */
        $merged = \array_merge(
            $parent,
            $custom
        );

        unset(
            $configurationOptions,
            $defaultEnabledConfigurations,
            $requiredUnstructuredSubfieldOptions,
            $custom,
            $parent
        );

        return $merged;
    }

    /**
     * Top-level validation rule for the stored role_title array shape.
     *
     * @return array<int, string> Laravel rules for the root field value
     */
    #[\Override]
    public function rules(): array
    {
        return ['array'];
    }

    /**
     * Human-readable names for nested validation keys.
     *
     * @return array<string, string> Dotted attribute paths to translated labels
     */
    #[\Override]
    public function extraValidationAttributes(): array
    {
        // Resolve the Statamic field backing this fieldtype
        $field = $this->field();

        // Short-circuit when the field is unavailable
        if (! $field instanceof Field) {
            return [];
        }

        // Prefix validation keys with the field handle
        /** @var string $prefix */
        $prefix = $field->handle();

        // Build dotted paths under this field handle
        $p = static fn (string $path): string => sprintf('%s.%s', $prefix, $path);

        // Collect translated labels for each nested validation path
        $out = [];

        // Map each dotted validation path to a human label for error messages
        foreach (RoleTitleValue::validationAttributeRelativePathToLangSuffix() as $relativePath => $langKey) {
            $out[$p($relativePath)] = $this->roleTitleFieldtypeFieldLabel($langKey);
        }

        // Unset local variables to free up memory
        unset(
            $field,
            $prefix,
            $p
        );

        return $out;
    }

    /**
     * Translated CP / validation label for `role_title.field.{key}` or
     * `role_title.field.option.{key}` when the attribute matches an option label.
     *
     * @param  string  $langKey  Suffix under `role_title.field` in fieldtypes.php
     */
    private function roleTitleFieldtypeFieldLabel(
        string $langKey
    ): string {
        // Use option translations for Title Options checkbox attribute names
        // The option keys and lang suffixes are non-numeric strings, so loose and strict comparison agree (TrueToFalse is equivalent)
        // @pest-mutate-ignore: TrueToFalse
        if (\in_array($langKey, RoleTitleValue::storedTitleOptionBooleanKeys(), true)) {
            return __('aether-people::fieldtypes.role_title.field.option.'.$langKey);
        }

        return __('aether-people::fieldtypes.role_title.field.'.$langKey);
    }

    /**
     * Nested validation rules for sub-keys derived from `config/role_title.php`
     * `subfields` (checkboxes, radios, groups, and per-configuration radios rules).
     *
     * @return array<string, array<int, string|object>> Dotted paths to Laravel rules for nested role_title keys
     */
    #[\Override]
    public function extraRules(): array
    {
        // Resolve the Statamic field backing this fieldtype
        $field = $this->field();

        // Short-circuit when the field is unavailable
        if (! $field instanceof Field) {
            return [];
        }

        // Read blueprint config (required flags, validate array, etc.)
        /** @var array<string, mixed> $config */
        $config = $field->config();

        // Prefix nested rule keys with the field handle
        /** @var string $prefix */
        $prefix = $field->handle();

        // Dotted path to this field’s value for custom rules (`Arr::get` on the bag)
        /** @var string $fieldPathPrefix */
        $fieldPathPrefix = $field->fieldPathPrefix();

        // Detect explicit `required` in the blueprint validate array
        $validate = $config['validate'] ?? [];

        // Treat either UI required or validate-required as required
        $validateHasRequired = \is_array($validate) && \in_array('required', $validate, true);

        // Derive whether subfields should use required_if tightening
        $required = ! empty($config['required']) || $validateHasRequired;

        $enabledTitleStructures = $this->enabledTitleStructureKeys($config);
        $titleStructurePickerVisible = \count($enabledTitleStructures) > 1;
        // The enabled structures are the canonical handle strings, so loose and strict comparison agree (TrueToFalse is equivalent)
        // @pest-mutate-ignore: TrueToFalse
        $structuredTitleStructureEnabled = \in_array(
            RoleTitleValue::MODE_STRUCTURED,
            $enabledTitleStructures,
            true
        );
        $unstructuredRequiredSegments = RoleTitleValue::requiredUnstructuredSubfieldHandlesFromConfig(
            $config
        );

        // Whether a required field also requires this unstructured segment
        // The segment handles are non-numeric strings, so loose and strict comparison agree (TrueToFalse is equivalent)
        // @pest-mutate-ignore: TrueToFalse
        $segmentRequired = static fn (string $segment): bool => $required
            && \in_array($segment, $unstructuredRequiredSegments, true);

        // Build dotted paths under this field handle
        $dot = static fn (string $path): string => sprintf('%s.%s', $prefix, $path);

        // Build cross-field rule targets; `{this}.` is replaced by Statamic’s nested
        // validation prefix (e.g. `title_replicator.0.`) so `required_if` resolves
        // against the same bag keys as the prefixed rule attributes.
        /** @var string $handle */
        $handle = $field->handle();
        $thisKey = static fn (string $path): string => '{this}.'.$handle.'.'.$path;

        // Start the nested rule map
        $rules = [];

        $modeRules = [
            'string',
            Rule::in(RoleTitleValue::titleStructureHandles()),
        ];

        if ($titleStructurePickerVisible && $required) {
            \array_unshift($modeRules, 'required');
        } else {
            \array_unshift($modeRules, 'nullable');
        }

        $rules[$dot('mode')] = $modeRules;

        $rules[$dot('configuration')] = [
            'nullable',
            'string',
            Rule::in(RoleTitleValue::configurationHandles()),
        ];

        if (
            $structuredTitleStructureEnabled
            && $required
        ) {
            $rules[$dot('configuration')][] = 'required_if:'.$thisKey('mode').','.RoleTitleValue::MODE_STRUCTURED;
        }

        // Allow composed string storage alongside structured fields; unstructured persists free text
        $valueRules = ['nullable', 'string'];

        if ($segmentRequired('value')) {
            $valueRules[] = 'required_if:'.$thisKey('mode').','.RoleTitleValue::MODE_UNSTRUCTURED;
        }

        $rules[$dot('value')] = $valueRules;

        $occSameRules = ['nullable', 'array'];

        if ($segmentRequired('occupation_same_as')) {
            $occSameRules[] = 'required_if:'.$thisKey('mode').','.RoleTitleValue::MODE_UNSTRUCTURED;
            $occSameRules[] = 'min:1';
        }

        $rules[$dot('occupation.same_as')] = $occSameRules;
        $rules[$dot('occupation.same_as.*')] = ['nullable', 'string', 'url'];
        $rules[$dot('occupation.category_code')] = ['nullable', 'array'];
        $onetRules = ['nullable', 'array'];

        if ($segmentRequired('occupation_onet_soc')) {
            $onetRules[] = 'required_if:'.$thisKey('mode').','.RoleTitleValue::MODE_UNSTRUCTURED;
            $onetRules[] = 'min:1';
        }

        $rules[$dot('occupation.category_code.onet_soc')] = $onetRules;
        $rules[$dot('occupation.category_code.onet_soc.*')] = ['nullable', 'string', new OnetSocOccupationCode];
        $iscoRules = ['nullable', 'array'];

        if ($segmentRequired('occupation_isco_08')) {
            $iscoRules[] = 'required_if:'.$thisKey('mode').','.RoleTitleValue::MODE_UNSTRUCTURED;
            $iscoRules[] = 'min:1';
        }

        $rules[$dot('occupation.category_code.isco_08')] = $iscoRules;
        $rules[$dot('occupation.category_code.isco_08.*')] = [
            'nullable',
            'string',
            Rule::enum(Isco08::class),
        ];
        $fieldSameRules = ['nullable', 'array'];

        if ($segmentRequired('occupational_field_same_as')) {
            $fieldSameRules[] = 'required_if:'.$thisKey('mode').','.RoleTitleValue::MODE_UNSTRUCTURED;
            $fieldSameRules[] = 'min:1';
        }

        $rules[$dot('occupational_field.same_as')] = $fieldSameRules;
        $rules[$dot('occupational_field.same_as.*')] = ['nullable', 'string', 'url'];
        $rules[$dot('occupational_field.category_code')] = ['nullable', 'array'];

        foreach (RoleTitleUnstructuredCpBridge::cpKeys() as $cpKey) {
            $rules[$dot($cpKey)] = ['nullable', 'array'];
        }

        // Allow server-computed schema output maps (`occupation`, `occupational_field`) on save
        foreach (RoleTitleSchemaOutputComputer::storedOutputSubfieldKeys() as $outputKey) {
            $rules[$dot($outputKey)] = ['nullable', 'array'];
        }

        // Register boolean rules for each option key under every `checkboxes` subfield
        foreach (RoleTitleValue::checkboxSubfieldKeys() as $checkboxSubfieldKey) {
            foreach (
                RoleTitleValue::orderedOptionKeysForSubfield($checkboxSubfieldKey) as $booleanKey
            ) {
                $rules[$dot($checkboxSubfieldKey.'.'.$booleanKey)] = ['boolean'];
            }
        }

        // Top-level `radios`: optional-none uses sentinel-aware `Rule::in`; flagged
        // rows use `RoleTitlePerConfigurationOptionRule` (see `role_title.php`).
        foreach (RoleTitleValue::topLevelRadiosSubfieldKeys() as $radiosKey) {
            $row = RoleTitleValue::topLevelSubfieldRow($radiosKey);

            // topLevelRadiosSubfieldKeys() lists only rows that are arrays, so this skip is never taken (ContinueToBreak is equivalent)
            // @pest-mutate-ignore: ContinueToBreak
            if (! \is_array($row)) {
                continue;
            }

            if (
                RoleTitleValue::radiosSubfieldUsesPerConfigurationOptionValidation(
                    $radiosKey
                )
            ) {
                $rules[$dot($radiosKey)] = [
                    'nullable',
                    'string',
                    new RoleTitlePerConfigurationOptionRule(
                        $fieldPathPrefix,
                        $required,
                        $radiosKey
                    ),
                ];

                continue;
            }

            $keys = RoleTitleValue::orderedOptionKeysForSubfield($radiosKey);
            $allowOptionalNone = ($row['allow_optional_none'] ?? false) === true;

            $inRule = $allowOptionalNone
                ? Rule::in(
                    RoleTitleValue::ruleInValuesWithOptionalNone(
                        RoleTitleValue::fieldOptionLabelsForKeys($keys)
                    )
                )
                : Rule::in($keys);

            $rules[$dot($radiosKey)] = [
                'nullable',
                'string',
                $inRule,
            ];
        }

        // Nested `radios` / `text` under each `group` subfield
        foreach (RoleTitleValue::groupSubfieldKeys() as $groupKey) {
            $groupDef = RoleTitleValue::topLevelSubfieldRow($groupKey);

            // groupSubfieldKeys() lists only rows that are arrays, so this skip is never taken (ContinueToBreak is equivalent)
            // @pest-mutate-ignore: ContinueToBreak
            if (! \is_array($groupDef)) {
                continue;
            }

            $nestedFields = $groupDef['fields'] ?? null;

            if (! \is_array($nestedFields)) {
                continue;
            }

            foreach ($nestedFields as $nestedKey => $fieldDef) {
                if (! \is_array($fieldDef)) {
                    continue;
                }

                // A row with no type matches none of the types below, whatever the fallback (EmptyStringToNotEmpty is equivalent)
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                $nestedType = $fieldDef['type'] ?? '';
                $path = $groupKey.'.'.$nestedKey;

                if ($nestedType === 'text') {
                    $rules[$dot($path)] = [
                        'nullable',
                        'string',
                    ];

                    continue;
                }

                // A linked unit: resolves, names a unit, and agrees with the row (UAMS-Web/uams-statamic-archive#2146)
                if ($nestedType === 'entries') {
                    $rowLink = $config['scope_unit_row_link'] ?? null;

                    $rules[$dot($path)] = [
                        'nullable',
                        new RoleTitleScopeUnitRule(
                            $fieldPathPrefix,
                            \is_string($rowLink) && \trim($rowLink) !== '' ? \trim($rowLink) : null
                        ),
                    ];

                    continue;
                }

                if ($nestedType !== 'radios') {
                    continue;
                }

                $nestedKeys = RoleTitleValue::orderedOptionKeysForSubfield(
                    $groupKey,
                    $nestedKey
                );
                $allowOptionalNone = ($fieldDef['allow_optional_none'] ?? false) === true;

                $inRule = $allowOptionalNone
                    ? Rule::in(
                        RoleTitleValue::ruleInValuesWithOptionalNone(
                            RoleTitleValue::fieldOptionLabelsForKeys($nestedKeys)
                        )
                    )
                    : Rule::in($nestedKeys);

                $ruleList = [
                    'nullable',
                    'string',
                ];

                $requiredWithSibling = $fieldDef['required_with_nested_field'] ?? null;

                if (\is_string($requiredWithSibling) && $requiredWithSibling !== '') {
                    $ruleList[] = 'required_with:'.$thisKey(
                        $groupKey.'.'.$requiredWithSibling
                    );
                }

                $ruleList[] = $inRule;

                // A joiner option that requires the article is checked against the typed scope (UAMS-Web/uams-statamic-archive#2546)
                // Nested keys are non-numeric strings, so the cast changes nothing (RemoveStringCast is equivalent)
                // @pest-mutate-ignore: RemoveStringCast
                if (RoleTitleDefinition::instance()->articleRequiringOptionKeysForNestedField($groupKey, (string) $nestedKey) !== []) {
                    $ruleList[] = new RoleTitleScopeJoinerRule($fieldPathPrefix);
                }

                $rules[$dot($path)] = $ruleList;
            }
        }

        // Unset local variables to free up memory
        unset(
            $field,
            $config,
            $validate,
            $validateHasRequired,
            $required,
            $enabledTitleStructures,
            $titleStructurePickerVisible,
            $structuredTitleStructureEnabled,
            $unstructuredRequiredSegments,
            $segmentRequired,
            $prefix,
            $fieldPathPrefix,
            $dot,
            $thisKey,
            $modeRules,
            $valueRules,
            $occSameRules,
            $onetRules,
            $iscoRules,
            $fieldSameRules
        );

        return $rules;
    }

    /**
     * Default value for new entries.
     *
     * @return array<string, mixed> Empty template for the active default configuration
     */
    #[\Override]
    public function defaultValue(): array
    {
        return $this->emptyValue();
    }

    /**
     * CP meta: enabled modes, defaults, border toggle, and option label maps.
     *
     * @return array<string, mixed> Meta passed to the Vue fieldtype component
     */
    #[\Override]
    public function preload(): array
    {
        // Read fieldtype config from the blueprint
        $config = $this->config();

        // Normalize config to an array for safe reads
        /** @var array<string, mixed> $config */
        $config = \is_array($config) ? $config : [];

        // Merge blueprint toggles with definition-driven CP preload meta
        return [
            'enabled_configurations' => $this->enabledConfigurationKeys($config),
            'default_configuration' => $this->resolveDefaultConfigurationKey($config),
            'enabled_title_structures' => $this->enabledTitleStructureKeys($config),
            'default_title_structure' => $this->resolveDefaultTitleStructureKey($config),
            'required_unstructured_subfields' => RoleTitleValue::requiredUnstructuredSubfieldHandlesFromConfig(
                $config
            ),
            'border' => (bool) ($config['border'] ?? true),
            // The scope's unit picker: stock relationship config, plus meta
            // carrying the linked item so the CP shows it without a fetch (UAMS-Web/uams-statamic-archive#2146)
            'scope_unit_field_config' => RoleTitleScopeUnit::fieldConfig() + [
                'handle' => RoleTitleScopeUnit::NESTED_KEY,
            ],
            'scope_unit_meta' => RoleTitleScopeUnit::preloadMeta(
                $this->linkedScopeUnitId()
            ),
        ] + RoleTitleValue::fieldtypePreloadMetaFromDefinition();
    }

    /**
     * The scope unit this field's current value links, for the picker's meta.
     *
     * @return string|null Linked entry id, or null when none or no field
     */
    private function linkedScopeUnitId(): ?string
    {
        $field = $this->field();

        if (! $field instanceof Field) {
            return null;
        }

        $value = $field->value();

        return RoleTitleScopeUnit::idFromScope(
            \is_array($value) ? ($value[RoleTitleScopeUnit::GROUP_KEY] ?? null) : null
        );
    }

    /**
     * Normalize for the publish form.
     *
     * @param  mixed  $data  Raw field value from the editor.
     * @return array<string, mixed> Normalized value for the publish form
     */
    #[\Override]
    public function preProcess(
        mixed $data
    ): array {
        // Normalize posted or stored editor input to the canonical payload shape
        $normalized = $this->normalizeStored($data);

        if (RoleTitleUnstructuredCpBridge::payloadIsUnstructured($normalized)) {
            $normalized = RoleTitleUnstructuredCpBridge::expandForCp($normalized);
        }

        // Attach composed `value` and schema outputs for CP display (virtual only)
        return $this->mergeDerivedVirtualRoleTitleFields($normalized);
    }

    /**
     * Augment stored data for Antlers/API: add composed `value` and schema outputs
     * without treating them as persisted entry fields.
     *
     * @param  mixed  $value  Raw YAML-stored field value
     * @return mixed Augmented array with virtual derived keys, or original value
     */
    #[\Override]
    public function augment(
        mixed $value
    ): mixed {
        // Pass through non-array fragments unchanged
        if (! \is_array($value)) {
            return $value;
        }

        // Overlay stored data on blueprint defaults so compose inputs are complete
        /** @var array<string, mixed> $merged */
        $merged = \array_replace_recursive(
            $this->emptyValue(),
            $value
        );

        // Merge fresh derived keys (compose / schema) for front-end and APIs
        return $this->mergeDerivedVirtualRoleTitleFields($merged);
    }

    /**
     * Persist normalized array without composed `value` or schema output keys.
     *
     * @param  mixed  $data  Raw field value before persistence.
     * @return array<string, mixed> Normalized storable subfields only
     */
    #[\Override]
    public function process(
        mixed $data
    ): array {
        // Normalize types and merge with defaults before persisting
        $normalized = $this->normalizeStored($data);

        // Drop subfields that belong to inactive configurations
        $out = $this->stripKeysForConfiguration($normalized);

        $out = $this->synchronizeStoredBranchesByMode($out);

        // Unset local variables to free up memory
        unset(
            $normalized
        );

        // Omit virtual derived keys so they are not written to content files
        return $this->stripForPersistence($out);
    }

    /**
     * Ensure validation receives a complete array shape.
     *
     * @param  mixed  $value  Raw value before validation.
     * @return array<string, mixed> Merged template with normalized subfields
     */
    #[\Override]
    public function preProcessValidatable(
        mixed $value
    ): array {
        // Start from the blueprint default empty shape
        $base = $this->emptyValue();

        // Return defaults when the incoming value is not mergeable
        if (! \is_array($value)) {
            return $base;
        }

        // Overlay posted values onto defaults without dropping nested keys
        $merged = \array_replace_recursive(
            $base,
            $value
        );

        // Normalize the merged payload for validation rule evaluation
        $normalized = $this->normalizeStored($merged);

        unset(
            $base,
            $merged
        );

        if (RoleTitleUnstructuredCpBridge::payloadIsUnstructured($normalized)) {
            $normalized = RoleTitleUnstructuredCpBridge::expandForCp($normalized);
        }

        // Include virtual derived fields so rules can reference composed data
        return $this->mergeDerivedVirtualRoleTitleFields($normalized);
    }

    /**
     * Remove virtual derived keys before recomputing composed title / schema slices.
     *
     * @param  array<string, mixed>  $payload  Normalized role_title payload
     * @return array<string, mixed> Payload without stale derived keys
     */
    private function stripVirtualDerivedKeysBeforeRecompose(
        array $payload
    ): array {
        if (($payload['mode'] ?? null) !== RoleTitleValue::MODE_UNSTRUCTURED) {
            unset($payload['value']);
        }

        $mode = $payload['mode'] ?? null;

        foreach (
            RoleTitleSchemaOutputComputer::storedOutputSubfieldKeys() as $handle
        ) {
            // The output handles are non-numeric strings, so loose and strict comparison agree (TrueToFalse is equivalent).
            // The configured outputs are only these two handles, so stopping at the first one skips the same
            // keys as moving past it (ContinueToBreak is equivalent).
            // @pest-mutate-ignore: TrueToFalse, ContinueToBreak
            if (
                $mode === RoleTitleValue::MODE_UNSTRUCTURED
                && \in_array($handle, ['occupation', 'occupational_field'], true)
            ) {
                continue;
            }

            unset($payload[$handle]);
        }

        return $payload;
    }

    /**
     * Final persistence strip: the composed `value` when structured.
     *
     * The CP replicator keys need no strip here, because `normalizeStored()` has
     * already collapsed them, and neither do the schema output keys, because
     * `synchronizeStoredBranchesByMode()` has already dropped them from a
     * structured payload and an unstructured one stores them.
     *
     * @param  array<string, mixed>  $payload  Normalized role_title payload
     * @return array<string, mixed> Storable YAML payload
     */
    private function stripForPersistence(
        array $payload
    ): array {
        if (($payload['mode'] ?? null) !== RoleTitleValue::MODE_UNSTRUCTURED) {
            unset($payload['value']);
        }

        return $payload;
    }

    /**
     * Merge composed `value` and schema-driven outputs for read paths (CP,
     * augmentation, validation).
     *
     * @param  array<string, mixed>  $payload  Normalized role_title payload
     * @return array<string, mixed> Payload including virtual derived keys
     */
    private function mergeDerivedVirtualRoleTitleFields(
        array $payload
    ): array {
        $withDerived = $this->stripVirtualDerivedKeysBeforeRecompose($payload);

        unset($payload);

        if (($withDerived['mode'] ?? null) === RoleTitleValue::MODE_UNSTRUCTURED) {
            $withDerived = RoleTitleValue::normalizeUnstructuredOccupationBranchesForSchemaParity(
                $withDerived
            );
            $withDerived['value'] = \is_string($withDerived['value'] ?? null)
                ? \trim($withDerived['value'])
                : '';

            return $withDerived;
        }

        $withDerived['value'] = RoleTitleValue::compose($withDerived);

        // The strip above removed every output key, so an empty result leaves none behind
        return \array_replace(
            $withDerived,
            RoleTitleSchemaOutputComputer::computeFromNormalizedPayload(
                $withDerived
            )
        );
    }

    /**
     * Resolve the default configuration for new values from blueprint config.
     *
     * @param  array<string, mixed>  $config  Fieldtype config from the blueprint
     * @return string|null Enabled configuration key, or null when none applies.
     */
    private function resolveDefaultConfigurationKey(
        array $config
    ): ?string {
        // Read configured default or fall back to the definition default handle
        $key = $config['default_configuration'] ?? RoleTitleValue::defaultConfigurationHandle();

        // Coerce non-string defaults back to the definition default handle
        $key = \is_string($key) ? $key : RoleTitleValue::defaultConfigurationHandle();

        // Resolve which configuration modes are enabled for this field
        $enabled = $this->enabledConfigurationKeys($config);

        // Check if the configured default is still enabled
        // The key and the enabled handles are non-numeric strings, so loose and strict comparison agree (TrueToFalse is equivalent)
        // @pest-mutate-ignore: TrueToFalse
        if (\in_array($key, $enabled, true)) {
            // Return the configured default key
            return $key;
        }

        // Fall back to the first enabled mode, then the definition default handle
        return $enabled[0] ?? RoleTitleValue::defaultConfigurationHandle();
    }

    /**
     * Return enabled configuration keys in canonical order.
     *
     * @param  array<string, mixed>  $config  Fieldtype config from the blueprint
     * @return list<string> Ordered list of enabled `configuration` string values
     */
    private function enabledConfigurationKeys(
        array $config
    ): array {
        // Read the modern checkboxes selection when present
        $raw = $config['enabled_configurations'] ?? null;

        // Check if enabled modes are selected via checkboxes
        if (
            \is_array($raw)
            && $raw !== []
        ) {
            // Build the enabled list in canonical order
            $ordered = [];

            // Preserve canonical order while filtering to enabled keys
            foreach (RoleTitleValue::configurationHandles() as $key) {
                // Check if this mode appears in the blueprint selection
                if (\in_array($key, $raw, true)) {
                    // Add the enabled key to the ordered list
                    $ordered[] = $key;
                }
            }

            // Return ordered keys when at least one mode is enabled
            if ($ordered !== []) {
                // Return the ordered keys
                return $ordered;
            }
        }

        // Fall back to the configured default mode when checkboxes are absent
        $default = RoleTitleValue::defaultConfigurationHandle();

        // Return a single-mode list when a definition default exists
        if ($default !== null) {
            return [$default];
        }

        // Return no enabled modes when the definition provides no default handle
        return [];
    }

    /**
     * Build the empty template using blueprint default configuration.
     *
     * @return array<string, mixed> Default subfield payload for new entries
     */
    private function emptyValue(): array
    {
        // Read blueprint config for the bound field when available
        /** @var array<string, mixed> $config */
        $config = $this->field()?->config() ?? [];

        // Resolve which configuration mode new entries should start in
        $configuration = $this->resolveDefaultConfigurationKey($config);
        $mode = $this->resolveDefaultTitleStructureKey($config);

        // Return the empty value template for the configuration
        return RoleTitleValue::emptyValueTemplate($configuration, $mode);
    }

    /**
     * Merge incoming data with defaults and normalize types for storage / validation.
     *
     * @param  mixed  $data  Raw field value from the CP or API.
     * @return array<string, mixed> Normalized role_title array for storage
     */
    private function normalizeStored(
        mixed $data
    ): array {
        if (! \is_array($data)) {
            return $this->emptyValue();
        }

        /** @var array<string, mixed> $data */
        /** @var array<string, mixed> $config */
        $config = $this->field()?->config() ?? [];

        if ($this->payloadHasCpKeys($data)) {
            $tmp = $data;
            $tmp['mode'] ??= RoleTitleValue::MODE_UNSTRUCTURED;
            $data = RoleTitleUnstructuredCpBridge::collapseFromCp($tmp);
        }

        /** @var array<string, mixed> $merged */
        $merged = \array_replace_recursive(
            $this->emptyValue(),
            $data
        );

        return $this->normalizeMergedPayload($merged, $config);
    }

    /**
     * @param  array<string, mixed>  $merged  Value merged onto the blueprint empty template
     * @param  array<string, mixed>  $config  Blueprint fieldtype config
     * @return array<string, mixed>
     */
    private function normalizeMergedPayload(
        array $merged,
        array $config
    ): array {
        $base = $this->emptyValue();

        $merged['mode'] = $this->resolveStoredMode(
            \is_string($merged['mode'] ?? null) ? \trim($merged['mode']) : null,
            $this->enabledTitleStructureKeys($config),
            $config
        );

        // Keep a non-empty trimmed configuration string when provided
        $merged['configuration'] = \is_string($merged['configuration'] ?? null)
            && \trim($merged['configuration']) !== ''
            ? \trim($merged['configuration'])
            : $base['configuration'];

        // Legacy migration (UAMS-Web/uams-statamic-archive#2138): the honorific was two `title_options` checkboxes
        // (`emeritus` / `emerita`) before it became the single-choice `honorific`
        // radios slot. An entry stored before the move carries the old checkbox keys
        // and no `honorific`, so read them across here -- ahead of the checkbox loop
        // below, which discards keys the subfield no longer defines -- or a stored
        // honorific is silently dropped. `emeritus` wins if a stale payload carries
        // both. On the next save the migrated value persists and the stale keys fall
        // away.
        if (! \in_array($merged['honorific'] ?? null, ['emeritus', 'emerita'], true)) {
            $legacyTitleOptions = \is_array($merged['title_options'] ?? null)
                ? $merged['title_options']
                : [];

            if (($legacyTitleOptions['emeritus'] ?? null) === true) {
                $merged['honorific'] = 'emeritus';
            } elseif (($legacyTitleOptions['emerita'] ?? null) === true) {
                $merged['honorific'] = 'emerita';
            }
        }

        // Drop the legacy checkbox keys whether or not one was set. They name options
        // `title_options` no longer defines, and the checkbox loop below rebuilds the
        // map from the current config without removing extras -- so left in place they
        // would persist on every save. Removing them here lets an entry shed them for
        // good the next time it is saved.
        if (\is_array($merged['title_options'] ?? null)) {
            unset($merged['title_options']['emeritus'], $merged['title_options']['emerita']);
        }

        $emptyShared = RoleTitleValue::emptyValueSharedPayload();

        foreach (RoleTitleValue::checkboxSubfieldKeys() as $checkboxSubfieldKey) {
            $defaults = $emptyShared[$checkboxSubfieldKey] ?? null;

            if (! \is_array($defaults)) {
                $defaults = [];
            }

            if (! \is_array($merged[$checkboxSubfieldKey] ?? null)) {
                $merged[$checkboxSubfieldKey] = [];
            }

            foreach (\array_keys($defaults) as $optionKey) {
                $merged[$checkboxSubfieldKey][$optionKey] = (bool) (
                    $merged[$checkboxSubfieldKey][$optionKey] ?? $defaults[$optionKey]
                );
            }
        }

        unset($emptyShared);

        foreach (RoleTitleValue::topLevelRadiosSubfieldKeys() as $radiosKey) {
            $merged[$radiosKey] = $this->normalizeOptionalRadioStoredValue(
                $merged[$radiosKey] ?? null
            );
        }

        foreach (RoleTitleValue::groupSubfieldKeys() as $groupKey) {
            /** @var array<string, mixed> $rawGroup */
            $rawGroup = \is_array($merged[$groupKey] ?? null) ? $merged[$groupKey] : [];
            $merged[$groupKey] = $this->normalizeGroupSubfield(
                $groupKey,
                $rawGroup
            );
        }

        // A structured `value` is left as posted: every caller recomposes it or strips it
        if ($merged['mode'] === RoleTitleValue::MODE_UNSTRUCTURED) {
            $merged['value'] = \is_string($merged['value'] ?? null)
                ? \trim($merged['value'])
                : '';
            $merged = RoleTitleValue::normalizeUnstructuredOccupationBranchesForSchemaParity(
                $merged
            );
        }

        unset($base);

        return $merged;
    }

    /**
     * Normalize one `group` subfield using nested `fields` rows from config.
     *
     * @param  array<string, mixed>  $raw  Raw group payload from the editor
     * @return array<string, mixed> Normalized nested storage shape
     */
    private function normalizeGroupSubfield(
        string $groupKey,
        mixed $raw
    ): array {
        // Read the group row when present
        $groupDef = RoleTitleValue::topLevelSubfieldRow($groupKey);

        // groupSubfieldKeys() lists only group rows, so the definition is never missing (RemoveEarlyReturn is equivalent)
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! \is_array($groupDef)) {
            return $raw;
        }

        // Read nested field rows when present
        $fields = $groupDef['fields'] ?? null;

        // Return an empty map when nested fields are missing
        if (! \is_array($fields)) {
            return [];
        }

        // Use the incoming payload for per-key normalization
        $merged = $raw;

        // Build each nested key from its field definition row
        $out = [];

        foreach ($fields as $nestedKey => $fieldDef) {
            // Normalize the nested config key to a string storage key
            // Nested keys are non-numeric strings, so the cast changes nothing (RemoveStringCast is equivalent)
            // @pest-mutate-ignore: RemoveStringCast
            $nestedKey = (string) $nestedKey;

            // Skip invalid nested rows
            if (! \is_array($fieldDef)) {
                continue;
            }

            // Read the Statamic-style field type for this nested row
            // A row with no type matches none of the types below, whatever the fallback (EmptyStringToNotEmpty is equivalent)
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $nestedType = $fieldDef['type'] ?? '';

            // Normalize nested text fields to string defaults
            if ($nestedType === 'text') {
                $nestedDefault = $fieldDef['default'] ?? '';
                $out[$nestedKey] = isset($merged[$nestedKey]) && \is_string($merged[$nestedKey])
                    ? $merged[$nestedKey]
                    : (\is_scalar($nestedDefault) ? (string) $nestedDefault : '');

                continue;
            }

            // Normalize a nested entries link to one id or null (UAMS-Web/uams-statamic-archive#2146)
            if ($nestedType === 'entries') {
                $out[$nestedKey] = RoleTitleScopeUnit::normalizeId(
                    $merged[$nestedKey] ?? null
                );

                continue;
            }

            // Skip non-radios nested fields
            if ($nestedType !== 'radios') {
                continue;
            }

            // Read ordered keys and optional-none behavior for this nested radios field
            $allowedKeys = RoleTitleValue::orderedOptionKeysForSubfield(
                $groupKey,
                $nestedKey
            );
            $allowOptionalNone = ($fieldDef['allow_optional_none'] ?? false) === true;
            $firstKey = RoleTitleValue::firstOrderedOptionKeyForNestedField(
                $groupKey,
                $nestedKey
            );

            // Map sentinel / empty to null for optional-none radios
            if ($allowOptionalNone) {
                $out[$nestedKey] = $this->normalizeOptionalRadioStoredValue(
                    $merged[$nestedKey] ?? null
                );
            } else {
                $out[$nestedKey] = $this->normalizeRequiredRadioStoredKey(
                    $merged[$nestedKey] ?? null,
                    $allowedKeys,
                    $firstKey
                );
            }
        }

        // A set link supersedes the siblings it replaces, so they cannot disagree
        foreach ($fields as $nestedKey => $fieldDef) {
            if (! \is_array($fieldDef)) {
                continue;
            }

            // A row with no type is not an `entries` row, whatever the fallback (EmptyStringToNotEmpty is equivalent)
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if (($fieldDef['type'] ?? '') !== 'entries') {
                continue;
            }

            // Nested keys are non-numeric strings, so the cast changes nothing (RemoveStringCast is equivalent)
            // @pest-mutate-ignore: RemoveStringCast
            if (($out[(string) $nestedKey] ?? null) === null) {
                continue;
            }

            // Clear each replaced sibling to its own empty shape: text to '', radios to null
            foreach ((array) ($fieldDef['replaces_nested_field'] ?? []) as $replaces) {
                if (! \is_string($replaces)) {
                    continue;
                }

                if (! \array_key_exists($replaces, $out)) {
                    continue;
                }

                // `$out` holds only typed array rows, so neither fallback is read (EmptyStringToNotEmpty is equivalent)
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                $replacedType = \is_array($fields[$replaces] ?? null) ? ($fields[$replaces]['type'] ?? '') : '';

                $out[$replaces] = $replacedType === 'text' ? '' : null;
            }
        }

        return $out;
    }

    /**
     * Normalize a required nested radios value to a valid option key.
     *
     * @param  list<string>  $allowedKeys  Ordered keys from config
     * @return string A key in `$allowedKeys` (falls back to `$firstKey`)
     */
    private function normalizeRequiredRadioStoredKey(
        mixed $value,
        array $allowedKeys,
        string $firstKey
    ): string {
        // Check if the raw value is not a string
        if (! \is_string($value)) {
            return $firstKey;
        }

        // Trim the stored radios value
        $trimmed = \trim($value);

        // Fall back to the first key when empty or unknown (an empty string is never an option key)
        // The option keys are non-numeric strings, so loose and strict comparison agree (TrueToFalse is equivalent)
        // @pest-mutate-ignore: TrueToFalse
        if (! \in_array($trimmed, $allowedKeys, true)) {
            return $firstKey;
        }

        return $trimmed;
    }

    /**
     * Normalize optional radios subfields, mapping sentinel none to null.
     *
     * @param  mixed  $value  Raw subfield value.
     * @return string|null Non-empty trimmed string, or null when unset.
     */
    private function normalizeOptionalRadioStoredValue(
        mixed $value
    ): ?string {
        // Check if the value is not a string (null included)
        if (! \is_string($value)) {
            return null;
        }

        // Trim the value string
        $t = \trim($value);

        // Check if the trimmed value is empty or the optional selection none sentinel
        if (
            $t === ''
            || $t === RoleTitleValue::optionalSelectionNone()
        ) {
            return null;
        }

        return $t;
    }

    /**
     * Remove subfields that do not belong to the active configuration.
     *
     * @param  array<string, mixed>  $normalized  Post-merge payload before configuration-specific stripping
     * @return array<string, mixed> Payload with inactive fields cleared via reset maps
     */
    private function stripKeysForConfiguration(
        array $normalized
    ): array {
        // An unstructured payload is cleared here too, and `synchronizeStoredBranchesByMode()`
        // then drops every structured key, so it needs no early return

        // Read active mode, defaulting to the definition default handle
        $configuration = $normalized['configuration'] ?? RoleTitleValue::defaultConfigurationHandle();

        // Keep the normalized payload when no active configuration applies
        // (`normalizeMergedPayload()` never stores an empty configuration string)
        if (! \is_string($configuration)) {
            return $normalized;
        }

        // Keep the normalized payload
        $out = $normalized;

        // ---
        // Apply configuration-specific reset maps so inactive fields stay cleared
        // ---

        // Merge inactive-mode field clears (including nested `title_options`)
        /** @var array<string, mixed> $out */
        $out = \array_replace_recursive(
            $out,
            RoleTitleValue::inactiveSubfieldClearsForConfiguration($configuration)
        );

        // Return the normalized payload
        return $out;
    }

    /**
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function synchronizeStoredBranchesByMode(
        array $out
    ): array {
        if (($out['mode'] ?? null) === RoleTitleValue::MODE_UNSTRUCTURED) {
            return $this->stripStructuredSubfieldsWhenUnstructured($out);
        }

        return $this->stripUnstructuredSubfieldsWhenStructured($out);
    }

    /**
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function stripStructuredSubfieldsWhenUnstructured(
        array $out
    ): array {
        foreach (\array_keys(RoleTitleValue::emptyValueSharedPayload()) as $k) {
            if (
                in_array($k, ['value', 'occupation', 'occupational_field'], true)
            ) {
                continue;
            }

            unset($out[$k]);
        }

        unset($out['configuration']);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function stripUnstructuredSubfieldsWhenStructured(
        array $out
    ): array {
        unset($out['occupation'], $out['occupational_field']);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function enabledTitleStructureKeys(
        array $config
    ): array {
        $raw = $config['enabled_title_structures'] ?? null;

        if (\is_array($raw) && $raw !== []) {
            $ordered = [];

            foreach (RoleTitleValue::titleStructureHandles() as $key) {
                if (\in_array($key, $raw, true)) {
                    $ordered[] = $key;
                }
            }

            if ($ordered !== []) {
                return $ordered;
            }
        }

        return [RoleTitleValue::MODE_STRUCTURED];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveDefaultTitleStructureKey(
        array $config
    ): string {
        $key = $config['default_title_structure'] ?? RoleTitleValue::MODE_STRUCTURED;
        $key = \is_string($key) ? $key : RoleTitleValue::MODE_STRUCTURED;

        $enabled = $this->enabledTitleStructureKeys($config);

        // The key and the enabled structures are non-numeric strings, so loose and strict comparison agree (TrueToFalse is equivalent)
        // @pest-mutate-ignore: TrueToFalse
        if (\in_array($key, $enabled, true)) {
            return $key;
        }

        return $enabled[0] ?? RoleTitleValue::MODE_STRUCTURED;
    }

    /**
     * @param  list<string>  $enabled
     * @param  array<string, mixed>  $config
     */
    private function resolveStoredMode(
        ?string $mode,
        array $enabled,
        array $config
    ): string {
        // The enabled structures are canonical handles, and neither null nor '' is one
        // The mode and the enabled structures are non-numeric strings, so loose and strict comparison agree (TrueToFalse is equivalent)
        // @pest-mutate-ignore: TrueToFalse
        if (\in_array($mode, $enabled, true)) {
            return $mode;
        }

        $def = $config['default_title_structure'] ?? null;

        // A default that is not a string is never an enabled structure
        // The default and the enabled structures are non-numeric strings, so loose and strict comparison agree (TrueToFalse is equivalent)
        // @pest-mutate-ignore: TrueToFalse
        if (\is_string($def) && \in_array($def, $enabled, true)) {
            return $def;
        }

        return $enabled[0] ?? RoleTitleValue::MODE_STRUCTURED;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payloadHasCpKeys(array $data): bool
    {
        return array_any(RoleTitleUnstructuredCpBridge::cpKeys(), fn (string $k): bool => \array_key_exists($k, $data));
    }
}
