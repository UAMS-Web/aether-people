<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use UAMSWeb\Aether\People\Support\RoleTitleValue;

/**
 * Validates a top-level `role_title` radios subfield against the parent value’s
 * `configuration`, using option keys derived from `config/role_title.php`.
 *
 * Enable per subfield with `validation.per_configuration_options` on the radios
 * row (see `RoleTitleDefinition::radiosSubfieldUsesPerConfigurationOptionValidation()`).
 * Allowed keys per mode come from `RoleTitleValue::mapOptionKeysByConfigurationHandles()`.
 */
final class RoleTitlePerConfigurationOptionRule implements DataAwareRule, ValidationRule
{
    /**
     * Full request data bag for reading the parent role_title field.
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Create a rule bound to the parent field path, required flag, and radios key.
     *
     * @param  string  $parentFieldPath  Dotted path to the role_title value (e.g.
     *                                   `title` or `title_replicator.0.title`) for
     *                                   `Arr::get()` on the validator data bag
     * @param  bool  $parentFieldMarkedRequired  Whether the parent field is required
     * @param  string  $optionSubfieldKey  Top-level `subfields` storage key with an
     *                                     `options` map (e.g. `role`)
     */
    public function __construct(
        private readonly string $parentFieldPath,
        private readonly bool $parentFieldMarkedRequired,
        private readonly string $optionSubfieldKey
    ) {}

    /**
     * Receive the full validation data bag for `DataAwareRule`.
     *
     * @param  array<string, mixed>  $data  Request data including the parent field
     */
    public function setData(
        array $data
    ): static {
        // Store the bag so `validate()` can read the parent role_title payload
        $this->data = $data;

        // Return this rule for fluent chaining
        return $this;
    }

    /**
     * Validate the stored option key for the active configuration.
     *
     * @param  string  $attribute  Dotted validation attribute path
     * @param  mixed  $value  Raw radios subfield value
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Read the parent role_title array from the data bag using its dotted path
        $parent = Arr::get(
            $this->data,
            $this->parentFieldPath
        );

        // Check if the parent payload is present when the field is required
        if (! \is_array($parent)) {
            // Check if the parent field is marked required
            if ($this->parentFieldMarkedRequired) {
                // Fail the validation with the required error message
                $fail(__('validation.required'));
            }

            // Return from the validation
            return;
        }

        if (($parent['mode'] ?? null) === RoleTitleValue::MODE_UNSTRUCTURED) {
            return;
        }

        // Read the active configuration for allowed option keys
        $configuration = $parent['configuration'] ?? null;

        // Coerce the configuration to a string for match-style lookups. Empty
        // handles are filtered out, so neither '' nor the mutator's replacement
        // text names a configuration, and both find no options.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $configuration = \is_string($configuration) ? $configuration : '';

        // Map optional-radio sentinel to null like `RoleTitle::normalizeStored()`
        if ($value === RoleTitleValue::optionalSelectionNone()) {
            $value = null;
        }

        // Check if a required parent field still has an empty value. Removing the
        // return inside is equivalent: the empty-value return below also stops
        // there. The marker sits on the `if`, because one on the return itself
        // does not suppress a mutant of the node it annotates.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (
            $this->parentFieldMarkedRequired
            && ($value === null || $value === '')
        ) {
            // Fail the validation with the required error message
            $fail(__('validation.required'));

            // Return from the validation
            return;
        }

        // Allow empty value when the parent field is optional
        if (
            $value === null
            || $value === ''
        ) {
            // Return from the validation
            return;
        }

        // Check if the value is a string before membership checks
        if (! \is_string($value)) {
            // Fail the validation with the string error message
            $fail(__('validation.string'));

            // Return from the validation
            return;
        }

        // Read the allowed option keys for this configuration mode
        $allowed = $this->allowedOptionKeysForConfiguration($configuration, $this->optionSubfieldKey);

        // Check if the configuration has no options but a value was sent
        if ($allowed === []) {
            // Fail the validation with the in error message
            $fail(__('validation.in'));

            // Return from the validation
            return;
        }

        // Check if the value is one of the allowed keys for this mode
        if (! \in_array($value, $allowed, true)) {
            // Fail the validation with the in error message
            $fail(__('validation.in'));
        }
    }

    /**
     * Stored option keys allowed for the configuration and radios subfield.
     *
     * @param  string  $configuration  Active `configuration` value from the parent
     * @param  string  $subfieldKey  Top-level `subfields` key with an `options` map
     * @return list<string> Allowed stored keys for that radios field in that mode
     */
    private function allowedOptionKeysForConfiguration(
        string $configuration,
        string $subfieldKey
    ): array {
        // Read per-mode option keys from the unified subfield definition
        $byConfiguration = RoleTitleValue::mapOptionKeysByConfigurationHandles(
            $subfieldKey
        );

        // Return allowed keys for this configuration, or none when unknown
        return $byConfiguration[$configuration] ?? [];
    }
}
