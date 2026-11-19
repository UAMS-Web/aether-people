<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use UAMSWeb\Aether\People\Support\Isco08FieldtypeStorage;

/**
 * Validates cross-tier consistency for an `isco_08` field (after per-key rules).
 *
 * Isco08FieldtypeStorage::normalizeChain() keeps only a valid major and the tiers
 * that nest beneath it, dropping a stale `value` and any invalid branch, so the
 * only state left to refuse is a field with no valid major when it is required.
 */
final readonly class Isco08FieldtypeStoredRule implements ValidationRule
{
    public function __construct(
        private bool $required,
    ) {}

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check whether the field value is an array-shaped payload
        if (! \is_array($value)) {
            $fail(__('aether-people::fieldtypes.isco_08.validation.not_array'));

            return;
        }

        // Normalize tiers so validation ignores stale `value` and invalid branches
        /** @var array<string, mixed> $value */
        $normalized = Isco08FieldtypeStorage::normalizeChain($value);

        // Require a valid major when the field is marked required
        if ($this->required && $normalized === []) {
            $fail(__('aether-people::fieldtypes.isco_08.validation.required_major'));
        }
    }
}
