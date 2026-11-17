<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use UAMSWeb\Aether\People\Merge\RedirectsGlobalWriter;

uses(MockeryPHPUnitIntegration::class);

covers(RedirectsGlobalWriter::class);

/**
 * The writer the people merge's redirects go through on a host whose redirects live in the `redirects`
 * globals set: it counts what a write would create, and writes exactly that, to each site's own
 * localization (UAMS-Web/aether-people#100).
 *
 * @command vendor/bin/pest tests/Integration/Merge/RedirectsGlobalWriterTest.php
 */

/**
 * One planned redirect row.
 *
 * @return array{site: string, source: string, target: string, response: string}
 */
function rgwRow(string $site, string $source): array
{
    return ['site' => $site, 'source' => $source, 'target' => '/people/jane', 'response' => '301'];
}

/**
 * The redirect rows stored on one site's localization of the set.
 *
 * @return list<array<string, mixed>>
 */
function rgwStored(string $site): array
{
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');
    /** @var Variables|null $variables */
    $variables = $set->in($site);

    /** @var list<array<string, mixed>> $rows */
    $rows = $variables?->get('redirects', []) ?? [];

    return $rows;
}

beforeEach(function (): void {
    isolateStacheAndBlueprintsIn('merge-redirects-global-writer');

    config(['statamic.system.multisite' => true, 'statamic.editions.pro' => true]);
    Site::setSites([
        'default' => ['name' => 'Default', 'locale' => 'en_US', 'url' => '/'],
        'health' => ['name' => 'Health', 'locale' => 'en_US', 'url' => '/health/'],
    ]);

    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->sites(['default' => null, 'health' => null]);
    $set->save();

    /** @var Variables $vars */
    $vars = $set->makeLocalization('default');
    $vars->data(['redirects' => [['id' => 'x', 'source' => '/already', 'target_url' => '/t']]]);
    $vars->save();
});

it('counts what a write creates across sites, and writes nothing while counting', function (): void {
    $plan = [rgwRow('default', '/a'), rgwRow('default', '/b'), rgwRow('health', '/a')];
    $writer = new RedirectsGlobalWriter;

    expect($writer->count($plan))->toBe(3)
        ->and(rgwStored('default'))->toHaveCount(1)
        ->and($writer->write($plan))->toBe(3)
        ->and(rgwStored('default'))->toHaveCount(3)
        ->and(rgwStored('health'))->toHaveCount(1);
});

it('counts and writes only the sources that do not already redirect on the site', function (): void {
    $plan = [rgwRow('default', '/already'), rgwRow('default', '/new'), rgwRow('default', '/new'), rgwRow('health', '/already')];
    $writer = new RedirectsGlobalWriter;

    expect($writer->count($plan))->toBe(2)
        ->and($writer->write($plan))->toBe(2)
        ->and(array_column(rgwStored('default'), 'source'))->toBe(['/already', '/new'])
        ->and(array_column(rgwStored('health'), 'source'))->toBe(['/already']);
});

it('counts zero and writes nothing when every source already redirects', function (): void {
    $writer = new RedirectsGlobalWriter;

    expect($writer->count([rgwRow('default', '/already')]))->toBe(0)
        ->and($writer->write([rgwRow('default', '/already')]))->toBe(0)
        ->and(rgwStored('default'))->toHaveCount(1);
});

it('counts zero and writes nothing for an empty plan, without a warning', function (): void {
    Log::partialMock()->shouldReceive('warning')->never();
    $writer = new RedirectsGlobalWriter;

    expect($writer->count([]))->toBe(0)
        ->and($writer->write([]))->toBe(0);
});

it('warns and reports zero from both methods when the redirects set is missing', function (): void {
    /** @var Statamic\Globals\GlobalSet|null $set */
    $set = GlobalSet::findByHandle('redirects');
    $set?->delete();

    Log::partialMock()
        ->shouldReceive('warning')
        ->twice()
        ->with('People merge could not create redirects: redirects global set is missing.', ['planned' => 1]);

    $writer = new RedirectsGlobalWriter;

    expect($writer->count([rgwRow('default', '/a')]))->toBe(0)
        ->and($writer->write([rgwRow('default', '/a')]))->toBe(0);
});
