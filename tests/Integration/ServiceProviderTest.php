<?php

declare(strict_types=1);

use Statamic\Facades\Addon;
use UAMSWeb\Aether\People\ServiceProvider;

/**
 * The add-on boots inside a minimal Statamic.
 *
 * @command  vendor/bin/pest tests/Integration/ServiceProviderTest.php
 */
it('is discovered by Statamic as an add-on', function (): void {
    // Expect the add-on among those Statamic booted
    expect(Addon::all()->map->package()->all())->toContain('uams-web/aether-people');
});

it('publishes its config file under the tag the README names', function (): void {
    // Read the paths published under the config tag
    $paths = ServiceProvider::pathsToPublish(null, 'aether-people-config');

    // Expect the package config to publish to the app's config directory
    expect(array_map(realpath(...), array_keys($paths)))->toBe([realpath(__DIR__.'/../../config/aether-people.php')])
        ->and(array_values($paths))->toBe([config_path('aether-people.php')]);
});

it('builds the Control Panel assets from the files vite.config.js names', function (): void {
    // Read the Vite settings the provider hands to Statamic
    $vite = new ReflectionProperty(ServiceProvider::class, 'vite')
        ->getValue(new ServiceProvider(app()));

    // Expect the entry points and the output directory to match the committed build
    expect($vite)->toBe([
        'input' => ['resources/js/cp.js', 'resources/css/cp.css'],
        'publicDirectory' => 'resources/dist',
    ]);
});

it('merges the people config under the key the code reads', function (): void {
    expect(config('people.collection'))->toBe('people');
});

it('fills a missing people config while it registers', function (): void {
    // Drop the merged config, then register the provider again
    config(['people' => []]);
    new ServiceProvider(app())->register();

    expect(config('people.collection'))->toBe('people');
});

it('leaves a people config the host has set', function (): void {
    // Set the host's own value, then register the provider again
    config(['people.collection' => 'staff']);
    new ServiceProvider(app())->register();

    expect(config('people.collection'))->toBe('staff');
});
