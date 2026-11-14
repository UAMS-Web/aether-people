<?php

declare(strict_types=1);

use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Fieldset;
use Statamic\Facades\YAML;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\FieldRepository;
use Statamic\Fields\FieldsetRepository;
use UAMSWeb\Aether\People\ServiceProvider;
use UAMSWeb\Aether\People\Tests\HostFieldsetReferences;

/**
 * The people blueprints and person-only fieldsets are served from the add-on (UAMS-Web/uams-statamic#281).
 *
 * The collection blueprints keep their handles. The fieldsets are served as
 * `aether-people::<handle>`, the namespace Statamic gives an add-on's
 * `resources/fieldsets/`, and the blueprints and fieldsets import them by that
 * name; a fieldset the host keeps is imported by its bare handle.
 *
 * The host's own fieldsets are stood in for by `tests/Fixtures/HostFieldsets/`, which
 * `scripts/generate-host-fieldset-stubs.php` writes from the add-on's references.
 *
 * @command  vendor/bin/pest tests/Integration/PeopleBlueprintsTest.php
 */
beforeEach(function (): void {
    // Read host fieldsets from the stand-ins, as the host would from its own directory
    app(FieldsetRepository::class)->setDirectory(__DIR__.'/../Fixtures/HostFieldsets');
});

it("serves each collection blueprint under the collection's own namespace", function (string $collection, string $blueprint): void {
    // Make the collection, as the host's content does
    Collection::make($collection)->save();

    // List the blueprints the collection finds
    $listed = Collection::findByHandle($collection)?->entryBlueprints();
    if (! $listed instanceof Illuminate\Support\Collection) {
        throw new LogicException("The {$collection} collection listed no blueprints.");
    }

    expect($listed->map(fn (mixed $found): ?string => $found instanceof StatamicBlueprint ? $found->handle() : null)->all())->toBe([$blueprint])
        ->and(Blueprint::in("collections/{$collection}")->keys()->all())->toBe([$blueprint]);
})->with([
    'people' => ['people', 'person'],
    'people groups' => ['people_groups', 'people_group'],
]);

it('reads each collection blueprint from the add-on directory', function (string $collection): void {
    // Read the directory registered for the collection
    $registered = Blueprint::getAdditionalNamespaces()->get("collections.{$collection}");
    if (! is_string($registered)) {
        throw new LogicException("No directory is registered for the {$collection} collection.");
    }

    expect(realpath($registered))->toBe(realpath(__DIR__."/../../resources/collection-blueprints/{$collection}"))
        ->and(ServiceProvider::collectionBlueprintPath($collection))->toBe($registered);
})->with(ServiceProvider::COLLECTION_BLUEPRINT_DIRECTORIES);

it('serves every person-only fieldset under the add-on namespace', function (string $handle): void {
    expect(Fieldset::find("aether-people::{$handle}"))->not->toBeNull()
        ->and(Fieldset::find($handle))->toBeNull();
})->with(fn (): array => array_map(
    fn (string $path): string => basename($path, '.yaml'),
    glob(__DIR__.'/../../resources/fieldsets/*.yaml') ?: [],
));

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

it('resolves every field of each add-on fieldset', function (string $handle): void {
    expect(Fieldset::find("aether-people::{$handle}")?->fields()->all()->count())->toBeGreaterThan(0);
})->with(fn (): array => array_map(
    fn (string $path): string => basename($path, '.yaml'),
    glob(__DIR__.'/../../resources/fieldsets/*.yaml') ?: [],
));

it('resolves every field of each collection blueprint file', function (string $path): void {
    // Build the blueprint from its file, independent of how it is served
    $blueprint = new StatamicBlueprint;
    $blueprint->setHandle(basename($path, '.yaml'));
    $blueprint->setContents(YAML::file($path)->parse());

    expect($blueprint->fields()->all()->count())->toBeGreaterThan(0);
})->with(fn (): array => glob(__DIR__.'/../../resources/collection-blueprints/*/*.yaml') ?: []);
