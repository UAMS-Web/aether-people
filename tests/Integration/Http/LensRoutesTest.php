<?php

declare(strict_types=1);

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Site;
use Statamic\Sites\Sites;
use UAMSWeb\Aether\People\Http\Controllers\PeopleLensController;
use UAMSWeb\Aether\People\ServiceProvider;

covers(ServiceProvider::class);

/**
 * Each configured lens is routed under every site it is active on, ahead of Statamic's catch-all
 * (UAMS-Web/uams-statamic-archive#1635 and UAMS-Web/uams-statamic-archive#1636).
 *
 * @command vendor/bin/pest tests/Integration/Http/LensRoutesTest.php
 */

/**
 * The lens routes now registered, as `uri => lens handle`.
 *
 * @return array<string, string>
 */
function registeredLensRoutes(): array
{
    $found = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if ($route->getActionName() === PeopleLensController::class) {
            $lens = $route->defaults['lens'] ?? '';
            $found[$route->uri()] = \is_string($lens) ? $lens : '';
        }
    }

    return $found;
}

/**
 * Register the lens routes again, as the provider does while Statamic boots.
 */
function registerLensRoutesNow(): void
{
    $provider = mustBe(ServiceProvider::class, app()->getProvider(ServiceProvider::class), 'the add-on provider');
    $method = new ReflectionMethod($provider, 'registerLensRoutes');
    $method->invoke($provider);
}

it('routes the every-site lenses at the root prefix on a single site, and not the clinical lens', function (): void {
    expect(registeredLensRoutes())->toBe([
        'faculty/{slug}' => 'academic',
        'staff/{slug}' => 'institutional',
        'researcher/{slug}' => 'researcher',
    ]);
});

it('routes each lens under the prefix of every site it is active on', function (): void {
    Site::setSites([
        'default' => ['name' => 'Default', 'locale' => 'en_US', 'url' => '/'],
        'health' => ['name' => 'Health', 'locale' => 'en_US', 'url' => '/health/'],
    ]);
    Route::getRoutes()->refreshNameLookups();

    $before = array_keys(registeredLensRoutes());
    registerLensRoutesNow();
    $added = array_values(array_diff(array_keys(registeredLensRoutes()), $before));

    expect($added)->toContain('health/provider/{slug}')
        ->and($added)->toContain('health/faculty/{slug}')
        ->and($added)->toContain('health/researcher/{slug}');
});

it('serves each lens route through the front-end middleware with its handle as a default', function (): void {
    $routes = array_filter(
        Route::getRoutes()->getRoutes(),
        static fn (LaravelRoute $route): bool => $route->getActionName() === PeopleLensController::class,
    );

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('statamic.web')
            ->and($route->defaults['lens'])->toBeString()->not->toBe('')
            ->and($route->methods())->toContain('GET');
    }
});

it('skips a site that is not a site, or has a handle or URL that is not a string, and routes the rest', function (): void {
    $good = new Statamic\Sites\Site('health', ['name' => 'Health', 'locale' => 'en_US', 'url' => '/health/']);
    $badHandle = new class extends Statamic\Sites\Site
    {
        public function __construct()
        {
            parent::__construct('bad', ['name' => 'Bad', 'locale' => 'en_US', 'url' => '/bad/']);
        }

        /**
         * @return mixed
         */
        #[Override]
        public function handle()
        {
            return 7;
        }
    };
    $badUrl = new class extends Statamic\Sites\Site
    {
        public function __construct()
        {
            parent::__construct('worse', ['name' => 'Worse', 'locale' => 'en_US', 'url' => '/worse/']);
        }

        /**
         * @return mixed
         */
        #[Override]
        public function url()
        {
            return null;
        }
    };
    // The malformed ones come first, so a loop that stopped at one would never reach the good site
    $sites = new class([$badHandle, $badUrl, 'not a site', $good]) extends Sites
    {
        /**
         * @param  list<mixed>  $fakeAll
         */
        public function __construct(private readonly array $fakeAll)
        {
            parent::__construct();
        }

        /**
         * @return mixed
         */
        #[Override]
        public function all()
        {
            return $this->fakeAll;
        }
    };
    app()->instance(Sites::class, $sites);

    $before = array_keys(registeredLensRoutes());
    registerLensRoutesNow();
    $added = array_values(array_diff(array_keys(registeredLensRoutes()), $before));

    expect($added)->toContain('health/provider/{slug}')
        ->and($added)->not->toContain('bad/provider/{slug}')
        ->and($added)->not->toContain('worse/faculty/{slug}')
        ->and(array_filter($added, static fn (string $uri): bool => str_starts_with($uri, 'bad/') || str_starts_with($uri, 'worse/')))->toBe([]);
});

it('routes a lens once under a prefix two sites share', function (): void {
    $first = new Statamic\Sites\Site('one', ['name' => 'One', 'locale' => 'en_US', 'url' => '/shared/']);
    $second = new Statamic\Sites\Site('two', ['name' => 'Two', 'locale' => 'en_US', 'url' => '/shared/']);
    $sites = new class([$first, $second]) extends Sites
    {
        /**
         * @param  list<Statamic\Sites\Site>  $fakeAll
         */
        public function __construct(private readonly array $fakeAll)
        {
            parent::__construct();
        }

        /**
         * @return mixed
         */
        #[Override]
        public function all()
        {
            return $this->fakeAll;
        }
    };
    app()->instance(Sites::class, $sites);

    $count = static fn (): int => count(array_filter(
        Route::getRoutes()->getRoutes(),
        static fn (LaravelRoute $route): bool => $route->uri() === 'shared/faculty/{slug}',
    ));

    registerLensRoutesNow();

    expect($count())->toBe(1);
});

it('registers the lens URL tag', function (): void {
    expect(app('statamic.tags')->has('people_lens_url'))->toBeTrue();
});
