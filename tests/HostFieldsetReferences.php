<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests;

/**
 * Reads which host fieldsets the add-on's blueprints and fieldsets reference by bare handle.
 *
 * A reference with the `aether-people::` namespace names one of the add-on's own fieldsets; a
 * bare one names a fieldset the host keeps (UAMS-Web/uams-statamic#281).
 */
final class HostFieldsetReferences
{
    /**
     * The host fieldsets referenced, each with the field handles read from it, sorted.
     *
     * A fieldset that is only imported whole maps to an empty list.
     *
     * @param  string  $root  The add-on's root directory.
     * @return array<string, list<string>> Field handles keyed by host fieldset handle.
     */
    public static function read(string $root): array
    {
        $files = [
            ...glob("{$root}/resources/fieldsets/*.yaml") ?: [],
            ...glob("{$root}/resources/collection-blueprints/*/*.yaml") ?: [],
        ];

        $references = [];
        foreach ($files as $file) {
            $contents = (string) file_get_contents($file);

            // A whole fieldset imported by its bare handle
            preg_match_all('/^\s+import: ([A-Za-z0-9_]+)\s*$/m', $contents, $imports);
            foreach ($imports[1] as $fieldset) {
                $references[$fieldset] ??= [];
            }

            // One field read from a fieldset by its bare handle
            preg_match_all('/^\s+field: ([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)\s*$/m', $contents, $fields, PREG_SET_ORDER);
            foreach ($fields as [, $fieldset, $handle]) {
                $references[$fieldset][] = $handle;
            }
        }

        // Sort the fieldsets and their handles so the stubs are stable
        ksort($references);

        return array_map(function (array $handles): array {
            $handles = array_values(array_unique($handles));
            sort($handles);

            return $handles;
        }, $references);
    }
}
