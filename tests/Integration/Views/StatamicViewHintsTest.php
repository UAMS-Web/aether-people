<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;
use UAMSWeb\Aether\People\ServiceProvider;

/**
 * Where the add-on's view directory lands in the `statamic` view namespace: after
 * a host's overrides, so a host copy still wins, and before Statamic's own views,
 * so the add-on's replace them (UAMS-Web/uams-statamic#281).
 *
 * Testbench's skeleton has no `resources/views/vendor/statamic`, so Laravel adds no
 * override hint at boot. These set the namespace by hand and call the helper again.
 *
 * @command  vendor/bin/pest tests/Integration/Views/StatamicViewHintsTest.php
 */

/**
 * Run the provider's helper over a given `statamic` namespace and view paths.
 *
 * @param  list<string>  $hints  The namespace's paths before the call.
 * @param  mixed  $viewPaths  The `view.paths` config value.
 * @return list<string> The namespace's paths after the call.
 */
function statamicViewHintsAfter(array $hints, mixed $viewPaths): array
{
    config(['view.paths' => $viewPaths]);
    View::replaceNamespace('statamic', $hints);

    $provider = new ServiceProvider(app());
    new ReflectionMethod($provider, 'addStatamicViewsAfterHostOverrides')->invoke($provider, '/addon/field-views');

    $finder = mustBe(FileViewFinder::class, View::getFinder(), 'view finder');

    /** @var list<string> */
    return array_values($finder->getHints()['statamic']);
}

it('puts the add-on views after the host overrides and before the rest', function (): void {
    expect(statamicViewHintsAfter(
        ['/host/views/vendor/statamic', '/core/views', '/other/views/vendor/statamic', '/later/views'],
        ['/host/views', 42, '/other/views'],
    ))->toBe([
        '/host/views/vendor/statamic',
        '/other/views/vendor/statamic',
        '/addon/field-views',
        '/core/views',
        '/later/views',
    ]);
});

it('puts the add-on views first when the host overrides nothing', function (): void {
    expect(statamicViewHintsAfter(['/core/views'], ['/host/views']))
        ->toBe(['/addon/field-views', '/core/views']);
});

it('skips a view path that is not a string and still finds the host override after it', function (): void {
    expect(statamicViewHintsAfter(['/core/views', '/host/views/vendor/statamic'], [42, '/host/views']))
        ->toBe(['/host/views/vendor/statamic', '/addon/field-views', '/core/views']);
});

it('reads no override directories from a view.paths that is not a list', function (): void {
    expect(statamicViewHintsAfter(['/host/views/vendor/statamic', '/core/views'], '/host/views'))
        ->toBe(['/addon/field-views', '/host/views/vendor/statamic', '/core/views']);
});

it('finds a host override whose view path does not resolve as written', function (): void {
    // A real directory reached through a `..` segment: Laravel stores the hint
    // resolved, so the override has to be resolved before it is compared
    $root = sys_get_temp_dir().'/aether-people-hints-'.bin2hex(random_bytes(4));
    mkdir($root.'/views/vendor/statamic', 0777, true);

    try {
        $override = mustBeString(realpath($root.'/views/vendor/statamic'), 'override');
        $hints = statamicViewHintsAfter([$root.'/views/vendor/statamic', '/core/views'], [$root.'/views/../views']);

        // A Laravel release that stores hints as given keeps the unresolved path, so compare it resolved
        expect(array_map(static fn (string $hint): string => realpath($hint) ?: $hint, $hints))
            ->toBe([$override, '/addon/field-views', '/core/views']);
    } finally {
        rmdir($root.'/views/vendor/statamic');
        rmdir($root.'/views/vendor');
        rmdir($root.'/views');
        rmdir($root);
    }
});

it('finds a host override that a Laravel release stored unresolved', function (): void {
    // Older Laravel releases keep a hint as given, so plant the `..` path in the finder directly
    $root = sys_get_temp_dir().'/aether-people-hints-'.bin2hex(random_bytes(4));
    mkdir($root.'/views/vendor/statamic', 0777, true);

    try {
        config(['view.paths' => [$root.'/views']]);
        $finder = mustBe(FileViewFinder::class, View::getFinder(), 'view finder');
        $hints = new ReflectionProperty(FileViewFinder::class, 'hints');
        $hints->setValue($finder, [...$finder->getHints(), 'statamic' => [$root.'/views/../views/vendor/statamic', '/core/views']]);

        $provider = new ServiceProvider(app());
        new ReflectionMethod($provider, 'addStatamicViewsAfterHostOverrides')->invoke($provider, '/addon/field-views');

        /** @var list<string> $after */
        $after = array_values(mustBe(FileViewFinder::class, View::getFinder(), 'view finder')->getHints()['statamic']);

        expect(array_map(static fn (string $hint): string => realpath($hint) ?: $hint, $after))->toBe([mustBeString(realpath($root.'/views/vendor/statamic'), 'override'), '/addon/field-views', '/core/views']);
    } finally {
        rmdir($root.'/views/vendor/statamic');
        rmdir($root.'/views/vendor');
        rmdir($root.'/views');
        rmdir($root);
    }
});
