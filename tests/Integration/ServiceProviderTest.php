<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;
use Statamic\Facades\Addon;
use Statamic\Facades\Blueprint;
use Statamic\Policies\EntryPolicy;
use Statamic\Widgets\Widget;
use UAMSWeb\Aether\People\Policies\ScopedEntryPolicy;
use UAMSWeb\Aether\People\ServiceProvider;
use UAMSWeb\Aether\People\Widgets\WorkdayGenderOverrides;

covers(ServiceProvider::class);

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

it('fills a missing provider eligibility config while it registers', function (string $key): void {
    // Drop the merged config, then register the provider again
    config([$key => []]);
    new ServiceProvider(app())->register();

    // Expect the add-on's own file to supply it again
    $defaults = require __DIR__.'/../../config/'.$key.'.php';

    expect(config($key))->toBe($defaults)
        ->and($defaults)->not->toBeEmpty();
})->with(['clinical_provider_profile', 'researcher_profile', 'find_a_doc_api']);

it('leaves a provider eligibility config value the host has set', function (string $key): void {
    // Set the host's own value, then register the provider again
    config(["{$key}.host_marker" => 'kept']);
    new ServiceProvider(app())->register();

    expect(config("{$key}.host_marker"))->toBe('kept');
})->with(['clinical_provider_profile', 'researcher_profile', 'find_a_doc_api']);

it('serves each collection blueprint directory under the collection namespace', function (string $collection): void {
    // Read the blueprints the namespace serves
    $blueprints = Blueprint::in("collections.{$collection}");

    expect($blueprints)->not->toBeEmpty();
})->with(ServiceProvider::COLLECTION_BLUEPRINT_DIRECTORIES);

it('adds the field views, search views and templates to the view finder', function (): void {
    $finder = mustBe(FileViewFinder::class, View::getFinder(), 'the view finder');
    $realPaths = fn (array $paths): array => array_map(realpath(...), array_values(array_filter($paths, is_string(...))));
    $hints = $finder->getHints();

    expect($realPaths($hints['statamic'] ?? []))->toContain(realpath(__DIR__.'/../../resources/field-views'))
        ->and($realPaths($hints['aether-search'] ?? []))->toContain(realpath(__DIR__.'/../../resources/search-views'))
        ->and($realPaths($finder->getPaths()))->toContain(realpath(__DIR__.'/../../resources/views'));
});

/**
 * Register the Find-a-Doc routes again under a given rate-limit config, and return the middleware of the
 * route the second registration added. The provider boots once per application, so the config the
 * registration reads is set first and the private method is called directly.
 *
 * @param  bool  $configured  Whether the config key exists; false removes it so the default applies.
 * @return list<string>
 */
function findADocRouteMiddleware(mixed $rateLimit, bool $configured = true): array
{
    $all = config()->all();
    Arr::forget($all, 'find_a_doc_api.rate_limit_per_minute');
    config()->set('find_a_doc_api', $all['find_a_doc_api']);

    if ($configured) {
        config()->set('find_a_doc_api.rate_limit_per_minute', $rateLimit);
    }

    $provider = mustBe(ServiceProvider::class, app()->getProvider(ServiceProvider::class), 'the add-on provider');
    $method = new ReflectionMethod($provider, 'registerFindADocApiRoutes');
    $method->invoke($provider);

    $matches = array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route): bool => $route->uri() === 'api/v1/find-a-doc/providers',
    ));

    $route = end($matches);

    return $route instanceof RoutingRoute ? array_values(array_map(strval(...), $route->middleware())) : [];
}

it('throttles the Find-a-Doc routes at the configured rate under the api middleware', function (): void {
    expect(findADocRouteMiddleware(120))->toBe(['api', 'throttle:120,1']);
});

it('throttles the Find-a-Doc routes at 60 a minute when the rate is not configured', function (): void {
    expect(findADocRouteMiddleware(null, configured: false))->toBe(['api', 'throttle:60,1']);
});

it('throttles the Find-a-Doc routes at 60 a minute when the configured rate is not an integer', function (): void {
    expect(findADocRouteMiddleware('fast'))->toBe(['api', 'throttle:60,1']);
});

it("limits the entry policy to the editor's organizations", function (): void {
    expect(app(EntryPolicy::class))->toBeInstanceOf(ScopedEntryPolicy::class);
});

it('registers the gender override widget', function (): void {
    $extensions = mustBe(ArrayAccess::class, app('statamic.extensions'), 'the extensions');
    $widgets = mustBe(Collection::class, $extensions[Widget::class], 'the registered widgets');

    expect($widgets->get('workday_gender_overrides'))->toBe(WorkdayGenderOverrides::class);
});

it('boots the editing scope, Workday and people merge registrars', function (): void {
    // The registrars' own tests cover what each registers; here the provider's call to each shows as its effect
    expect(Validator::make(['organization' => 'org-none'], ['organization' => 'not_top_level_organization'])->passes())->toBeTrue()
        ->and(cp_route('people-workday-gender.confirm', ['id' => 'x']))->toEndWith('/people/workday-gender/x/confirm')
        ->and(cp_route('people-merge.show'))->toEndWith('/people/merge');
});
