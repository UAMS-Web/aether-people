<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use UAMSWeb\Aether\People\Support\PersonNameComposite;
use UAMSWeb\Aether\People\Support\PersonNameDisplayFormats;

/**
 * Validates {@see PersonName} `display_format`: required when the blueprint marks
 * the field required and at least one format option exists; value must be an
 * allowed key for the posted field payload.
 *
 * Also validates the `name_display_format` field (UAMS-Web/uams-statamic-archive#3186), which keeps the
 * display format beside `person_name` rather than inside it: the name the
 * format must fit is then rebuilt from `person_name` and its sibling fields.
 */
final class PersonNameDisplayFormatRule implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  string  $parentFieldHandle  Top-level field handle (e.g. person_name)
     * @param  bool  $parentFieldMarkedRequired  Whether a format must be chosen when one is available
     * @param  bool  $joinRefinementFields  Whether to rebuild the name from `person_name` and its
     *                                      sibling fields, for the `name_display_format` field
     */
    public function __construct(
        private readonly string $parentFieldHandle,
        private readonly bool $parentFieldMarkedRequired,
        private readonly bool $joinRefinementFields = false
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        $parent = $this->joinRefinementFields
            ? PersonNameComposite::fromData($this->data)
            : $this->data[$this->parentFieldHandle] ?? null;

        if (! \is_array($parent)) {
            if ($this->parentFieldMarkedRequired) {
                $fail(__('validation.required'));
            }

            return;
        }

        /** @var array<string, mixed> $parent */
        $normalized = PersonNameDisplayFormats::normalizeValue($parent);
        $allowed = PersonNameDisplayFormats::allowedKeys($normalized);

        if ($allowed === []) {
            return;
        }

        if ($value === null || $value === '') {
            if ($this->parentFieldMarkedRequired) {
                $fail(__('validation.required'));
            }

            return;
        }

        if (! \is_string($value)) {
            $fail(__('validation.string'));

            return;
        }

        // Equivalent: format keys such as `full_first_only@ns` are never numeric
        // strings, so loose comparison matches exactly as strict comparison does.
        // @pest-mutate-ignore: TrueToFalse
        if (! \in_array($value, $allowed, true)) {
            $fail(__('validation.in'));
        }

        unset($parent, $normalized, $allowed);
    }
}
