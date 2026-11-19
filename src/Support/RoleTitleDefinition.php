<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

/**
 * Loads v3 `config('role_title')` (unified `subfields`) and derives ordered keys,
 * defaults, inactive clears, and preload maps from `available_in` rules.
 */
final class RoleTitleDefinition
{
    /**
     * Cached singleton built from the application config array.
     */
    private static ?self $instance = null;

    /**
     * @param  array<string, mixed>  $raw  Normalized `role_title` config array
     */
    private function __construct(
        /**
         * Raw `config('role_title')` array.
         */
        private readonly array $raw
    ) {}

    /**
     * Return the cached definition, constructing it from config when needed.
     *
     * @return self Cached definition for `config('role_title')`.
     */
    public static function instance(): self
    {
        // Check if the singleton is already initialized
        if (! self::$instance instanceof RoleTitleDefinition) {
            // Resolve the config array from the application container
            $raw = \config('role_title');

            // Check if the config entry is a usable array
            if (! \is_array($raw)) {
                // Fail fast when configuration is missing or invalid
                throw new \RuntimeException(
                    'Configuration `role_title` must be an array.'
                );
            }

            // Build and cache the definition instance
            /** @var array<string, mixed> $raw */
            self::$instance = new self($raw);
        }

        // Return the cached singleton
        return self::$instance;
    }

    /**
     * Drop the cached singleton (tests that override `config()`).
     */
    public static function forgetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Optional-radio “none” sentinel stored by the CP before normalization.
     *
     * @return string Sentinel string normalized to null in `RoleTitle`.
     *
     * @throws \RuntimeException When `sentinel.optional_radio_none` is missing
     *                           or not a non-empty string.
     */
    public function optionalRadioNone(): string
    {
        // Read the sentinel section from configuration
        $sentinel = $this->raw['sentinel'] ?? null;

        // Read the sentinel string when the section is an array
        $v = \is_array($sentinel) ? ($sentinel['optional_radio_none'] ?? null) : null;

        // Check if the sentinel is a non-empty string
        if (\is_string($v) && $v !== '') {
            // Return the configured sentinel
            return $v;
        }

        // Fail fast when the sentinel is not configured
        throw new \RuntimeException(
            'Configuration `role_title.sentinel.optional_radio_none` must be a non-empty string.'
        );
    }

    /**
     * Stored `configuration` handles in canonical UI / validation order.
     *
     * @return list<string>
     */
    public function configurationHandles(): array
    {
        // Read the configurations section from configuration
        $configurations = $this->raw['configurations'] ?? null;

        // Read ordered handle list when the section is an array
        $handles = \is_array($configurations) ? ($configurations['handles'] ?? null) : null;

        // Return an empty list when handles are missing or not a list
        if (! \is_array($handles) || ! \array_is_list($handles)) {
            return [];
        }

        // Filter to non-empty string handles in file order
        $out = [];

        foreach ($handles as $h) {
            // Keep string handles only
            if (\is_string($h) && $h !== '') {
                $out[] = $h;
            }
        }

        // Return the ordered handle list
        return $out;
    }

    /**
     * Default stored `configuration` handle from `configurations.default`.
     *
     * @return string|null Handle present in `configurationHandles()`, the first
     *                     declared handle when the list is non-empty, the
     *                     configured string when handles are empty but a
     *                     non-empty default was set, or null when no default
     *                     applies.
     */
    public function defaultConfigurationHandle(): ?string
    {
        // Read canonical handles from configuration
        $handles = $this->configurationHandles();

        // Read the configurations section from configuration
        $configurations = $this->raw['configurations'] ?? null;

        // Read the configured default handle when the section is an array
        $raw = \is_array($configurations) ? ($configurations['default'] ?? null) : null;
        $configured = \is_string($raw) && $raw !== '' ? $raw : null;

        // Use the configured default when it is a known handle
        if (
            $configured !== null
            && $handles !== []
            && \in_array($configured, $handles, true)
        ) {
            return $configured;
        }

        // Fall back to the first declared handle when the list is non-empty
        if ($handles !== []) {
            return $handles[0];
        }

        return $configured;
    }

    /**
     * Ordered `schema` scenario rows (`conditions` + `output`) for derived outputs.
     *
     * @return list<array<string, mixed>>
     */
    public function schemaScenarioRows(): array
    {
        // Read the schema list when present
        $schema = $this->raw['schema'] ?? null;

        // Return an empty list when the schema is missing or invalid
        if (! \is_array($schema)) {
            return [];
        }

        // Collect only associative scenario rows in file order
        $rows = [];

        foreach ($schema as $row) {
            // Append each valid scenario array
            if (\is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Raw `subfields.{key}` row when present.
     *
     * @return array<string, mixed>|null
     */
    public function topLevelSubfieldRow(
        string $subfieldKey
    ): ?array {
        // Read the subfields section from configuration
        $subfields = $this->raw['subfields'] ?? null;

        // Read the subfield row when the section is an array
        $row = \is_array($subfields) ? ($subfields[$subfieldKey] ?? null) : null;

        // Return the row when it is an array
        /** @var array<string, mixed>|null */
        return \is_array($row) ? $row : null;
    }

    /**
     * Whether a top-level `radios` row opts into `RoleTitlePerConfigurationOptionRule`
     * (`validation.per_configuration_options` on that row in `role_title.php`).
     */
    public function radiosSubfieldUsesPerConfigurationOptionValidation(
        string $subfieldKey
    ): bool {
        // Read the top-level subfield row when present
        $row = $this->topLevelSubfieldRow($subfieldKey);

        // Reject missing rows or non-radios types
        if ($row === null || self::fieldType($row) !== 'radios') {
            return false;
        }

        // Read optional validation flags for this radios row
        $validation = $row['validation'] ?? null;

        // Treat non-array validation sections as disabled
        if (! \is_array($validation)) {
            return false;
        }

        // Enable per-configuration membership checks when explicitly configured
        return ($validation['per_configuration_options'] ?? false) === true;
    }

    /**
     * Top-level `subfields` keys whose `type` is `checkboxes`.
     *
     * @return list<string>
     */
    public function checkboxSubfieldKeys(): array
    {
        // Read subfield rows when present
        $subfields = $this->raw['subfields'] ?? null;

        // Return an empty list when subfields are missing
        if (! \is_array($subfields)) {
            return [];
        }

        // Collect storage keys for checkbox subfields
        $out = [];

        foreach ($subfields as $key => $def) {
            // Skip invalid subfield rows
            if (! \is_array($def)) {
                continue;
            }

            // Keep rows that store checkbox maps
            if (self::fieldType($def) === 'checkboxes') {
                $out[] = $key;
            }
        }

        return $out;
    }

    /**
     * Top-level `subfields` keys whose `type` is `radios`.
     *
     * @return list<string>
     */
    public function topLevelRadiosSubfieldKeys(): array
    {
        // Read subfield rows when present
        $subfields = $this->raw['subfields'] ?? null;

        // Return an empty list when subfields are missing
        if (! \is_array($subfields)) {
            return [];
        }

        // Collect storage keys for top-level radios
        $out = [];

        foreach ($subfields as $key => $def) {
            // Skip invalid subfield rows
            if (! \is_array($def)) {
                continue;
            }

            // Keep rows that store radios selections
            if (self::fieldType($def) === 'radios') {
                $out[] = $key;
            }
        }

        return $out;
    }

    /**
     * Top-level `subfields` keys whose `type` is `group`.
     *
     * @return list<string>
     */
    public function groupSubfieldKeys(): array
    {
        // Read subfield rows when present
        $subfields = $this->raw['subfields'] ?? null;

        // Return an empty list when subfields are missing
        if (! \is_array($subfields)) {
            return [];
        }

        // Collect storage keys for group subfields
        $out = [];

        foreach ($subfields as $key => $def) {
            // Skip invalid subfield rows
            if (! \is_array($def)) {
                continue;
            }

            // Keep rows that store nested group maps
            if (self::fieldType($def) === 'group') {
                $out[] = $key;
            }
        }

        return $out;
    }

    /**
     * Option storage keys for a stored `configuration` that compose in a given
     * segment, in per-option `options.{key}.position` order within that
     * segment. Applies to any `subfields` row with an `options` map (e.g.
     * `checkboxes` or `radios`). Missing or unknown `position` is treated as
     * `inline` (compose in config order with other `inline` keys).
     *
     * @param  string  $subfieldKey  Top-level `subfields` key
     * @param  string  $configuration  Stored configuration handle
     * @param  string  $segment  `prefix`, `suffix`, or `inline`
     * @return list<string>
     *
     * @throws \InvalidArgumentException When `$segment` is not a known compose
     *                                   segment.
     */
    public function subfieldOptionKeysForComposeSegment(
        string $subfieldKey,
        string $configuration,
        string $segment
    ): array {
        // Reject invalid compose segment names
        if (
            ! in_array($segment, ['prefix', 'suffix', 'inline'], true)
        ) {
            throw new \InvalidArgumentException(
                'Compose segment must be "prefix", "suffix", or "inline".'
            );
        }

        // Collect keys whose normalized `position` matches the requested segment
        $out = [];

        // Walk options for this configuration in compose order
        foreach (
            $this->optionKeysForSubfieldAndConfiguration(
                $subfieldKey,
                $configuration
            ) as $key
        ) {
            // Read prefix / suffix / inline placement for this option key
            $pos = $this->optionComposePositionForSubfield(
                $subfieldKey,
                $key
            );

            // Append keys that belong to the requested segment
            if ($pos === $segment) {
                $out[] = $key;
            }
        }

        // Return the ordered keys for this segment
        return $out;
    }

    /**
     * Ordered option keys under `subfields.{subfieldKey}.options` that apply to
     * a stored `configuration` (per-option `available_in`).
     *
     * @param  string  $subfieldKey  Top-level `subfields` key with an `options` map
     * @param  string  $configuration  Stored configuration handle
     * @return list<string>
     */
    public function optionKeysForSubfieldAndConfiguration(
        string $subfieldKey,
        string $configuration
    ): array {
        // Resolve global key order for this subfield’s options map
        $orderedKeys = $this->orderedOptionKeysForSubfield($subfieldKey);

        // Collect keys that apply to the requested configuration mode
        $out = [];

        // Walk option keys in config order
        foreach ($orderedKeys as $key) {
            // Check if this option row applies to the active configuration
            if (
                $this->subfieldOptionAppliesToConfiguration(
                    $subfieldKey,
                    $key,
                    $configuration
                )
            ) {
                $out[] = $key;
            }
        }

        // Return the filtered ordered keys
        return $out;
    }

    /**
     * Map each configured mode handle to ordered option keys for a subfield.
     *
     * @param  string  $subfieldKey  Top-level `subfields` key with an `options` map
     * @return array<string, list<string>> Configuration handle to ordered keys
     */
    public function mapOptionKeysByConfigurationHandles(
        string $subfieldKey
    ): array {
        // Build a map of configuration handle to ordered keys
        $out = [];

        // Walk each configured mode handle
        foreach ($this->configurationHandles() as $handle) {
            // Collect ordered keys allowed for this mode and subfield
            $out[$handle] = $this->optionKeysForSubfieldAndConfiguration(
                $subfieldKey,
                $handle
            );
        }

        // Return the per-mode key map
        return $out;
    }

    /**
     * Default checkbox map from a `checkboxes` field definition row.
     *
     * @param  array<string, mixed>  $fieldDef
     * @return array<string, bool>
     */
    private function checkboxDefaultMapFromFieldDefinition(
        array $fieldDef
    ): array {
        // Read an explicit default map when the config provides one
        $explicit = $fieldDef['default'] ?? null;

        // Return configured defaults when the map is a non-empty array
        if (\is_array($explicit) && $explicit !== []) {
            /** @var array<string, bool> $explicit */
            return $explicit;
        }

        // Build every union key as off for empty templates
        $out = [];

        // Walk option keys in config order for this field row
        foreach (
            $this->orderedOptionKeysFromFieldDefinition($fieldDef) as $key
        ) {
            // Default each checkbox to off when no explicit map is set
            $out[$key] = false;
        }

        // Return the derived checkbox default map
        return $out;
    }

    /**
     * First ordered radios option key, or a safe literal when options are empty.
     *
     * @param  array<string, mixed>  $fieldDef
     */
    private function firstStoredKeyForRadiosFieldDefinition(
        array $fieldDef
    ): string {
        // Read ordered keys for this radios field row
        $keys = $this->orderedOptionKeysFromFieldDefinition($fieldDef);

        // Check if at least one option key exists
        if ($keys === []) {
            // Return empty when no radios options are configured
            return '';
        }

        // Return the first configured option key
        return $keys[0];
    }

    /**
     * Stored default for a nested `radios` field (validates against option keys).
     *
     * @param  array<string, mixed>  $fieldDef
     */
    private function defaultStoredValueForRadiosFieldDefinition(
        array $fieldDef
    ): ?string {
        // Read whether null is a valid stored value
        $allowOptionalNone = ($fieldDef['allow_optional_none'] ?? false) === true;

        // Read ordered keys for validation
        $ordered = $this->orderedOptionKeysFromFieldDefinition($fieldDef);

        // Read the configured default when present
        $raw = $fieldDef['default'] ?? null;

        // Check if the default is empty
        if ($raw === null || $raw === '') {
            // Return null when optional-none is allowed
            if ($allowOptionalNone) {
                return null;
            }

            // Otherwise require a concrete first option key
            return $this->firstStoredKeyForRadiosFieldDefinition($fieldDef);
        }

        // Coerce non-strings to a safe radios default
        if (! \is_string($raw)) {
            // Return null when optional-none is allowed
            if ($allowOptionalNone) {
                return null;
            }

            // Otherwise require a concrete first option key
            return $this->firstStoredKeyForRadiosFieldDefinition($fieldDef);
        }

        // Check if the default matches a configured option key
        if ($ordered !== [] && \in_array($raw, $ordered, true)) {
            return $raw;
        }

        // Return null when optional-none is allowed for unknown defaults
        if ($allowOptionalNone) {
            return null;
        }

        // Otherwise coerce to the first configured option key
        return $this->firstStoredKeyForRadiosFieldDefinition($fieldDef);
    }

    /**
     * Stored default for a nested `text` field.
     *
     * @param  array<string, mixed>  $fieldDef
     */
    private function defaultStoredValueForTextFieldDefinition(
        array $fieldDef
    ): string {
        // Read the configured default when present
        $raw = $fieldDef['default'] ?? '';

        // Return the default when it is a string
        return \is_string($raw) ? $raw : '';
    }

    /**
     * Stored default for one field definition row (`fields` entry or top-level).
     *
     * `composed_line` is the composed one-line title (typically null in empty
     * templates until `RoleTitle::process()` runs).
     *
     * @param  array<string, mixed>  $fieldDef
     */
    private function defaultStoredValueForFieldDefinition(
        array $fieldDef
    ): mixed {
        // Dispatch by Statamic field type for nested and top-level rows
        return match (self::fieldType($fieldDef)) {
            'checkboxes' => $this->checkboxDefaultMapFromFieldDefinition(
                $fieldDef
            ),
            'composed_line' => $fieldDef['default'] ?? null,
            'radios' => $this->defaultStoredValueForRadiosFieldDefinition(
                $fieldDef
            ),
            'text' => $this->defaultStoredValueForTextFieldDefinition(
                $fieldDef
            ),
            'group' => $this->groupDefaultFromDefinition($fieldDef),
            default => $fieldDef['default'] ?? null,
        };
    }

    /**
     * Stored defaults for a `group` field from each nested `fields` entry.
     *
     * @param  array<string, mixed>  $groupDef
     * @return array<string, mixed>
     */
    private function groupDefaultFromDefinition(
        array $groupDef
    ): array {
        // Read nested field rows when present
        $fields = $groupDef['fields'] ?? null;

        // Check if nested fields are missing or empty
        if (! \is_array($fields) || $fields === []) {
            // Return an empty stored object when the group has no nested fields
            return [];
        }

        // Initialize the merged default map for this group
        $out = [];

        // Walk each nested field in config insertion order
        foreach ($fields as $nestedKey => $nestedDef) {
            // Skip invalid nested rows
            if (! \is_array($nestedDef)) {
                continue;
            }

            /** @var array<string, mixed> $nestedDef */

            // Resolve the stored default for this nested field row
            $out[$nestedKey] = $this->defaultStoredValueForFieldDefinition(
                $nestedDef
            );
        }

        // Return the assembled group default payload
        /** @var array<string, mixed> $out */
        return $out;
    }

    /**
     * Shared empty-template subfields (excluding `configuration`).
     *
     * Built from `subfields` key order in `config/role_title.php` via
     * `subfieldDefault()` for each row (including `composed_line` for `value`).
     *
     * @return array<string, mixed>
     */
    public function emptyValueSharedPayload(): array
    {
        // Read top-level subfield rows when present
        $subfields = $this->raw['subfields'] ?? null;

        // Return an empty map when subfields are missing
        if (! \is_array($subfields)) {
            return [];
        }

        // Build one default per subfield key in config insertion order
        $out = [];

        foreach (\array_keys($subfields) as $subfieldKey) {
            $out[$subfieldKey] = $this->subfieldDefault($subfieldKey);
        }

        /** @var array<string, mixed> $out */
        return $out;
    }

    /**
     * Inactive-mode field clears for the active `configuration` (merged on save).
     *
     * For each `checkboxes` subfield: turn off options whose `available_in` (or
     * the whole subfield’s `available_in`) excludes the active mode. For other
     * subfield types (except `composed_line`), when the row’s `available_in`
     * excludes the mode: `group` resets to `subfieldDefault()`; scalars clear to
     * `null`.
     *
     * @param  string  $configuration  Active stored `configuration` handle
     * @return array<string, mixed> Nested overrides merged in `RoleTitle::process()`
     */
    public function inactiveSubfieldClearsForConfiguration(
        string $configuration
    ): array {
        // Initialize the merged clear map for this configuration mode
        $out = [];

        // Read top-level subfield rows when present
        $subfields = $this->raw['subfields'] ?? null;

        // Return an empty clear map when subfields are missing
        if (! \is_array($subfields)) {
            return $out;
        }

        // Turn off checkbox keys that do not apply to this configuration
        foreach ($subfields as $subfieldKey => $def) {
            // Skip invalid or non-checkbox subfield rows
            if (! \is_array($def)) {
                continue;
            }

            if (self::fieldType($def) !== 'checkboxes') {
                continue;
            }

            /** @var array<string, mixed> $def */

            // Check whether the whole subfield row applies to this mode
            $wholeSubfieldApplies = $this->subfieldDefinitionAppliesToMode(
                $def,
                $configuration
            );

            // Walk each checkbox storage key for this subfield
            foreach (
                $this->orderedOptionKeysForSubfield($subfieldKey) as $optionKey
            ) {
                // Turn off every key when the subfield row excludes this mode
                if (! $wholeSubfieldApplies) {
                    $out[$subfieldKey][$optionKey] = false;

                    continue;
                }

                // Check if this option row applies to the active configuration
                if (
                    ! $this->subfieldOptionAppliesToConfiguration(
                        $subfieldKey,
                        $optionKey,
                        $configuration
                    )
                ) {
                    // Turn off checkboxes that belong to other configurations
                    $out[$subfieldKey][$optionKey] = false;
                }
            }
        }

        // Clear or reset subfields whose row excludes this configuration
        foreach ($subfields as $subfieldKey => $def) {
            // Skip invalid subfield rows
            if (! \is_array($def)) {
                continue;
            }

            /** @var array<string, mixed> $def */

            // Read the Statamic-style field type for this row
            $type = self::fieldType($def);
            // Skip composed line (not stripped by mode) and checkboxes (handled above)
            if ($type === 'composed_line') {
                continue;
            }

            if ($type === 'checkboxes') {
                continue;
            }

            // Skip subfields that apply to every configuration (or this mode)
            if ($this->subfieldDefinitionAppliesToMode($def, $configuration)) {
                continue;
            }

            // Reset group storage to configured defaults when the group is inactive
            if ($type === 'group') {
                $out[$subfieldKey] = $this->subfieldDefault($subfieldKey);
            } else {
                // Clear scalar subfield storage (e.g. radios) for inactive modes
                $out[$subfieldKey] = null;
            }
        }

        // Return the assembled inactive-mode map
        /** @var array<string, mixed> $out */
        return $out;
    }

    /**
     * Stable preload key order for `option_label_maps` (and JS preload meta).
     *
     * One slot per top-level `radios` / `checkboxes` subfield (`preload_slot`
     * overrides the storage key), plus one slot per nested `radios` /
     * `checkboxes` under `group` (defaults to the nested field key, e.g.
     * `scope_joiner`). Other types are skipped. Order follows `subfields` in
     * config; within a group, order follows `fields`.
     *
     * @return list<string>
     */
    public function preloadSlotOrderFromSubfields(): array
    {
        // Read subfield definitions when present
        $subfields = $this->raw['subfields'] ?? null;

        // Return an empty list when subfields are absent
        if (! \is_array($subfields)) {
            return [];
        }

        // Collect preload slot identifiers in config order
        $out = [];

        foreach ($subfields as $subfieldKey => $def) {
            // Skip invalid subfield rows
            if (! \is_array($def)) {
                continue;
            }

            /** @var array<string, mixed> $row */
            $row = $def;

            // Append slots for option-backed field types only
            foreach (
                $this->preloadSlotIdentifiersForSubfieldRow(
                    $subfieldKey,
                    $row
                ) as $slot
            ) {
                $out[] = $slot;
            }
        }

        return $out;
    }

    /**
     * Preload slot names contributed by one top-level `subfields` row.
     *
     * @param  array<string, mixed>  $def  Top-level `subfields` row
     * @return list<string>
     */
    private function preloadSlotIdentifiersForSubfieldRow(
        string $subfieldKey,
        array $def
    ): array {
        $type = self::fieldType($def);

        // Emit one slot for top-level radios or checkboxes
        if ($type === 'radios' || $type === 'checkboxes') {
            // Use the declared preload slot when present, else the storage key
            $slot = $def['preload_slot'] ?? $subfieldKey;

            return [\is_string($slot) ? $slot : $subfieldKey];
        }

        // Emit one slot per nested radios/checkboxes field under a group
        if ($type === 'group') {
            $fields = $def['fields'] ?? null;

            if (! \is_array($fields)) {
                return [];
            }

            $slots = [];

            foreach ($fields as $nestedKey => $nestedDef) {
                // Skip invalid nested rows
                if (! \is_array($nestedDef)) {
                    continue;
                }

                $nestedType = self::fieldType($nestedDef);

                // Skip nested fields that do not expose option maps
                if ($nestedType !== 'radios' && $nestedType !== 'checkboxes') {
                    continue;
                }

                // Use the declared preload slot when present, else the field key
                $nestedSlot = $nestedDef['preload_slot'] ?? $nestedKey;

                $slots[] = \is_string($nestedSlot) ? $nestedSlot : (string) $nestedKey;
            }

            return $slots;
        }

        return [];
    }

    /**
     * Option keys per preload slot (derived from `subfields`).
     *
     * @return array<string, list<string>>
     */
    public function optionLabelMapKeyListsByPreloadKey(): array
    {
        // Initialize the preload key to key-list map
        $map = [];

        // Walk each preload slot in subfield-derived order
        foreach ($this->preloadSlotOrderFromSubfields() as $slot) {
            // Resolve ordered option keys for this preload slot name
            $map[$slot] = $this->orderedOptionKeysForPreloadSlot($slot);
        }

        // Return the assembled preload key map
        return $map;
    }

    /**
     * Resolve a CP preload slot to a top-level `subfields` key and optional
     * nested `group.fields` key.
     *
     * @return array{top: string, nested: ?string}|null
     */
    private function resolvePreloadSlotLocation(
        string $slot
    ): ?array {
        // Read top-level subfield rows when present
        $subfields = $this->raw['subfields'] ?? null;

        // Signal unknown slots when subfields are missing
        if (! \is_array($subfields)) {
            // Unreachable: both callers pass only slots preloadSlotOrderFromSubfields() read from this same subfields array
            // @pest-mutate-ignore: RemoveEarlyReturn
            return null;
        }

        foreach ($subfields as $subfieldKey => $def) {
            // Skip invalid subfield rows
            if (! \is_array($def)) {
                continue;
            }

            $type = self::fieldType($def);

            // Match a top-level radios/checkboxes row by declared slot name
            if ($type === 'radios' || $type === 'checkboxes') {
                $declared = $def['preload_slot'] ?? $subfieldKey;

                if ($declared === $slot) {
                    return [
                        'top' => $subfieldKey,
                        'nested' => null,
                    ];
                }

                continue;
            }

            // Search nested option fields when this row is a group
            if ($type !== 'group') {
                continue;
            }

            $fields = $def['fields'] ?? null;

            if (! \is_array($fields)) {
                continue;
            }

            foreach ($fields as $nestedKey => $nestedDef) {
                // Skip invalid nested rows
                if (! \is_array($nestedDef)) {
                    continue;
                }

                $nestedType = self::fieldType($nestedDef);

                if ($nestedType !== 'radios' && $nestedType !== 'checkboxes') {
                    continue;
                }

                $declared = $nestedDef['preload_slot'] ?? $nestedKey;

                if ($declared === $slot) {
                    return [
                        'top' => $subfieldKey,
                        'nested' => $nestedKey,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Raw option row under `subfields.{groupKey}.fields.{nestedKey}.options`.
     *
     * @return array<string, mixed>|null
     */
    private function nestedGroupFieldOptionRow(
        string $groupSubfieldKey,
        string $nestedFieldKey,
        string $optionKey
    ): ?array {
        // Read the subfields section from configuration
        $subfields = $this->raw['subfields'] ?? null;

        // Read the group subfield row when the section is an array
        $groupDef = \is_array($subfields) ? ($subfields[$groupSubfieldKey] ?? null) : null;

        // Return null when the group row is missing
        if (! \is_array($groupDef)) {
            // Equivalent: every read below is a null-safe ?? that ends in the same null
            // @pest-mutate-ignore: RemoveEarlyReturn
            return null;
        }

        // Read nested fields for this group
        $fields = $groupDef['fields'] ?? null;

        // Return null when nested fields are missing
        if (! \is_array($fields)) {
            // Equivalent: every read below is a null-safe ?? that ends in the same null
            // @pest-mutate-ignore: RemoveEarlyReturn
            return null;
        }

        // Read the nested field definition row
        $nested = $fields[$nestedFieldKey] ?? null;

        // Return null when the nested field row is missing
        if (! \is_array($nested)) {
            // Equivalent: every read below is a null-safe ?? that ends in the same null
            // @pest-mutate-ignore: RemoveEarlyReturn
            return null;
        }

        // Read the nested options map
        $opts = $nested['options'] ?? null;

        // Return null when nested options are missing
        if (! \is_array($opts)) {
            // Equivalent: the null-safe ?? read below ends in the same null
            // @pest-mutate-ignore: RemoveEarlyReturn
            return null;
        }

        // Read the specific nested option row
        $row = $opts[$optionKey] ?? null;

        // Return the nested option row when it is an array
        /** @var array<string, mixed>|null */
        return \is_array($row) ? $row : null;
    }

    /**
     * Whether a preload-slot option applies to a stored configuration handle.
     */
    private function preloadSlotOptionAppliesToConfiguration(
        string $slot,
        string $optionKey,
        string $handle
    ): bool {
        // Resolve the backing subfield location for this preload slot
        $loc = $this->resolvePreloadSlotLocation($slot);

        // Treat unknown slots as non-applicable
        if ($loc === null) {
            // Unreachable: the slot comes with option keys only when resolvePreloadSlotLocation() found it
            // @pest-mutate-ignore: FalseToTrue, RemoveEarlyReturn
            return false;
        }

        // Branch on top-level versus nested radios/checkboxes definitions
        if ($loc['nested'] === null) {
            // Read the top-level subfield definition row
            $def = $this->topLevelSubfieldRow($loc['top']);

            // Reject missing top-level definitions
            if ($def === null) {
                // Unreachable: the location was resolved from this array row
                // @pest-mutate-ignore: FalseToTrue, RemoveEarlyReturn
                return false;
            }

            // Require the subfield row and per-option applicability for this mode
            return $this->subfieldDefinitionAppliesToMode(
                $def,
                $handle
            ) && $this->subfieldOptionAppliesToConfiguration(
                $loc['top'],
                $optionKey,
                $handle
            );
        }

        // Read the parent group definition row
        $groupDef = $this->topLevelSubfieldRow($loc['top']);

        // Reject missing group rows
        if (! \is_array($groupDef)) {
            // Unreachable: the location was resolved from this array group row
            // @pest-mutate-ignore: FalseToTrue, RemoveEarlyReturn
            return false;
        }

        // Read nested fields for this group
        $fields = $groupDef['fields'] ?? null;

        // Reject missing nested field maps
        if (! \is_array($fields)) {
            // Unreachable: the location was resolved from this array fields map
            // @pest-mutate-ignore: FalseToTrue, RemoveEarlyReturn
            return false;
        }

        // Read the nested radios/checkboxes field definition
        $nestedDef = $fields[$loc['nested']] ?? null;

        // Reject missing nested field rows
        if (! \is_array($nestedDef)) {
            // Unreachable: the location was resolved from this array nested row
            // @pest-mutate-ignore: FalseToTrue, RemoveEarlyReturn
            return false;
        }

        /** @var array<string, mixed> $nestedDef */

        // Require group, nested field, and per-option availability for this mode
        if (
            ! $this->subfieldDefinitionAppliesToMode(
                $groupDef,
                $handle
            )
            || ! $this->subfieldDefinitionAppliesToMode(
                $nestedDef,
                $handle
            )
        ) {
            return false;
        }

        // Read the nested option row for per-option availability
        $row = $this->nestedGroupFieldOptionRow(
            $loc['top'],
            $loc['nested'],
            $optionKey
        );

        // Treat unknown nested option keys as not applicable
        if ($row === null) {
            return false;
        }

        // Read per-option availability for configuration modes
        $avail = $row['available_in'] ?? null;

        // Check if this nested option applies to the active configuration handle
        return $this->modeMatchesAvailableIn(
            $this->availableInWireList($avail),
            $handle
        );
    }

    /**
     * Wire `available_in` list for one preload-slot option (empty = all modes).
     *
     * @return list<string>|null Null when the option applies to no mode
     */
    private function effectiveAvailableInWireListForPreloadSlotOption(
        string $slot,
        string $optionKey
    ): ?array {
        // Read configured configuration handles in canonical order
        $handles = $this->configurationHandles();

        // Collect handles where this option is active for the preload slot
        $applies = [];

        foreach ($handles as $h) {
            // Check if this option applies to the configuration handle
            if (
                $this->preloadSlotOptionAppliesToConfiguration(
                    $slot,
                    $optionKey,
                    $h
                )
            ) {
                $applies[] = $h;
            }
        }

        // Signal options that never apply so callers can skip them
        if ($applies === []) {
            return null;
        }

        // Use an empty list on the wire when every configured mode is allowed
        if (\count($applies) === \count($handles)) {
            return [];
        }

        // Return the explicit allow list when only some modes apply
        return $applies;
    }

    /**
     * CP `option_label_maps` row metadata: option `key` plus `available_in`
     * (empty list = all configured modes). Labels are applied in
     * `RoleTitleValue::optionLabelMapsForPreload()`.
     *
     * @return array<string, list<array{key: string, available_in: list<string>}>>
     */
    public function optionLabelOptionMetadataRowsForPreload(): array
    {
        // Build metadata row lists keyed by preload slot name
        $out = [];

        // Walk each preload slot and its ordered option keys
        foreach ($this->optionLabelMapKeyListsByPreloadKey() as $slot => $keys) {
            // Collect `{ key, available_in }` rows for this slot
            $rows = [];

            foreach ($keys as $optionKey) {
                // Resolve the wire availability list for this option key
                $wireAvail = $this->effectiveAvailableInWireListForPreloadSlotOption(
                    $slot,
                    $optionKey
                );

                // Skip option keys that never apply to any configured mode
                if ($wireAvail === null) {
                    continue;
                }

                // Append one metadata row for this preload slot
                $rows[] = [
                    'key' => $optionKey,
                    'available_in' => $wireAvail,
                ];
            }

            // Store the ordered row list for this preload slot
            $out[$slot] = $rows;
        }

        return $out;
    }

    /**
     * Ordered option storage keys for a CP preload slot identifier.
     *
     * Finds the matching top-level or nested `radios` / `checkboxes` definition
     * by comparing `$slot` to `preload_slot` or the storage key.
     *
     * @param  string  $slot  Value from `preloadSlotOrderFromSubfields()`
     * @return list<string>
     */
    private function orderedOptionKeysForPreloadSlot(
        string $slot
    ): array {
        // Resolve the backing subfield location for this preload slot
        $loc = $this->resolvePreloadSlotLocation($slot);

        // Return an empty list when the slot does not map to a radios/checkboxes field
        if ($loc === null) {
            return [];
        }

        // Return ordered keys for the resolved top-level or nested field
        return $this->orderedOptionKeysForSubfield(
            $loc['top'],
            $loc['nested']
        );
    }

    /**
     * Raw row for one entry under `subfields.{subfieldKey}.options`.
     *
     * @return array<string, mixed>|null
     */
    private function subfieldOptionRow(
        string $subfieldKey,
        string $optionKey
    ): ?array {
        // Read the subfields section from configuration
        $subfields = $this->raw['subfields'] ?? null;

        // Read the subfield definition when the section is an array
        $def = \is_array($subfields) ? ($subfields[$subfieldKey] ?? null) : null;

        // Check if the subfield row is missing
        if (! \is_array($def)) {
            // Signal that the subfield is not configured
            // Equivalent: the null-safe ?? reads below end in the same null
            // @pest-mutate-ignore: RemoveEarlyReturn
            return null;
        }

        // Read the options map for this subfield
        $opts = $def['options'] ?? null;

        // Check if options are missing
        if (! \is_array($opts)) {
            // Signal that this subfield has no options table
            // Equivalent: the null-safe ?? read below ends in the same null
            // @pest-mutate-ignore: RemoveEarlyReturn
            return null;
        }

        // Read the specific option row
        $row = $opts[$optionKey] ?? null;

        // Return the option row when it is an array
        /** @var array<string, mixed>|null */
        return \is_array($row) ? $row : null;
    }

    /**
     * Whether a per-option `available_in` list allows the configuration `$handle`.
     */
    private function subfieldOptionAppliesToConfiguration(
        string $subfieldKey,
        string $optionKey,
        string $handle
    ): bool {
        // Read the option definition row when present
        $row = $this->subfieldOptionRow($subfieldKey, $optionKey);

        // Treat unknown option keys as not applicable
        if ($row === null) {
            return false;
        }

        // Read per-option availability for configuration modes
        $avail = $row['available_in'] ?? null;

        // Check if this option applies to the active configuration handle
        return $this->modeMatchesAvailableIn(
            $this->availableInWireList($avail),
            $handle
        );
    }

    /**
     * Normalized compose segment for an option row under `subfields.*.options`.
     *
     * Only `prefix` and `suffix` are reserved; any other value (including
     * missing `position`) maps to `inline`.
     *
     * @param  string  $subfieldKey  Top-level `subfields` key
     * @param  string  $optionKey  Key under `subfields.{key}.options`
     * @return string `prefix`, `suffix`, or `inline`
     */
    private function optionComposePositionForSubfield(
        string $subfieldKey,
        string $optionKey
    ): string {
        // Read the option row when present
        $row = $this->subfieldOptionRow($subfieldKey, $optionKey);

        // Treat unknown option keys as inline placement
        if ($row === null) {
            // Equivalent: the null-safe ?? read below ends in the same inline placement
            // @pest-mutate-ignore: RemoveEarlyReturn
            return 'inline';
        }

        // Read the compose position flag from config
        $position = $row['position'] ?? null;

        // Preserve explicit prefix and suffix segments
        if ($position === 'prefix' || $position === 'suffix') {
            return $position;
        }

        // Map a missing, empty or any other position to inline placement
        return 'inline';
    }

    /**
     * Ordered storage keys for a Statamic field that uses an `options` map:
     * top-level `subfields.{key}`, or a nested field under
     * `subfields.{groupKey}.fields.{fieldKey}` when the parent is `type` =
     * `group`. Supports `radios`, `checkboxes`, and nested radios/checkboxes
     * under a group.
     *
     * @param  string  $subfieldKey  Top-level `subfields` key
     * @param  string|null  $fieldWithinGroup  Nested field name when resolving
     *                                         `group.fields.{name}` option fields
     * @return list<string>
     */
    public function orderedOptionKeysForSubfield(
        string $subfieldKey,
        ?string $fieldWithinGroup = null
    ): array {
        // Read the subfields section from configuration
        $subfields = $this->raw['subfields'] ?? null;

        // Read the top-level subfield definition when the section is an array
        $def = \is_array($subfields) ? ($subfields[$subfieldKey] ?? null) : null;

        // Check if top-level subfield is missing
        if (! \is_array($def)) {
            // Return an empty list when the subfield row is absent
            return [];
        }

        /** @var array<string, mixed> $def */

        // Check if resolving a nested option field inside a group subfield
        if ($fieldWithinGroup !== null) {
            // Check if the parent subfield is a group
            if (self::fieldType($def) !== 'group') {
                // Return an empty list when nested path is invalid
                return [];
            }

            // Read nested fields map
            $fields = $def['fields'] ?? null;

            // Check if nested fields are missing
            if (! \is_array($fields)) {
                // Return an empty list when the group has no fields
                return [];
            }

            // Read nested field row
            $nested = $fields[$fieldWithinGroup] ?? null;

            // Check if nested field row is missing
            if (! \is_array($nested)) {
                // Return an empty list when the nested field is absent
                return [];
            }

            /** @var array<string, mixed> $nested */

            // Return ordered keys from nested field definition
            return $this->orderedOptionKeysFromFieldDefinition($nested);
        }

        // Return ordered keys from top-level field definition
        return $this->orderedOptionKeysFromFieldDefinition($def);
    }

    /**
     * First ordered option key for a nested field under a `group` subfield.
     *
     * @param  string  $groupSubfieldKey  Top-level `subfields` key for the group
     * @param  string  $nestedFieldKey  Key under `group.fields`
     * @return string First option storage key, or empty when none are configured
     */
    public function firstOrderedOptionKeyForNestedField(
        string $groupSubfieldKey,
        string $nestedFieldKey
    ): string {
        // Read ordered keys for the nested radios/checkboxes field
        $keys = $this->orderedOptionKeysForSubfield(
            $groupSubfieldKey,
            $nestedFieldKey
        );

        // Return the first key when the nested field defines options
        return $keys[0] ?? '';
    }

    /**
     * Nested-field option keys whose row declares `requires_article` (UAMS-Web/uams-statamic-archive#2546).
     *
     * For `scope.scope_joiner` this is `in`, which introduces only units: a typed
     * `in` scope must choose the article, since the corpus has no article-less
     * `in` to compose. A linked scope always carries the article already.
     *
     * A row that omits the flag requires nothing, so a joiner added later composes
     * with or without the article as its author chooses.
     *
     * @param  string  $groupSubfieldKey  Top-level `subfields` key for the group
     * @param  string  $nestedFieldKey  Key under `group.fields`
     * @return list<string> Option storage keys declaring `requires_article`, in config order
     */
    public function articleRequiringOptionKeysForNestedField(
        string $groupSubfieldKey,
        string $nestedFieldKey
    ): array {
        // Collect the declaring keys in configured order
        $keys = [];

        // Walk the nested field's options in config order
        foreach (
            $this->orderedOptionKeysForSubfield(
                $groupSubfieldKey,
                $nestedFieldKey
            ) as $optionKey
        ) {
            // Read the raw option row for its declaration
            $row = $this->nestedGroupFieldOptionRow(
                $groupSubfieldKey,
                $nestedFieldKey,
                $optionKey
            );

            // Keep the key only when the row declares it requires the article
            if (\is_array($row) && ($row['requires_article'] ?? false) === true) {
                // Append the declaring key
                $keys[] = $optionKey;
            }
        }

        return $keys;
    }

    /**
     * Option keys in config order for a field definition row that stores choices
     * under `options` (`radios` or `checkboxes`).
     *
     * @param  array<string, mixed>  $fieldDef
     * @return list<string>
     */
    private function orderedOptionKeysFromFieldDefinition(
        array $fieldDef
    ): array {
        // Check if the field defines an options map for radios or checkboxes
        $type = self::fieldType($fieldDef);

        if ($type !== 'radios' && $type !== 'checkboxes') {
            // Return an empty list when option storage is not configured
            return [];
        }

        // Read the options map
        $opts = $fieldDef['options'] ?? null;

        // Check if options are missing
        if (! \is_array($opts)) {
            // Return an empty list when option keys are absent
            return [];
        }

        // Preserve insertion order from the config file
        return \array_keys($opts);
    }

    /**
     * Default stored value for a top-level `subfields` row.
     *
     * Uses `defaultStoredValueForFieldDefinition()` so behavior matches nested
     * `fields` rows: `checkboxes`, `radios`, `text`, and `group` resolve the
     * same way (required radios with null `default` use the first option key).
     * `checkboxes` and `group` rows always resolve to arrays. When the subfield row is missing, returns `null` (v3 config is
     * expected to define every stored subfield row).
     *
     * @param  string  $subfieldKey  Key under `subfields`
     * @return mixed `null` for optional radios, checkbox map, group array, or
     *               other
     */
    private function subfieldDefault(
        string $subfieldKey
    ): mixed {
        // Read the subfields section from configuration
        $subfields = $this->raw['subfields'] ?? null;

        // Read the subfield definition when the section is an array
        $def = \is_array($subfields) ? ($subfields[$subfieldKey] ?? null) : null;

        // Return null when the subfield row is absent (invalid / partial config)
        if (! \is_array($def)) {
            return null;
        }

        /** @var array<string, mixed> $def */

        // Resolve defaults with the same rules as nested blueprint field rows
        return $this->defaultStoredValueForFieldDefinition($def);
    }

    /**
     * Translated configuration picker labels keyed by stored `configuration` handle.
     *
     * @return array<string, string>
     */
    public function configurationLabelsForCpPreload(): array
    {
        // Initialize the handle to label map
        $out = [];

        // Build one label per canonical configuration handle
        foreach ($this->configurationHandles() as $handle) {
            // Resolve the Statamic translation for this configuration mode
            $out[$handle] = __(
                'aether-people::fieldtypes.role_title.configuration.'.$handle
            );
        }

        // Return the assembled label map
        return $out;
    }

    /**
     * Top-level subfield keys whose stored values are plain objects the CP merges
     * shallowly with posted data (`checkboxes` maps and `group` maps).
     *
     * @return list<string>
     */
    public function structuredTopLevelKeysForCpValueMerge(): array
    {
        // Read top-level subfield rows when present
        $subfields = $this->raw['subfields'] ?? null;

        // Return an empty list when subfields are missing
        if (! \is_array($subfields)) {
            return [];
        }

        // Collect storage keys for structured object payloads
        $keys = [];

        foreach ($subfields as $subfieldKey => $def) {
            // Skip invalid subfield rows
            if (! \is_array($def)) {
                continue;
            }

            // Read the subfield storage type
            $type = self::fieldType($def);

            // Treat checkbox maps and group maps as merged objects in the CP
            if ($type === 'checkboxes' || $type === 'group') {
                $keys[] = $subfieldKey;
            }
        }

        $keys[] = 'occupation';
        $keys[] = 'occupational_field';

        return $keys;
    }

    /**
     * Nested CP editor rows for one `group` subfield (`radios` / `text` only).
     *
     * @param  array<string, mixed>  $groupDef  `subfields.{groupKey}` row
     * @return list<array<string, mixed>>
     */
    private function nestedCpEditorRowsForGroup(
        string $groupKey,
        array $groupDef
    ): array {
        // Read nested fields for this group
        $fields = $groupDef['fields'] ?? null;

        // Return an empty list when nested fields are missing
        if (! \is_array($fields)) {
            return [];
        }

        // Initialize ordered nested editor rows
        $out = [];

        // Walk nested fields in config insertion order
        foreach ($fields as $nestedKey => $fieldDef) {
            // Skip invalid nested rows
            if (! \is_array($fieldDef)) {
                continue;
            }

            // Read the nested field storage type
            $nestedType = self::fieldType($fieldDef);

            // Render radios, text inputs, and entries links in the CP only
            if (! in_array($nestedType, ['radios', 'text', 'entries'], true)) {
                continue;
            }

            // Build the dotted storage path for this nested field
            $storagePath = sprintf('%s.%s', $groupKey, $nestedKey);

            // Describe one nested editor row
            $row = [
                'type' => $nestedType,
                'nested_key' => $nestedKey,
                'storage_path' => $storagePath,
            ];

            // Surface radios-specific wiring for the Vue shell
            if ($nestedType === 'radios') {
                // Read optional-none handling for optional radio groups
                $row['allow_optional_none'] =
                    ($fieldDef['allow_optional_none'] ?? false) === true;

                // Resolve the `option_label_maps` slot for option rows
                $row['option_map_key'] = $fieldDef['preload_slot'] ?? $nestedKey;

                // Read optional sibling path that makes the label show as required
                $requiredWith = $fieldDef['required_with_nested_field'] ?? null;

                // Expose sibling path when joiner (or similar) is required with text
                if (\is_string($requiredWith) && $requiredWith !== '') {
                    $row['label_required_when_sibling_path_nonempty'] =
                        sprintf('%s.%s', $groupKey, $requiredWith);
                }
            }

            // Append this nested row to the ordered list
            $out[] = $row;
        }

        return $out;
    }

    /**
     * CP editor blocks per configuration: ordered checkboxes, radios, and group
     * rows derived from `subfields` order and each row’s `available_in`.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function cpEditorBlocksByConfigurationForPreload(): array
    {
        // Read the unified subfields map from configuration
        $subfields = $this->raw['subfields'] ?? null;

        // Return an empty map when subfields are missing or invalid
        if (! \is_array($subfields)) {
            return [];
        }

        // Initialize the configuration handle to block list map
        $byConfiguration = [];

        // Build one ordered block list per configuration mode
        foreach ($this->configurationHandles() as $configurationHandle) {
            // Start the ordered block list for this mode
            $blocks = [];

            // Walk subfields in config file order
            foreach ($subfields as $key => $def) {
                // Skip invalid subfield rows
                if (! \is_array($def)) {
                    continue;
                }

                /** @var array<string, mixed> $def */

                // Skip the composed line row (not edited in the CP shell)
                if ($key === 'value') {
                    continue;
                }

                // Skip subfields that do not apply to this configuration mode
                if (! $this->subfieldDefinitionAppliesToMode($def, $configurationHandle)) {
                    continue;
                }

                // Read the subfield storage type
                $type = self::fieldType($def);

                // Append supported editor block types only
                if (
                    in_array($type, ['checkboxes', 'radios', 'group'], true)
                ) {
                    // Describe one editor block for this subfield row
                    $row = [
                        'type' => $type,
                        'key' => $key,
                    ];

                    // Surface optional-none radios so the Vue shell can pick a widget
                    if ($type === 'radios') {
                        $row['allow_optional_none'] =
                            ($def['allow_optional_none'] ?? false) === true;
                    }

                    // Wire checkbox option rows to their `option_label_maps` slot
                    if ($type === 'checkboxes') {
                        $row['option_map_key'] = $def['preload_slot'] ?? $key;
                    }

                    // Attach nested editor rows for group subfields
                    if ($type === 'group') {
                        $row['nested'] = $this->nestedCpEditorRowsForGroup(
                            $key,
                            $def
                        );
                    }

                    // Append this block to the ordered list for the mode
                    $blocks[] = $row;
                }
            }

            // Store the ordered block list for this configuration handle
            $byConfiguration[$configurationHandle] = $blocks;
        }

        // Return the assembled per-configuration block map
        return $byConfiguration;
    }

    /**
     * “No …” labels for optional radios (CP `RoleTitleOptionalRadioGroup`): each
     * top-level one by its subfield key, and each nested one under a group by its
     * dotted storage path.
     *
     * @return array<string, string> Subfield key or nested storage path to translated none label
     */
    public function optionalRadioNoneLabelsByTopLevelRadiosSubfield(): array
    {
        // Initialize the subfield key to none-label map
        $out = [];

        // Walk each top-level radios subfield from configuration order
        foreach ($this->topLevelRadiosSubfieldKeys() as $subfieldKey) {
            // Skip rows that are not optional-none radios
            if (($this->topLevelSubfieldRow($subfieldKey)['allow_optional_none'] ?? false) !== true) {
                continue;
            }

            // Resolve the Statamic translation for the none option on this subfield
            $out[$subfieldKey] = __(
                'aether-people::fieldtypes.role_title.option.none_'.$subfieldKey
            );
        }

        // Nested optional radios under a group, keyed by their dotted storage path
        // (`scope.scope_article`, UAMS-Web/uams-statamic-archive#2546), which is the key the CP reads first
        foreach ($this->groupSubfieldKeys() as $groupKey) {
            $fields = $this->topLevelSubfieldRow($groupKey)['fields'] ?? null;

            if (! \is_array($fields)) {
                continue;
            }

            foreach ($fields as $nestedKey => $fieldDef) {
                if (! \is_array($fieldDef)) {
                    continue;
                }

                if (self::fieldType($fieldDef) !== 'radios') {
                    continue;
                }

                if (($fieldDef['allow_optional_none'] ?? false) !== true) {
                    continue;
                }

                $out[$groupKey.'.'.$nestedKey] = __(
                    'aether-people::fieldtypes.role_title.option.none_'.$nestedKey
                );
            }
        }

        // Return the assembled none-label map
        return $out;
    }

    /**
     * Translate a CP field label from optional `cp_label` / `cp_label_lang_suffix`
     * on a subfield row, falling back to `aether-people::fieldtypes.role_title.field`
     * plus the storage key suffix.
     *
     * @param  array<string, mixed>  $def  Subfield or nested field definition row
     */
    private function translatedCpFieldLabelFromRow(
        array $def,
        string $fallbackLangSuffix
    ): string {
        // Prefer a full translation key when the row declares one
        $full = $def['cp_label'] ?? null;

        if (\is_string($full) && $full !== '') {
            return __($full);
        }

        // Use an explicit lang file suffix under `role_title.field` when present
        $suffix = $def['cp_label_lang_suffix'] ?? null;

        if (\is_string($suffix) && $suffix !== '') {
            return __('aether-people::fieldtypes.role_title.field.'.$suffix);
        }

        // Fall back to the storage key as the field lang suffix
        return __('aether-people::fieldtypes.role_title.field.'.$fallbackLangSuffix);
    }

    /**
     * Top-level CP field labels for `checkboxes` and `radios` subfields.
     *
     * @return array<string, string> Storage key to translated label
     */
    public function cpSubfieldLabelsForPreload(): array
    {
        // Read the unified subfields map from configuration
        $subfields = $this->raw['subfields'] ?? null;

        // Return an empty map when subfields are missing or invalid
        if (! \is_array($subfields)) {
            return [];
        }

        // Initialize the storage key to label map
        $out = [];

        // Walk top-level subfields in config file order
        foreach ($subfields as $key => $def) {
            // Skip invalid subfield rows
            if (! \is_array($def)) {
                continue;
            }

            /** @var array<string, mixed> $def */

            // Read the subfield storage type
            $type = self::fieldType($def);

            // Collect labels only for CP-edited scalar groups (not `group` rows)
            if ($type !== 'checkboxes' && $type !== 'radios') {
                continue;
            }

            // Resolve the translated label for this subfield row
            $out[$key] = $this->translatedCpFieldLabelFromRow(
                $def,
                $key
            );
        }

        // Return the assembled top-level label map
        /** @var array<string, string> $out */
        return $out;
    }

    /**
     * Nested CP field labels for `radios` / `text` / `entries` fields under `group` subfields.
     *
     * @return array<string, string> Dotted path (`group.nested`) to translated label
     */
    public function cpNestedFieldLabelsForPreload(): array
    {
        // Initialize the dotted path to label map
        $out = [];

        // Walk each group subfield from configuration order
        foreach ($this->groupSubfieldKeys() as $groupKey) {
            // Read nested fields for this group (`groupSubfieldKeys()` lists only group rows)
            $fields = $this->topLevelSubfieldRow($groupKey)['fields'] ?? null;

            // Skip groups without a nested field map
            if (! \is_array($fields)) {
                continue;
            }

            // Label each nested radios or text field that appears in the CP
            foreach ($fields as $nestedKey => $fieldDef) {
                // Skip invalid nested rows
                if (! \is_array($fieldDef)) {
                    continue;
                }

                /** @var array<string, mixed> $fieldDef */

                // Read the nested field storage type
                $nestedType = self::fieldType($fieldDef);

                // Keep only nested field types rendered with a field label
                if (! in_array($nestedType, ['radios', 'text', 'entries'], true)) {
                    continue;
                }

                // Build the dotted storage path for this nested field
                $path = sprintf('%s.%s', $groupKey, $nestedKey);

                // Resolve the translated label for this nested row
                $out[$path] = $this->translatedCpFieldLabelFromRow(
                    $fieldDef,
                    $nestedKey
                );
            }
        }

        // Return the assembled nested label map
        return $out;
    }

    /**
     * Compose-checklist rules per configuration for the CP warning alert.
     *
     * Each rule includes a translated `alert_label` for display when still unset.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function composeChecklistRulesByConfigurationForPreload(): array
    {
        // Read the unified subfields map from configuration
        $subfields = $this->raw['subfields'] ?? null;

        // Return an empty map when subfields are missing or invalid
        if (! \is_array($subfields)) {
            return [];
        }

        // Initialize the configuration handle to rule list map
        $byConfiguration = [];

        // Build one ordered rule list per configuration mode
        foreach ($this->configurationHandles() as $configurationHandle) {
            // Start the ordered rule list for this mode
            $rules = [];

            // Walk subfields in config file order
            foreach ($subfields as $key => $def) {
                // Skip invalid subfield rows
                if (! \is_array($def)) {
                    continue;
                }

                /** @var array<string, mixed> $def */

                // Skip subfields that do not apply to this configuration mode
                if (! $this->subfieldDefinitionAppliesToMode($def, $configurationHandle)) {
                    continue;
                }

                // Read the subfield storage type
                $type = self::fieldType($def);

                // Map top-level radios to checklist kinds aligned with validation
                if ($type === 'radios') {
                    // Treat optional-none radios as optional-radio checklist rows
                    $optionalNone = ($def['allow_optional_none'] ?? false) === true;

                    // Append one checklist rule for this radios subfield
                    $rules[] = [
                        'path' => $key,
                        'kind' => $optionalNone ? 'optional_radio' : 'required_option',
                        'alert_label' => __(
                            'aether-people::fieldtypes.role_title.field.'.$key
                        ),
                    ];

                    continue;
                }

                // Walk nested radios under group subfields for conditional rules
                if ($type !== 'group') {
                    continue;
                }

                // Read nested fields for this group subfield
                $groupKey = $key;
                $fields = $def['fields'] ?? null;

                // Skip groups without a nested field map
                if (! \is_array($fields)) {
                    continue;
                }

                // Inspect each nested field for radios with sibling requirements
                foreach ($fields as $nestedKey => $fieldDef) {
                    // Skip invalid nested rows
                    if (! \is_array($fieldDef)) {
                        continue;
                    }

                    /** @var array<string, mixed> $fieldDef */

                    // Keep radios nested fields only
                    if (self::fieldType($fieldDef) !== 'radios') {
                        continue;
                    }

                    // Skip nested rows that do not apply to this configuration mode
                    if (! $this->subfieldDefinitionAppliesToMode(
                        $fieldDef,
                        $configurationHandle
                    )) {
                        continue;
                    }

                    // Read optional `required_with_nested_field` sibling wiring
                    $requiredWith = $fieldDef['required_with_nested_field'] ?? null;
                    // Skip radios without a sibling-conditional requirement
                    if (! \is_string($requiredWith)) {
                        continue;
                    }

                    if ($requiredWith === '') {
                        continue;
                    }

                    // Resolve the preload slot name for valid joiner option keys
                    $slot = $fieldDef['preload_slot'] ?? $nestedKey;

                    // Append one conditional checklist rule for this nested radios field
                    $rules[] = [
                        'path' => sprintf('%s.%s', $groupKey, $nestedKey),
                        'kind' => 'required_when_sibling_nonempty',
                        'sibling_path' => sprintf('%s.%s', $groupKey, $requiredWith),
                        'option_preload_slot' => $slot,
                        'alert_label' => __(
                            'aether-people::fieldtypes.role_title.field.'.$nestedKey
                        ),
                    ];
                }
            }

            // Store the ordered rule list for this configuration handle
            $byConfiguration[$configurationHandle] = $rules;
        }

        // Return the assembled per-configuration rule map
        return $byConfiguration;
    }

    /**
     * Read a field row's `type` as text, or `''` when it is missing or not a string.
     *
     * @param  array<array-key, mixed>  $def  A subfield or nested field row
     */
    private static function fieldType(
        array $def
    ): string {
        // Read the declared field type when present
        $type = $def['type'] ?? null;

        // Equivalent for the '' fallback: every caller compares the type strictly against a field type name, which no other text matches either
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        return \is_string($type) ? $type : '';
    }

    /**
     * Whether a subfield definition applies to a configuration mode.
     *
     * @param  array<string, mixed>  $def
     */
    private function subfieldDefinitionAppliesToMode(
        array $def,
        string $handle
    ): bool {
        // Read subfield-level availability for configuration modes
        $avail = $def['available_in'] ?? null;

        // Check if this subfield applies to the active configuration
        return $this->modeMatchesAvailableIn(
            $this->availableInWireList($avail),
            $handle
        );
    }

    /**
     * Normalize a raw `available_in` value to an ordered list of string handles.
     *
     * Non-array values yield null. The element count is preserved so an empty
     * list keeps its “available in all configurations” meaning; each entry is
     * coerced to its string form (handles are always strings in config, and a
     * non-string entry could never strict-match a string handle anyway).
     *
     * @return list<string>|null
     */
    private function availableInWireList(
        mixed $avail
    ): ?array {
        // Treat non-array availability as “available in all configurations”
        if (! \is_array($avail)) {
            return null;
        }

        // Preserve order and count while typing each entry as a string handle
        $out = [];

        foreach ($avail as $entry) {
            // Coerce scalar handles to string; replace non-scalars with empty
            $out[] = \is_scalar($entry) ? (string) $entry : '';
        }

        return $out;
    }

    /**
     * Whether a mode handle matches an `available_in` list.
     *
     * @param  list<string>|null  $availableIn
     */
    private function modeMatchesAvailableIn(
        ?array $availableIn,
        string $handle
    ): bool {
        // Treat empty or missing lists as “available in all configurations”
        if ($availableIn === null || $availableIn === []) {
            // Allow all configuration modes
            return true;
        }

        // Check if the active handle appears in the allow list
        return \in_array($handle, $availableIn, true);
    }
}
