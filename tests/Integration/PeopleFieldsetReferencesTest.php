<?php

declare(strict_types=1);

use Statamic\Facades\YAML;
use Statamic\Fields\FieldRepository;
use Statamic\Fields\FieldsetRepository;
use UAMSWeb\Aether\People\Tests\HostFieldsetReferences;

/**
 * The add-on's fieldset field references resolve, and the host stand-ins match them (UAMS-Web/uams-statamic#281).
 *
 * Split from `PeopleBlueprintsTest` so no one class holds most of the suite's run time.
 *
 * @command  vendor/bin/pest tests/Integration/PeopleFieldsetReferencesTest.php
 */
beforeEach(function (): void {
    // Read host fieldsets from the stand-ins, as the host would from its own directory
    app(FieldsetRepository::class)->setDirectory(__DIR__.'/../Fixtures/HostFieldsets');
});

it('resolves every field the add-on files take from an aether-people fieldset', function (string $reference): void {
    expect(app(FieldRepository::class)->find($reference))->not->toBeNull();
})->with(function (): array {
    // Collect every namespaced field reference in the add-on's fieldsets and blueprints
    $files = [
        ...glob(__DIR__.'/../../resources/fieldsets/*.yaml') ?: [],
        ...glob(__DIR__.'/../../resources/collection-blueprints/*/*.yaml') ?: [],
    ];
    $references = [];
    foreach ($files as $file) {
        preg_match_all('/^\s+field: (aether-people::[A-Za-z0-9_]+\.[A-Za-z0-9_]+)\s*$/m', (string) file_get_contents($file), $matches);
        $references = [...$references, ...$matches[1]];
    }

    return array_values(array_unique($references));
});

it('finds no field a namespaced fieldset lacks, so the check above can fail', function (): void {
    expect(app(FieldRepository::class)->find('aether-people::uamsweb_common_people_name.not_a_field'))->toBeNull()
        ->and(app(FieldRepository::class)->find('aether-people::not_a_fieldset.first_name'))->toBeNull();
});

it('has a host stand-in for exactly the host fields the add-on references', function (): void {
    // Read the stand-ins back as fieldset => field handles
    $stubs = [];
    foreach (glob(__DIR__.'/../Fixtures/HostFieldsets/*.yaml') ?: [] as $path) {
        $fields = YAML::file($path)->parse()['fields'] ?? [];
        $stubs[basename($path, '.yaml')] = array_column(is_array($fields) ? $fields : [], 'handle');
    }

    ksort($stubs);

    expect($stubs)->toBe(HostFieldsetReferences::read(dirname(__DIR__, 2)));
});
