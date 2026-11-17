<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use UAMSWeb\Aether\People\Http\Controllers\Api\V1\FindADoc\ProviderIndexController;
use UAMSWeb\Aether\People\Http\Controllers\Api\V1\FindADoc\ProviderShowController;
use UAMSWeb\Aether\People\Tests\Support\DefinesGreedyHostRoute;

/**
 * The Find-a-Doc read API's routes match ahead of a host's greedy front-end route
 * (UAMS-Web/aether-people#116).
 *
 * The host defines `{entry}/{section}` before the add-on boots, as `uams-statamic` does for its
 * Area-of-Expertise sections, and that route also matches the index path. Laravel takes the first
 * route that matches, so the API routes must be registered before it.
 *
 * @command  vendor/bin/pest tests/Integration/Api/FindADocRouteOrderTest.php
 */
uses(DefinesGreedyHostRoute::class);

/**
 * The route the router matches for a GET of a path.
 */
function findADocMatchedRoute(string $path): Route
{
    $router = app(Router::class);

    return $router->getRoutes()->match(Request::create($path, 'GET'));
}

it("really is shadowed by the host's route in this host, which is what the tests below guard against", function (): void {
    $host = app(Router::class)->getRoutes()->getByName('host.section');

    expect($host)->toBeInstanceOf(Route::class)
        ->and($host?->matches(Request::create('/api/v1/find-a-doc/providers', 'GET')))->toBeTrue();
});

it('matches the index path to the index controller, ahead of the host route', function (): void {
    expect(findADocMatchedRoute('/api/v1/find-a-doc/providers')->getActionName())->toBe(ProviderIndexController::class);
});

it('keeps matching the show path to the show controller', function (): void {
    $route = findADocMatchedRoute('/api/v1/find-a-doc/providers/abc');

    expect($route->getActionName())->toBe(ProviderShowController::class)
        ->and($route->parameter('provider'))->toBe('abc');
});

it("leaves the host's own route reachable for a path that is not the API's", function (): void {
    expect(findADocMatchedRoute('/some/overview')->getName())->toBe('host.section');
});
