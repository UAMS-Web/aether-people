<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\View\FileViewFinder;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Fields\FieldsetRepository;
use Statamic\Http\Controllers\FrontendController;
use UAMSWeb\Aether\People\Http\Controllers\PeopleLensController;
use UAMSWeb\Aether\People\ServiceProvider;
use UAMSWeb\Aether\People\Tests\Fixtures\HostStandIns\CspNonceStandIn;
use UAMSWeb\Aether\People\Tests\Fixtures\HostStandIns\SanitizeUrlStandIn;

uses(MockeryPHPUnitIntegration::class);

covers(PeopleLensController::class);

/**
 * The clinical provider lens route end to end (UAMS-Web/uams-statamic-archive#571, UAMS-Web/uams-statamic-archive#1635 and
 * UAMS-Web/uams-statamic-archive#1636), ported from the site's `PeopleLensRouteTest`.
 *
 * The route cases prove the `/health/provider/{slug}` lens route captures the clinical lens URL, only on the
 * `health` site, ahead of Statamic's catch-all and without shadowing the plural `/health/providers` directory.
 * The render cases resolve the canonical person on the default site and render it through the clinical
 * template, and 404 an ineligible or unknown slug. The generic lens controller cases are in
 * `PeopleLensRenderTest`, and the route registration cases in `LensRoutesTest`.
 *
 * @command  vendor/bin/pest tests/Integration/Http/ProviderLensRouteTest.php
 */
beforeEach(function (): void {
    // Save the entries under a directory of this test's own, so no worker reads another's
    $directory = providerLensStacheDirectory(fresh: true);
    Stache::stores()->each(fn ($store) => $store->directory($directory.'/'.$store->key()));

    // The host's partials, modifier and tag the clinical template calls, stood in by the fixtures
    app('view')->addLocation(dirname(__DIR__, 2).'/Fixtures/HostPartials');
    mustBe(FileViewFinder::class, app('view')->getFinder(), 'the view finder')->prependLocation(dirname(__DIR__, 2).'/Fixtures/HostPartialsRendered');
    app('statamic.modifiers')->put('sanitize_url', SanitizeUrlStandIn::class);
    app('statamic.tags')->put('csp_nonce', CspNonceStandIn::class);

    // Read the host's fieldsets, which the person blueprint imports, from the stand-ins
    app(FieldsetRepository::class)->setDirectory(dirname(__DIR__, 2).'/Fixtures/HostFieldsets');

    // The clinical lens is active on the health site only: the canonical people live on the default site
    Site::setSites([
        'default' => ['name' => 'Default', 'locale' => 'en_US', 'url' => '/'],
        'health' => ['name' => 'Health', 'locale' => 'en_US', 'url' => '/health/'],
    ]);
    $provider = mustBe(ServiceProvider::class, app()->getProvider(ServiceProvider::class), 'the add-on provider');
    new ReflectionMethod($provider, 'registerLensRoutes')->invoke($provider);

    // Boot registers the lens routes ahead of Statamic's catch-all; routes added now come after it, so put
    // the lens routes first again to match as they do at boot
    $routes = RouteFacade::getRoutes()->getRoutes();
    $isLens = static fn (Route $route): bool => $route->getActionName() === PeopleLensController::class;
    $ordered = new RouteCollection;
    foreach ([...array_filter($routes, $isLens), ...array_filter($routes, static fn (Route $route): bool => ! $isLens($route))] as $route) {
        $ordered->add($route);
    }

    $ordered->refreshNameLookups();
    $ordered->refreshActionLookups();

    app('router')->setRoutes($ordered);

    $people = Collection::make('people');
    $people->title('People');
    $people->sites(['default']);
    $people->save();
});

afterEach(function (): void {
    app('files')->deleteDirectory(providerLensStacheDirectory());
});

/**
 * The directory this test saves entries under, made new when asked.
 */
function providerLensStacheDirectory(bool $fresh = false): string
{
    static $directory = '';

    if ($fresh || $directory === '') {
        $directory = sys_get_temp_dir().'/provider-lens-'.uniqid('', true);
    }

    return $directory;
}

/**
 * The route Laravel matches for a GET path.
 */
function providerLensMatchedRoute(string $path): Route
{
    return app('router')->getRoutes()->match(Request::create($path, 'GET'));
}

/**
 * Persist a published location every eligible person links to.
 */
function providerLensLocation(): string
{
    $collection = Collection::make('clinical_locations');
    $collection->title('Clinical Locations');
    $collection->save();

    $location = new Entry;
    $location->collection('clinical_locations');
    $location->id('lens-location');
    $location->slug('lens-location');
    $location->published(true);
    $location->data(['title' => 'Sickbay']);
    $location->saveQuietly();

    return 'lens-location';
}

/**
 * Persist a published person on the default site, eligible for a clinical profile unless a field says otherwise.
 *
 * @param  array<string, mixed>  $fields  Overrides and extra fields.
 */
function providerLensPerson(string $slug, array $fields = []): Entry
{
    $entry = new Entry;
    $entry->collection('people');
    $entry->locale('default');
    $entry->id('lens-'.$slug);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data([
        'title' => 'Leonard McCoy',
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_employee_subtype' => 'regular',
        'resident_query' => false,
        'fellow_query' => false,
        'clinical_role_query' => true,
        'direct_patient_care_query' => true,
        'credentials' => ['md'],
        // The host's language select augments to rows; the stand-in field is a text field, so none are set
        'language' => [],
        'clinical_locations' => [providerLensLocation()],
        ...$fields,
    ]);
    $entry->saveQuietly();

    return $entry;
}

it('captures the clinical provider lens URL on the health site prefix', function (): void {
    $route = providerLensMatchedRoute('/health/provider/some-slug');

    expect($route->getActionName())->toContain('PeopleLensController')
        ->and($route->defaults['lens'] ?? null)->toBe('provider');
});

it('routes a lens URL ahead of the Statamic catch-all as registered at boot', function (): void {
    // The faculty lens is routed at boot on the single test site, so this is the real precedence
    expect(providerLensMatchedRoute('/faculty/some-slug')->getActionName())->toContain('PeopleLensController');
});

it('does not register the provider lens on the canonical default site', function (): void {
    // The provider lens is health-only, so `/provider/{slug}` at the root must not reach the lens controller
    expect(providerLensMatchedRoute('/provider/some-slug')->getActionName())->not->toContain('PeopleLensController');
});

it('does not shadow the plural /health/providers directory mount', function (): void {
    // Singular `provider/{slug}` (two trailing segments) cannot match the single `providers` mount segment
    expect(providerLensMatchedRoute('/health/providers')->getActionName())->not->toContain('PeopleLensController');
});

it('renders an eligible provider through the clinical lens template', function (): void {
    providerLensPerson('leonard-mccoy');

    $response = $this->get('/health/provider/leonard-mccoy');

    // 200 through the clinical template, not the base `people/show`: the lens body stamps
    // `data-lens="provider"`, which the base profile never emits (UAMS-Web/uams-statamic-archive#571)
    $response->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    $html = mustBeString($response->getContent(), 'the lens page');

    expect($html)->toContain('data-lens="provider"')
        // Inside the layout, with the person's own content in the cascade
        ->and($html)->toContain('<html>')
        ->and($html)->toContain('class="clinical-provider-profile"')
        ->and($html)->toContain('Leonard McCoy');
});

it('404s the clinical lens for an ineligible person', function (): void {
    providerLensPerson('pavel-chekov', ['resident_query' => true]);

    $this->get('/health/provider/pavel-chekov')->assertNotFound();
});

it('404s the clinical lens for a person with no published location', function (): void {
    providerLensPerson('hikaru-sulu', ['clinical_locations' => []]);

    $this->get('/health/provider/hikaru-sulu')->assertNotFound();
});

it('404s a health provider URL with no canonical person', function (): void {
    $this->get('/health/provider/no-such-provider-xyz-123')->assertNotFound();
});

it('hands a request with no lens on its route to the front end, even for a renderable person', function (): void {
    providerLensPerson('leonard-mccoy');

    $frontEnd = Mockery::mock(FrontendController::class);
    $frontEnd->shouldReceive('index')->once()->andReturn('the front end');
    app()->instance(FrontendController::class, $frontEnd);

    // A request that matched no route carries no lens
    $response = app(PeopleLensController::class)(Request::create('/somewhere'), 'leonard-mccoy');

    expect($response)->toBe('the front end');
});
