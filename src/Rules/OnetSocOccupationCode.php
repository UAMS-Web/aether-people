<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom validation rule for validating O*Net-SOC Occupation Codes.
 *
 * This rule ensures O*Net-SOC Occupation Codes conform to the standard format of
 * two digits, followed by a hyphen, followed by four digits, followed by a
 * period, and ending with two digits (e.g., `11-1011.00`, `13-2011.01`). It
 * rejects invalid formats, incorrect character counts, or missing required
 * separators.
 *
 * The regular expression pattern is /^\d{2}-\d{4}\.\d{2}$/.
 *
 * This pattern ensures:
 *   - Exactly two digits (\d{2}),
 *   - Followed by a hyphen (-),
 *   - Followed by exactly four digits (\d{4}),
 *   - Followed by a period (.),
 *   - Ending with exactly two digits (\d{2}),
 *   - No extra characters, spaces or special symbols are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message. An empty
 * value passes, so that a rule such as `required` decides whether a value is needed, and
 * an array fails, since a code is one string. {@see self::isValid()} gives the same verdict
 * without a failure message, for a caller that only needs to know.
 */
class OnetSocOccupationCode implements ValidationRule
{
    /**
     * The O*Net-SOC Occupation Code format.
     */
    private const string PATTERN = '/^\d{2}-\d{4}\.\d{2}$/D';

    /**
     * Validate that the input value conforms to the O*Net-SOC Occupation Code format.
     *
     * @param  string  $attribute  The name of the field being validated
     * @param  mixed  $value  The value of the field
     * @param  Closure  $fail  The closure to call if validation fails
     */
    #[\Override]
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Leave an empty value to rules such as `required`
        if (! $value) {
            return;
        }

        // A code is one string, so an array fails
        if (\is_array($value)) {
            $fail('The value must be a string, not an array.');

            return;
        }

        // Check the value against the O*Net-SOC Occupation Code format
        if (! \is_scalar($value) || ! \preg_match(self::PATTERN, (string) $value)) {
            $fail(__('aether-people::validation.onet_soc_occupation_code'));
        }
    }

    /**
     * Whether a value passes, without a failure message.
     *
     * @param  mixed  $value  The value to check
     * @return bool True when the value would pass validation, false otherwise
     */
    public function isValid(
        mixed $value
    ): bool {
        $isValid = true;

        $this->validate(
            // The attribute only names the field in messages, which this check discards
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            '',
            $value,
            function () use (&$isValid): void {
                $isValid = false;
            }
        );

        return $isValid;
    }
}
