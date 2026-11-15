<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests\Fixtures\HostStandIns;

use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;

/**
 * A stand-in for the host's `sanitize_url` modifier, which the moved templates call.
 *
 * It keeps the two properties the template tests read: a URL whose scheme is not
 * `http`, `https`, `mailto` or `tel` collapses to `#`, and the result is encoded for an attribute.
 * The host's own tests cover the full behavior (entity and whitespace obfuscated schemes).
 */
final class SanitizeUrlStandIn extends Modifier
{
    /**
     * Neutralize an unsafe scheme and encode the URL for an attribute.
     *
     * @param  mixed  $value  The URL.
     * @param  array<int|string, mixed>  $params  Unused.
     * @param  array<int|string, mixed>  $context  Unused.
     * @return string The attribute-safe URL, or `#`.
     */
    public function index(mixed $value, array $params, array $context): string
    {
        $url = $value instanceof Value ? $value->value() : $value;
        $url = is_scalar($url) ? trim((string) $url) : '';

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $scheme) === 1 && ! in_array(strtolower($scheme[1]), ['http', 'https', 'mailto', 'tel'], true)) {
            return '#';
        }

        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}
