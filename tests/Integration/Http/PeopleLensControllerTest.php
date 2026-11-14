<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use Statamic\Sites\Site;
use UAMSWeb\Aether\People\Http\Controllers\PeopleLensController;
use UAMSWeb\Aether\People\Lenses\LensDescriptor;
use UAMSWeb\Aether\People\Lenses\LensGate;
use UAMSWeb\Aether\People\Lenses\PeopleLensRegistry;
use UAMSWeb\Aether\People\Support\CanonicalPeopleResolver;

/**
 * The security-critical publish/visibility gate for the people lens route
 * (UAMS-Web/uams-statamic-archive#1635 / UAMS-Web/uams-statamic-archive#1636): the custom route bypasses Statamic's DataResponse + the app's
 * ProtectEntry (UAMS-Web/uams-statamic-archive#223), so it must render only published, publicly-visible people
 * (everything else delegates to the normal front end). These pin that gate with a
 * mocked entry, mirroring ClinicalExpertiseSectionControllerTest.
 *
 * @covers \UAMSWeb\Aether\People\Http\Controllers\PeopleLensController
 *
 * @command php artisan test --compact tests/Unit/Http/Controllers/People/PeopleLensControllerTest.php
 */

/**
 * @param  array<string, mixed>  $values
 */
function lensGateEntry(string $status, array $values = []): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('status')->andReturn($status);
    $entry->shouldReceive('value')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    return mustBe(Entry::class, $entry, 'the mocked entry');
}

function lensController(): PeopleLensController
{
    // The resolver + registry are `final` (cannot be mocked) but the publish/visibility
    // gate under test never invokes them, so real, dependency-free instances suffice.
    return new PeopleLensController(
        new CanonicalPeopleResolver,
        new PeopleLensRegistry,
    );
}

it('renders a published, publicly-visible person', function (): void {
    expect(lensController()->isPubliclyRenderable(lensGateEntry('published')))->toBeTrue();
});

it('refuses a draft person (no draft-content disclosure)', function (): void {
    expect(lensController()->isPubliclyRenderable(lensGateEntry('draft')))->toBeFalse();
});

it('refuses a scheduled (future-dated) person', function (): void {
    expect(lensController()->isPubliclyRenderable(lensGateEntry('scheduled')))->toBeFalse();
});

it('refuses a private person even when published', function (): void {
    expect(lensController()->isPubliclyRenderable(lensGateEntry('published', ['status_and_visibility' => 'private'])))->toBeFalse();
});

it('refuses a password-protected person even when published', function (): void {
    expect(lensController()->isPubliclyRenderable(lensGateEntry('published', ['password_protected' => true])))->toBeFalse();
});

/**
 * Whether the controller's private lens gate admits the person for a lens gated by the
 * given class.
 */
function lensPassesGate(?string $gate): bool
{
    // Built by reflection so a gate that is not a LensGate class can be configured, as a
    // mistaken registry entry would be
    $descriptor = new ReflectionClass(LensDescriptor::class)->newInstanceArgs(['probe', 'people/probe', $gate, 'probe', ['*']]);
    $method = new ReflectionMethod(PeopleLensController::class, 'passesGate');

    return (bool) $method->invoke(lensController(), $descriptor, lensGateEntry('published'));
}

it('admits every person to a lens with no gate', function (): void {
    expect(lensPassesGate(null))->toBeTrue();
});

it('refuses a person when the gate class is not a lens gate', function (): void {
    // A misconfigured gate fails closed rather than admitting everyone
    expect(lensPassesGate(stdClass::class))->toBeFalse();
});

it('asks the lens gate on the current site', function (bool $passes): void {
    $gate = Mockery::mock(LensGate::class);
    $gate->shouldReceive('passes')->once()->with(Mockery::type(Entry::class), Mockery::type(Site::class))->andReturn($passes);
    app()->instance(LensGate::class, $gate);

    expect(lensPassesGate(LensGate::class))->toBe($passes);
})->with(['the gate admits' => [true], 'the gate refuses' => [false]]);
