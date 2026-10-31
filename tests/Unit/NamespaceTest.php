<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\ServiceProvider;

/**
 * The add-on owns the `UAMSWeb\Aether\People` prefix, mapped to `src/`. A
 * companion add-on nests its namespace inside that prefix, so `src/` must not
 * hold a directory of the same name.
 *
 * @command  vendor/bin/pest tests/Unit/NamespaceTest.php
 */
it('autoloads its classes from src/ under the UAMSWeb\Aether\People prefix', function (): void {
    // Resolve the file Composer's autoloader maps the provider class to
    $file = new ReflectionClass(ServiceProvider::class)->getFileName();

    // Expect the provider in the namespace, in src/
    expect(ServiceProvider::class)->toStartWith('UAMSWeb\Aether\People\\')
        ->and($file)->toBe(realpath(dirname(__DIR__, 2).'/src/ServiceProvider.php'));
});

it('maps the prefix and its test prefix in composer.json', function (): void {
    // Decode the manifest
    /** @var array{autoload: array{'psr-4': array<string, string>}, 'autoload-dev': array{'psr-4': array<string, string>}} $manifest */
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    // Expect each prefix on its directory
    expect($manifest['autoload']['psr-4'])->toBe(['UAMSWeb\Aether\People\\' => 'src/'])
        ->and($manifest['autoload-dev']['psr-4'])->toBe(['UAMSWeb\Aether\People\Tests\\' => 'tests/']);
});

it('leaves the ListingsManagement namespace to the companion add-on', function (): void {
    // Expect no src/ListingsManagement directory
    expect(dirname(__DIR__, 2).'/src/ListingsManagement')->not->toBeDirectory();
});
