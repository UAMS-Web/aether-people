<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use UAMSWeb\Aether\Enums\Isco08;

/**
 * Normalizes Statamic `isco_08` field payloads to a consistent tier-key shape for
 * YAML storage and CP previews. Incoming `value` is ignored here and recomputed
 * from the tier keys by the fieldtype.
 */
final class Isco08FieldtypeStorage
{
    /**
     * Default empty stored shape (explicit nulls for each tier).
     *
     * @return array{major_group: null, sub_major_group: null, minor_group: null, unit_group: null}
     */
    public static function emptyShape(): array
    {
        return [
            'major_group' => null,
            'sub_major_group' => null,
            'minor_group' => null,
            'unit_group' => null,
        ];
    }

    /**
     * Coerce a single tier value to a trimmed code or null (sentinel / empty).
     */
    public static function normalizeTierCode(mixed $v): ?string
    {
        // Coerce numeric scalars to string before trimming
        if (\is_int($v) || \is_float($v)) {
            $v = (string) $v;
        }

        // Reject non-string tier values, including null and the CP sentinel `false`
        if (! \is_string($v)) {
            return null;
        }

        // Trim whitespace and treat blank strings as empty
        $t = trim($v);

        return $t === '' ? null : $t;
    }

    /**
     * Enforce a valid ISCO-08 parent chain; drop invalid descendants and coerce
     * sentinels to null for lower tiers.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeChain(array $data): array
    {
        // Drop any client-provided `value`; the fieldtype recomputes it
        unset($data['value']);

        // Normalize the major tier input
        $major = self::normalizeTierCode($data['major_group'] ?? null);

        // Check if no major is selected
        if ($major === null) {
            return [];
        }

        // Resolve the enum case for the major code
        $majorCase = Isco08::tryFrom($major);

        // Check whether the major is a valid level-1 structure code
        if ($majorCase === null || $majorCase->level() !== 1) {
            return [];
        }

        // Normalize the sub-major tier input
        $sub = self::normalizeTierCode($data['sub_major_group'] ?? null);

        // Stop at major when sub-major is empty (sentinel or unset)
        if ($sub === null) {
            return [
                'major_group' => $major,
                'sub_major_group' => null,
                'minor_group' => null,
                'unit_group' => null,
            ];
        }

        // Resolve the enum case for the sub-major code
        $subCase = Isco08::tryFrom($sub);

        // Check whether the sub-major exists and nests under the selected major
        if ($subCase === null || $subCase->parent() !== $major) {
            return [
                'major_group' => $major,
                'sub_major_group' => null,
                'minor_group' => null,
                'unit_group' => null,
            ];
        }

        // Normalize the minor tier input
        $minor = self::normalizeTierCode($data['minor_group'] ?? null);

        // Stop at sub-major when minor is empty
        if ($minor === null) {
            return [
                'major_group' => $major,
                'sub_major_group' => $sub,
                'minor_group' => null,
                'unit_group' => null,
            ];
        }

        // Resolve the enum case for the minor code
        $minorCase = Isco08::tryFrom($minor);

        // Check whether the minor exists and nests under the selected sub-major
        if ($minorCase === null || $minorCase->parent() !== $sub) {
            return [
                'major_group' => $major,
                'sub_major_group' => $sub,
                'minor_group' => null,
                'unit_group' => null,
            ];
        }

        // Normalize the unit tier input
        $unit = self::normalizeTierCode($data['unit_group'] ?? null);

        // Stop at minor when unit is empty
        if ($unit === null) {
            return [
                'major_group' => $major,
                'sub_major_group' => $sub,
                'minor_group' => $minor,
                'unit_group' => null,
            ];
        }

        // Resolve the enum case for the unit code
        $unitCase = Isco08::tryFrom($unit);

        // Check whether the unit exists and nests under the selected minor
        if ($unitCase === null || $unitCase->parent() !== $minor) {
            return [
                'major_group' => $major,
                'sub_major_group' => $sub,
                'minor_group' => $minor,
                'unit_group' => null,
            ];
        }

        // Return the fully specified four-tier chain
        return [
            'major_group' => $major,
            'sub_major_group' => $sub,
            'minor_group' => $minor,
            'unit_group' => $unit,
        ];
    }

    /**
     * Whether every stored tier is empty (no major selected).
     *
     * @param  array<string, mixed>  $normalized  Output of {@see self::normalizeChain()}
     */
    public static function isFullyEmpty(array $normalized): bool
    {
        // Treat an empty normalized array as “no selection”
        return $normalized === []
            // Check whether the major tier is empty after coercion
            || self::normalizeTierCode($normalized['major_group'] ?? null) === null;
    }
}
