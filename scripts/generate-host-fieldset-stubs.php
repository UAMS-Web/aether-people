<?php

declare(strict_types=1);

/**
 * Write the stand-ins for the host fieldsets the add-on's blueprints and fieldsets import.
 *
 * The add-on's YAML imports fieldsets the host site keeps (`uamsweb_common` and others) by their
 * bare handles (UAMS-Web/uams-statamic#281). Statamic resolves a fieldset's whole field list when
 * any one field is read, so the add-on's tests need those host fieldsets to exist. This writes one
 * stub per host fieldset into `tests/Fixtures/HostFieldsets/`, holding a `text` field for each
 * field handle the add-on references from it, and no fields for a fieldset it only imports whole.
 *
 * Run it after changing a reference to a host fieldset; `PeopleBlueprintsTest` fails until the
 * stubs match the references again.
 *
 * @command  php scripts/generate-host-fieldset-stubs.php
 */

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use UAMSWeb\Aether\People\Tests\HostFieldsetReferences;

$root = dirname(__DIR__);
$target = "{$root}/tests/Fixtures/HostFieldsets";

// Start from an empty fixture directory, so a fieldset no longer referenced goes away
if (is_dir($target)) {
    foreach (glob("{$target}/*.yaml") ?: [] as $stale) {
        unlink($stale);
    }
} elseif (! mkdir($target, 0o777, true) && ! is_dir($target)) {
    fwrite(STDERR, "Could not create {$target}\n");
    exit(1);
}

// Write one stub per host fieldset, with its referenced fields as text fields
$written = 0;
foreach (HostFieldsetReferences::read($root) as $fieldset => $handles) {
    $stub = [
        'title' => "{$fieldset} (stand-in for the host fieldset)",
        'fields' => array_map(
            fn (string $handle): array => ['handle' => $handle, 'field' => ['type' => 'text']],
            $handles,
        ),
    ];
    file_put_contents("{$target}/{$fieldset}.yaml", Yaml::dump($stub, 4, 2));
    $written++;
}

fwrite(STDOUT, "Wrote {$written} host fieldset stubs to tests/Fixtures/HostFieldsets\n");
