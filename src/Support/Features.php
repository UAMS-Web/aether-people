<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

/**
 * The add-on's feature switches, read from `config('people.features')`.
 *
 * Each switch is on unless its config value is exactly `false`, so a host that publishes an
 * older copy of `config/people.php` without the `features` key keeps every feature.
 */
final class Features
{
    /**
     * The clinical provider layer: the eligibility computeds and listeners, the scheduled
     * re-index, the Find-a-Doc API routes and the clinical provider lens.
     */
    public const string PROVIDER_LAYER = 'provider_layer';

    /**
     * Whether a feature is on.
     *
     * @param  string  $feature  One of the constants on this class.
     */
    public static function enabled(string $feature): bool
    {
        return config("people.features.{$feature}", true) !== false;
    }

    /**
     * Whether the clinical provider layer is on.
     */
    public static function providerLayer(): bool
    {
        return self::enabled(self::PROVIDER_LAYER);
    }
}
