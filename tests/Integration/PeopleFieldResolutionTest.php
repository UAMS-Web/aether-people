<?php

declare(strict_types=1);

use Statamic\Facades\Fieldset;
use Statamic\Facades\YAML;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\FieldsetRepository;

/**
 * Every field of the add-on's fieldsets and collection blueprint files resolves (UAMS-Web/uams-statamic#281).
 *
 * Split from `PeopleBlueprintsTest` so no one class holds most of the suite's run time.
 *
 * @command  vendor/bin/pest tests/Integration/PeopleFieldResolutionTest.php
 */
beforeEach(function (): void {
    // Read host fieldsets from the stand-ins, as the host would from its own directory
    app(FieldsetRepository::class)->setDirectory(__DIR__.'/../Fixtures/HostFieldsets');
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
