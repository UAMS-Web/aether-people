<?php

declare(strict_types=1);

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Permission;
use UAMSWeb\Aether\People\Http\Controllers\MergeController;
use UAMSWeb\Aether\People\Merge\PeopleMergeRegistrar;

covers(PeopleMergeRegistrar::class);

/**
 * The Control Panel route registered under a name, or null when nothing carries it.
 */
function mergeRegistrarRoute(string $name): ?LaravelRoute
{
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if ($route->getName() === $name) {
            return $route;
        }
    }

    return null;
}

it('registers the merge permission under the people group', function (): void {
    $permission = null;

    foreach (Permission::all() as $candidate) {
        if ($candidate instanceof Statamic\Auth\Permission && $candidate->value() === 'merge people entries') {
            $permission = $candidate;
        }
    }

    expect($permission)->not->toBeNull()
        ->and($permission?->label())->toBe('Merge duplicate people entries')
        ->and($permission?->group())->toBe('people');
});

it('registers the reconciliation, preview and commit routes behind their controller actions', function (string $name, string $method, string $uri, string $action): void {
    $route = mergeRegistrarRoute($name);

    expect($route)->not->toBeNull()
        ->and($route?->methods())->toContain($method)
        ->and($route?->uri() !== null && str_ends_with($route->uri(), $uri))->toBeTrue()
        ->and($route?->getActionName())->toBe(MergeController::class.'@'.$action);
})->with([
    'show' => ['statamic.cp.people-merge.show', 'GET', 'people/merge', 'show'],
    'preview' => ['statamic.cp.people-merge.preview', 'POST', 'people/merge/preview', 'preview'],
    'commit' => ['statamic.cp.people-merge.commit', 'POST', 'people/merge/commit', 'commit'],
]);
