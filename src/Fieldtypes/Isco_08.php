<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Fieldtypes;

use Illuminate\Validation\Rule;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use UAMSWeb\Aether\Enums\Isco08;
use UAMSWeb\Aether\People\Rules\Isco08FieldtypeStoredRule;
use UAMSWeb\Aether\People\Support\Isco08FieldtypeStorage;

/**
 * Chained ISCO-08 occupation structure selector: major, sub-major, minor, and unit
 * groups backed by {@see Isco08}. Stores four nullable tier codes plus a hidden
 * `value` key (resolved 1–4 digit code), recomputed from the tiers on every save.
 */
final class Isco_08 extends Fieldtype
{
    /**
     * @var string
     */
    protected static $title = 'aether-people::fieldtypes.isco_08.title';

    /**
     * @var array<int, string>
     */
    // The coverage driver attributes no coverage to a property default, so Pest selects no test for its mutants.
    // Removing any entry by hand, it fails "declares its picker metadata".
    // @pest-mutate-ignore: RemoveArrayItem
    protected $keywords = [
        'ILO',
        'ISCO 08',
        'ISCO 2008',
        'ISCO',
        'ISCO-08',
        'ISCO-2008',
        'ISCO08',
        'ISCO2008',
        'International Labor Organization',
        'International Labour Organization',
        'International Standard Classification of Occupations 2008',
        'International Standard Classification of Occupations',
        'classification',
        'classifications',
        'classifier',
        'classifiers',
        'classify',
        'classifying',
        'code',
        'codes',
        'identification',
        'identifications',
        'identifier',
        'identifiers',
        'identify',
        'occupation',
    ];

    /**
     * @var array<int, string>
     */
    // The coverage driver attributes no coverage to a property default, so Pest selects no test for its mutants.
    // Removing either entry by hand, it fails "declares its picker metadata".
    // @pest-mutate-ignore: RemoveArrayItem
    protected $categories = [
        'special',
        'structured',
    ];

    /**
     * @var bool
     */
    // The coverage driver attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Flipped by hand, it fails "declares its picker metadata".
    // @pest-mutate-ignore: TrueToFalse
    protected $selectable = true;

    /**
     * @var bool
     */
    // The coverage driver attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Flipped by hand, it fails "declares its picker metadata".
    // @pest-mutate-ignore: FalseToTrue
    protected $localizable = false;

    /**
     * @var string
     */
    protected $component = 'isco_08';

    #[\Override]
    public function icon(): string
    {
        return 'array';
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function preload(): array
    {
        return Isco08::hierarchyOptionRows();
    }

    /**
     * @return array<string, null>
     */
    #[\Override]
    public function defaultValue(): array
    {
        return Isco08FieldtypeStorage::emptyShape();
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function preProcess(mixed $data): array
    {
        // Check whether the incoming payload is usable as an associative array
        if ($data === null || ! \is_array($data)) {
            // Return the empty default shape with `value` derived from tiers
            return $this->withResolvedValueKey(Isco08FieldtypeStorage::emptyShape());
        }

        // Overlay editor data onto default tier nulls so missing keys stay defined
        /** @var array<string, mixed> $merged */
        $merged = \array_replace(Isco08FieldtypeStorage::emptyShape(), $data);
        // Normalize the tier chain to valid parent/child codes
        $normalized = Isco08FieldtypeStorage::normalizeChain($merged);

        // Check if normalization dropped everything (no valid major)
        if ($normalized === []) {
            return $this->withResolvedValueKey(Isco08FieldtypeStorage::emptyShape());
        }

        // Map YAML null sentinels to CP `false`, then attach recomputed `value`
        return $this->withResolvedValueKey($this->withCpFalseSentinels($normalized));
    }

    #[\Override]
    public function augment(mixed $value): mixed
    {
        // Pass through non-array stored values unchanged
        if (! \is_array($value)) {
            return $value;
        }

        // Overlay stored tiers onto the default shape
        /** @var array<string, mixed> $merged */
        $merged = \array_replace(Isco08FieldtypeStorage::emptyShape(), $value);
        // Normalize the tier chain for read-time consistency
        $normalized = Isco08FieldtypeStorage::normalizeChain($merged);

        // Check if there is no valid major after normalization
        if ($normalized === []) {
            return $this->withResolvedValueKey(Isco08FieldtypeStorage::emptyShape());
        }

        // Prepare CP sentinel flags and expose the resolved code for Antlers/API
        return $this->withResolvedValueKey($this->withCpFalseSentinels($normalized));
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function process(mixed $data): array
    {
        // Reject non-array input so YAML never stores a scalar here
        if (! \is_array($data)) {
            return [];
        }

        // Merge posted tiers with the default null shape
        /** @var array<string, mixed> $merged */
        $merged = \array_replace(Isco08FieldtypeStorage::emptyShape(), $data);
        // Strip invalid branches and coerce sentinels to null for storage
        $normalized = Isco08FieldtypeStorage::normalizeChain($merged);

        // Check if the editor cleared the field entirely
        // The empty chain resolves no value either, so falling through also returns [] (RemoveEarlyReturn is equivalent)
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($normalized === []) {
            return [];
        }

        // Persist canonical `value` from the normalized tiers (ignore client `value`)
        return $this->withResolvedValueKey($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function preProcessValidatable(mixed $value): array
    {
        // Build a validatable array from a non-array payload
        if (! \is_array($value)) {
            return $this->withResolvedValueKey(Isco08FieldtypeStorage::emptyShape());
        }

        // Merge the posted value onto default tier keys before validation
        /** @var array<string, mixed> $merged */
        $merged = \array_replace(Isco08FieldtypeStorage::emptyShape(), $value);
        // Normalize tiers so rules evaluate a canonical chain
        $normalized = Isco08FieldtypeStorage::normalizeChain($merged);

        // Check if the payload normalizes to an empty selection
        if ($normalized === []) {
            return $this->withResolvedValueKey(Isco08FieldtypeStorage::emptyShape());
        }

        // Match CP sentinel semantics and attach `value` for nested rule evaluation
        return $this->withResolvedValueKey($this->withCpFalseSentinels($normalized));
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    public function rules(): array
    {
        return ['array'];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function extraValidationAttributes(): array
    {
        // Resolve the Statamic field for dotted validation attribute paths
        $field = $this->field();

        // Check if the field is unavailable (e.g. detached fieldtype)
        if (! $field instanceof Field) {
            return [];
        }

        // Build the handle prefix used by nested validation keys
        /** @var string $prefix */
        $prefix = $field->handle();
        // Create a helper that prefixes a relative tier key
        $p = static fn (string $path): string => sprintf('%s.%s', $prefix, $path);

        // Map dotted paths to human-readable validation labels
        return [
            $p('major_group') => __('aether-people::fieldtypes.isco_08.field.major_group'),
            $p('sub_major_group') => __('aether-people::fieldtypes.isco_08.field.sub_major_group'),
            $p('minor_group') => __('aether-people::fieldtypes.isco_08.field.minor_group'),
            $p('unit_group') => __('aether-people::fieldtypes.isco_08.field.unit_group'),
            $p('value') => __('aether-people::fieldtypes.isco_08.field.value'),
        ];
    }

    /**
     * @return array<string, array<int, string|object>>
     */
    #[\Override]
    public function extraRules(): array
    {
        // Resolve the Statamic field backing these rules
        $field = $this->field();

        // Check if nested rules cannot be anchored to a field handle
        if (! $field instanceof Field) {
            return [];
        }

        // Read blueprint config for required toggles and validate arrays
        $config = $field->config();
        $validate = $config['validate'] ?? [];
        // Detect explicit `required` in the blueprint validate list
        $validateHasRequired = \is_array($validate) && \in_array('required', $validate, true);
        // Treat UI “required” or validate-required as strict tier requirements
        $required = ! empty($config['required']) || $validateHasRequired;
        // Capture the field handle for dotted rule keys
        /** @var string $prefix */
        $prefix = $field->handle();
        // Build dotted paths under this field handle
        $dot = static fn (string $path): string => sprintf('%s.%s', $prefix, $path);
        // Collect allowed major codes from the enum hierarchy metadata
        $majorCodes = \array_column(Isco08::hierarchyOptionRows()['majors'], 'key');

        // Start major-group rules as nullable string constrained to known majors
        $majorRules = [
            'nullable',
            'string',
            Rule::in($majorCodes),
        ];

        // Require a major when the blueprint marks the field required
        if ($required) {
            \array_unshift($majorRules, 'required');
        }

        // Register per-key rules plus the cross-tier consistency rule on the root.
        // Sub-major through unit may be boolean `false` (CP sentinel) before save; do not use `string` there.
        return [
            $dot('major_group') => $majorRules,
            $dot('sub_major_group') => ['nullable'],
            $dot('minor_group') => ['nullable'],
            $dot('unit_group') => ['nullable'],
            $dot('value') => ['nullable', 'string'],
            $prefix => [new Isco08FieldtypeStoredRule($required)],
        ];
    }

    /**
     * Map YAML null sentinels to `false` for the CP so the Combobox can distinguish
     * “stop here” from an untouched tier.
     *
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function withCpFalseSentinels(array $normalized): array
    {
        // normalizeChain() yields only valid, non-empty tier codes or null, so a
        // string tier below is never empty and needs no separate empty check

        // Read the current major selection for downstream sentinel checks
        $major = $normalized['major_group'] ?? null;

        // Mark sub-major as an explicit CP sentinel when major is set but sub is null
        if (\is_string($major) && ($normalized['sub_major_group'] ?? null) === null) {
            $normalized['sub_major_group'] = false;
        }

        // Read the current sub-major selection (may be `false` from the line above)
        $sub = $normalized['sub_major_group'] ?? null;

        // Mark minor as a CP sentinel when sub-major is a code but minor is null
        if (\is_string($sub) && ($normalized['minor_group'] ?? null) === null) {
            $normalized['minor_group'] = false;
        }

        // Read the current minor selection
        $minor = $normalized['minor_group'] ?? null;

        // Mark unit as a CP sentinel when minor is a code but unit is null
        if (\is_string($minor) && ($normalized['unit_group'] ?? null) === null) {
            $normalized['unit_group'] = false;
        }

        return $normalized;
    }

    /**
     * Set `value` from the tier keys (editor-hidden; not trusted from the client).
     *
     * @param  array<string, mixed>  $tierData  Normalized tier keys only (no `value`)
     * @return array<string, mixed>
     */
    private function withResolvedValueKey(array $tierData): array
    {
        // Resolve the deepest selected structure code from tier keys alone
        $resolved = Isco08::resolvedDeepestStructureCode($tierData);

        // Check if a non-empty resolved code exists
        // The resolver maps an empty tier to null, so it never returns '' (EmptyStringToNotEmpty is equivalent)
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if ($resolved !== null && $resolved !== '') {
            // Persist the derived code on the stored payload
            $tierData['value'] = $resolved;
        } else {
            // Omit `value` when no tier yields a resolvable code
            unset($tierData['value']);
        }

        return $tierData;
    }
}
