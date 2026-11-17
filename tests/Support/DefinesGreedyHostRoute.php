<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests\Support;

use Illuminate\Routing\Router;

/**
 * Gives a test's host a greedy two-segment front-end route that is defined before the add-on boots.
 *
 * Testbench loads a test's routes when the application has booted, ahead of Statamic's booted
 * callbacks that run the add-on's `bootAddon()`, so the route sits where a host's own
 * `routes/web.php` does (UAMS-Web/aether-people#116). The route is the shape of the
 * `uams-statamic` Area-of-Expertise section route: its first segment matches anything and its
 * second matches a token list that includes `providers`.
 *
 * Pest applies it with `uses()`, which PHPStan cannot see, so the analysis reports it unused.
 */
// @phpstan-ignore trait.unused (applied through Pest's uses(), which the analysis cannot follow)
trait DefinesGreedyHostRoute
{
    /**
     * The name of the host's greedy route.
     */
    public const string HOST_ROUTE = 'host.section';

    /**
     * Define the host's greedy route.
     *
     * @param  Router  $router  The application's router.
     */
    protected function defineRoutes($router): void
    {
        $router->get('{entry}/{section}', static fn (): string => 'host section')
            ->where('entry', '.*')
            ->where('section', 'providers|overview')
            ->name(self::HOST_ROUTE);
    }
}
