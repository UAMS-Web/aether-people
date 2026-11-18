<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Illuminate\Support\Str;
use Statamic\Fields\Field;
use UAMSWeb\Aether\Enums\Isco08;
use UAMSWeb\Aether\People\Fieldtypes\Isco_08;

/**
 * CP-only `__cp_role_title_unstructured_*` replicator payloads for unstructured
 * `role_title` values: expanded on read paths, collapsed to list shapes on save.
 */
final class RoleTitleUnstructuredCpBridge
{
    public const string CP_REPLICATOR_ISCO = '__cp_role_title_unstructured_isco_08';

    public const string CP_REPLICATOR_ONET = '__cp_role_title_unstructured_onet_soc';

    public const string CP_REPLICATOR_OCC_SAME_AS = '__cp_role_title_unstructured_occ_same_as';

    public const string CP_REPLICATOR_FIELD_SAME_AS = '__cp_role_title_unstructured_field_same_as';

    private const string SET_HANDLE = 'row';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function expandForCp(array $payload): array
    {
        if (! self::payloadIsUnstructured($payload)) {
            return $payload;
        }

        $payload[self::CP_REPLICATOR_ISCO] = self::iscoRowsFromCodes(
            self::stringListAt($payload, ['occupation', 'category_code', 'isco_08'])
        );
        $payload[self::CP_REPLICATOR_ONET] = self::textRowsFromStrings(
            self::stringListAt($payload, ['occupation', 'category_code', 'onet_soc'])
        );
        $payload[self::CP_REPLICATOR_OCC_SAME_AS] = self::urlRowsFromStrings(
            self::stringListAt($payload, ['occupation', 'same_as'])
        );
        $payload[self::CP_REPLICATOR_FIELD_SAME_AS] = self::urlRowsFromStrings(
            self::stringListAt($payload, ['occupational_field', 'same_as'])
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function collapseFromCp(array $payload): array
    {
        if (! self::payloadIsUnstructured($payload)) {
            foreach (self::cpKeys() as $k) {
                unset($payload[$k]);
            }

            return $payload;
        }

        $payload['occupation'] ??= [];
        $payload['occupation'] = \is_array($payload['occupation']) ? $payload['occupation'] : [];
        $payload['occupation']['category_code'] ??= [];
        $payload['occupation']['category_code'] = \is_array($payload['occupation']['category_code'])
            ? $payload['occupation']['category_code']
            : [];

        if (\array_key_exists(self::CP_REPLICATOR_ISCO, $payload)) {
            $payload['occupation']['category_code']['isco_08'] = self::iscoCodesFromRows(
                $payload[self::CP_REPLICATOR_ISCO]
            );
        }

        if (\array_key_exists(self::CP_REPLICATOR_ONET, $payload)) {
            $payload['occupation']['category_code']['onet_soc'] = self::onetCodesFromRows(
                $payload[self::CP_REPLICATOR_ONET]
            );
        }

        if (\array_key_exists(self::CP_REPLICATOR_OCC_SAME_AS, $payload)) {
            $payload['occupation']['same_as'] = self::urlsFromRows(
                $payload[self::CP_REPLICATOR_OCC_SAME_AS]
            );
        }

        $payload['occupational_field'] ??= [];
        $payload['occupational_field'] = \is_array($payload['occupational_field'])
            ? $payload['occupational_field']
            : [];

        if (\array_key_exists(self::CP_REPLICATOR_FIELD_SAME_AS, $payload)) {
            $payload['occupational_field']['same_as'] = self::urlsFromRows(
                $payload[self::CP_REPLICATOR_FIELD_SAME_AS]
            );
        }

        foreach (self::cpKeys() as $k) {
            unset($payload[$k]);
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    public static function cpKeys(): array
    {
        return [
            self::CP_REPLICATOR_ISCO,
            self::CP_REPLICATOR_ONET,
            self::CP_REPLICATOR_OCC_SAME_AS,
            self::CP_REPLICATOR_FIELD_SAME_AS,
        ];
    }

    public static function isCpInternalKey(string $key): bool
    {
        // The CP keys are non-numeric strings, so loose and strict string comparison agree (TrueToFalse is equivalent)
        // @pest-mutate-ignore: TrueToFalse
        return \in_array($key, self::cpKeys(), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function payloadIsUnstructured(array $payload): bool
    {
        $mode = $payload['mode'] ?? null;

        return $mode === RoleTitleValue::MODE_UNSTRUCTURED;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $paths
     * @return list<string>
     */
    private static function stringListAt(array $payload, array $paths): array
    {
        $cur = $payload;

        foreach ($paths as $p) {
            if (! \is_array($cur) || ! \array_key_exists($p, $cur)) {
                return [];
            }

            $cur = $cur[$p];
        }

        if (! \is_array($cur) || ! \array_is_list($cur)) {
            return [];
        }

        $out = [];

        foreach ($cur as $v) {
            if (\is_string($v)) {
                $t = \trim($v);

                if ($t !== '') {
                    $out[] = $t;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $codes
     * @return list<array<string, mixed>>
     */
    private static function iscoRowsFromCodes(array $codes): array
    {
        $rows = [];

        foreach ($codes as $code) {
            $tiers = Isco08::tierStoragePayloadForCode($code) ?? Isco08FieldtypeStorage::emptyShape();
            $rows[] = self::replicatorRow('code', $tiers);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $strings
     * @return list<array<string, mixed>>
     */
    private static function textRowsFromStrings(array $strings): array
    {
        $rows = [];

        foreach ($strings as $s) {
            $rows[] = self::replicatorRow('code', $s);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $urls
     * @return list<array<string, mixed>>
     */
    private static function urlRowsFromStrings(array $urls): array
    {
        $rows = [];

        foreach ($urls as $u) {
            $rows[] = self::replicatorRow('url', $u);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>|string  $innerValue
     * @return array<string, mixed>
     */
    private static function replicatorRow(string $fieldHandle, array|string $innerValue): array
    {
        return [
            'type' => self::SET_HANDLE,
            'id' => (string) Str::uuid(),
            'enabled' => true,
            $fieldHandle => $innerValue,
        ];
    }

    /**
     * @return list<string>
     */
    private static function iscoCodesFromRows(mixed $rows): array
    {
        if (! \is_array($rows) || ! \array_is_list($rows)) {
            return [];
        }

        // `Isco_08::process()` reads no field config, so dropping the type entry changes nothing (RemoveArrayItem is equivalent)
        // @pest-mutate-ignore: RemoveArrayItem
        $field = new Field('code', ['type' => 'isco_08']);
        /** @var Isco_08 $isco */
        $isco = (new Isco_08)->setField($field);
        $out = [];

        foreach ($rows as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $inner = $row['code'] ?? null;

            if (! \is_array($inner)) {
                continue;
            }

            $processed = $isco->process($inner);
            $v = $processed['value'] ?? null;

            // `Isco_08::process()` omits `value` rather than storing '', so the empty-string guard never decides (EmptyStringToNotEmpty is equivalent)
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if (\is_string($v) && $v !== '') {
                $out[] = $v;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function onetCodesFromRows(mixed $rows): array
    {
        if (! \is_array($rows) || ! \array_is_list($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $inner = $row['code'] ?? null;

            if (\is_string($inner)) {
                $t = \trim($inner);

                if ($t !== '') {
                    $out[] = $t;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function urlsFromRows(mixed $rows): array
    {
        if (! \is_array($rows) || ! \array_is_list($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $inner = $row['url'] ?? null;

            if (\is_string($inner)) {
                $t = \trim($inner);

                if ($t !== '') {
                    $out[] = $t;
                }
            }
        }

        return $out;
    }
}
