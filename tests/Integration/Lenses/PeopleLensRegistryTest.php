<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Lenses\Gates\ClinicalProviderLensGate;
use UAMSWeb\Aether\People\Lenses\Gates\ResearcherLensGate;
use UAMSWeb\Aether\People\Lenses\LensDescriptor;
use UAMSWeb\Aether\People\Lenses\PeopleLensRegistry;

/**
 * Coverage for {@see PeopleLensRegistry} — the config-driven people lens registry
 * (UAMS-Web/uams-statamic-archive#1636). The clinical lens and every org-derived lens are expressed as
 * configuration; the registry validates each descriptor and skips malformed rows
 * rather than fatally breaking the lens routes.
 *
 * @covers \UAMSWeb\Aether\People\Lenses\PeopleLensRegistry
 * @covers \UAMSWeb\Aether\People\Lenses\LensDescriptor
 *
 * @command php artisan test --compact tests/Feature/Support/People/Lenses/PeopleLensRegistryTest.php
 */
it('builds the shipped clinical provider lens descriptor from config', function (): void {
    $provider = mustBe(LensDescriptor::class, app(PeopleLensRegistry::class)->get('provider'), 'provider');

    expect($provider->handle)->toBe('provider')
        ->and($provider->template)->toBe('people/provider/show')
        ->and($provider->gate)->toBe(ClinicalProviderLensGate::class)
        ->and($provider->route)->toBe('provider/{slug}')
        ->and($provider->sites)->toBe(['health'])
        ->and($provider->activeOnSite('health'))->toBeTrue()
        ->and($provider->activeOnSite('default'))->toBeFalse();
});

it('builds the researcher lens descriptor from config (person-level gate, every site)', function (): void {
    $researcher = mustBe(LensDescriptor::class, app(PeopleLensRegistry::class)->get('researcher'), 'researcher');

    expect($researcher->handle)->toBe('researcher')
        ->and($researcher->template)->toBe('people/researcher/show')
        ->and($researcher->gate)->toBe(ResearcherLensGate::class)
        ->and($researcher->route)->toBe('researcher/{slug}')
        ->and($researcher->sites)->toBe(['*'])
        // Registered on every site; the person-level gate is the filter.
        ->and($researcher->activeOnSite('research'))->toBeTrue()
        ->and($researcher->activeOnSite('medicine_anesthesiology'))->toBeTrue();
});

it('builds the general lens as ungated, base-template, and active on no site by default', function (): void {
    $general = mustBe(LensDescriptor::class, app(PeopleLensRegistry::class)->get('general'), 'general');

    expect($general->handle)->toBe('general')
        ->and($general->template)->toBe('people/show')
        // Ungated: every published, publicly-visible person renders.
        ->and($general->gate)->toBeNull()
        ->and($general->route)->toBe('people/{slug}')
        // Opt-in: an explicit empty `sites` registers the lens but activates it on NO site,
        // so the ungated base profile does not render on all ~255 sites at once.
        ->and($general->sites)->toBe([])
        ->and($general->activeOnSite('default'))->toBeFalse()
        ->and($general->activeOnSite('health'))->toBeFalse();
});

it('activates the general lens only on a site that opts in', function (): void {
    // The opt-in mechanism: a site turns the ungated lens on by adding its handle.
    config()->set('people_lenses.lenses.general', [
        'template' => 'people/show',
        'route' => 'people/{slug}',
        'sites' => ['some_department'],
    ]);

    $general = mustBe(LensDescriptor::class, app(PeopleLensRegistry::class)->get('general'), 'general');

    expect($general->activeOnSite('some_department'))->toBeTrue()
        ->and($general->activeOnSite('default'))->toBeFalse();
});

it('registers a lens with an explicit empty sites list active on no site (opt-in)', function (): void {
    config()->set('people_lenses.lenses', [
        'dormant' => ['template' => 'people/x/show', 'route' => 'x/{slug}', 'sites' => []],
    ]);

    $dormant = mustBe(LensDescriptor::class, app(PeopleLensRegistry::class)->get('dormant'), 'dormant');

    // The descriptor is registered (not skipped) but activates nowhere until a site opts in.
    expect($dormant->sites)->toBe([])
        ->and($dormant->activeOnSite('anything'))->toBeFalse()
        ->and(app(PeopleLensRegistry::class)->activeOnSite('anything'))->not->toHaveKey('dormant');
});

it('returns null for an unknown lens handle', function (): void {
    expect(app(PeopleLensRegistry::class)->get('nonexistent'))->toBeNull();
});

it('skips a descriptor missing a template or a route', function (): void {
    config()->set('people_lenses.lenses', [
        'no_template' => ['route' => 'x/{slug}'],
        'no_route' => ['template' => 'people/x/show'],
        'ok' => ['template' => 'people/ok/show', 'route' => 'ok/{slug}'],
    ]);

    $registry = app(PeopleLensRegistry::class);

    $ok = mustBe(LensDescriptor::class, $registry->get('ok'), 'ok');

    expect($registry->get('no_template'))->toBeNull()
        ->and($registry->get('no_route'))->toBeNull()
        // An ok descriptor with no gate/sites defaults to ungated + every site.
        ->and($ok->gate)->toBeNull()
        ->and($ok->sites)->toBe(['*'])
        ->and($ok->activeOnSite('anything'))->toBeTrue();
});

it('ignores a gate that is not a LensGate implementation', function (): void {
    config()->set('people_lenses.lenses', [
        'x' => ['template' => 'people/x/show', 'route' => 'x/{slug}', 'gate' => stdClass::class],
    ]);

    $x = mustBe(LensDescriptor::class, app(PeopleLensRegistry::class)->get('x'), 'x');

    expect($x->gate)->toBeNull();
});

it('returns an empty set when the lens config is absent or malformed', function (): void {
    config()->set('people_lenses.lenses', 'not-an-array');

    expect(app(PeopleLensRegistry::class)->all())->toBe([]);
});

it('lists only the lenses active on a given site', function (): void {
    config()->set('people_lenses.lenses', [
        'a' => ['template' => 't', 'route' => 'a/{slug}', 'sites' => ['health']],
        'b' => ['template' => 't', 'route' => 'b/{slug}', 'sites' => ['*']],
        'c' => ['template' => 't', 'route' => 'c/{slug}', 'sites' => ['medicine']],
    ]);

    $registry = app(PeopleLensRegistry::class);

    /** @var list<string> $active */
    $active = array_keys($registry->activeOnSite('health'));

    expect(in_array('a', $active, true))->toBeTrue()
        ->and(in_array('b', $active, true))->toBeTrue()
        ->and(in_array('c', $active, true))->toBeFalse();
});
