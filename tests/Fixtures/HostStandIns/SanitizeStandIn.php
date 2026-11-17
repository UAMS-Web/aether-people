<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests\Fixtures\HostStandIns;

use Statamic\Modifiers\Modifier;

/**
 * A stand-in for the host's `sanitize` modifier, which the moved templates call on editor text.
 *
 * It encodes the value for HTML, the one property the template tests read. The host's own tests
 * cover its full behavior.
 */
final class SanitizeStandIn extends Modifier
{
    /**
     * Encode the value for HTML.
     *
     * @param  mixed  $value  The text.
     * @param  array<int|string, mixed>  $params  Unused.
     * @param  array<int|string, mixed>  $context  Unused.
     * @return string The encoded text.
     */
    public function index(mixed $value, array $params, array $context): string
    {
        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
    }
}
