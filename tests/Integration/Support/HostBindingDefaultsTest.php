<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\ComputedFields\Registration\ProvidersPeopleSharedCollectionComputedsRegistrar;
use UAMSWeb\Aether\People\Contracts\LocationRegions;
use UAMSWeb\Aether\People\Contracts\PublicationCitations;
use UAMSWeb\Aether\People\ServiceProvider;
use UAMSWeb\Aether\People\Support\MissingHostBindingException;
use UAMSWeb\Aether\People\Support\UnboundLocationRegions;
use UAMSWeb\Aether\People\Support\UnboundPublicationCitations;
use UAMSWeb\Aether\People\Tests\Support\HostFakes;

covers(ServiceProvider::class, UnboundLocationRegions::class, UnboundPublicationCitations::class, MissingHostBindingException::class);

/**
 * The provider layer builds two host-bound contracts at boot. A host that binds neither still boots,
 * and the missing contract is refused by name at first use (UAMS-Web/aether-people#144).
 *
 * @command  vendor/bin/pest tests/Integration/Support/HostBindingDefaultsTest.php
 */

/**
 * Put the container in the state of a host that binds neither contract, and run the add-on's own
 * registration again, as it runs on that host.
 */
function hostWithoutProviderBindings(): void
{
    app()->offsetUnset(LocationRegions::class);
    app()->offsetUnset(PublicationCitations::class);

    $provider = app()->getProvider(ServiceProvider::class);
    expect($provider)->toBeInstanceOf(ServiceProvider::class);

    $provider?->register();
}

it('binds a refusing default for each contract the provider layer builds at boot', function (): void {
    hostWithoutProviderBindings();

    expect(app(LocationRegions::class))->toBeInstanceOf(UnboundLocationRegions::class)
        ->and(app(PublicationCitations::class))->toBeInstanceOf(UnboundPublicationCitations::class);
});

it('builds the provider layer registrar on a host that binds neither contract', function (): void {
    hostWithoutProviderBindings();

    expect(fn (): ProvidersPeopleSharedCollectionComputedsRegistrar => app(ProvidersPeopleSharedCollectionComputedsRegistrar::class))
        ->not->toThrow(Throwable::class);
});

it('refuses by name when the location regions are first needed', function (): void {
    hostWithoutProviderBindings();

    expect(fn (): mixed => app(LocationRegions::class)->resolveRegions(new Entry))
        ->toThrow(MissingHostBindingException::class, 'The clinical provider layer needs a host binding for UAMSWeb\Aether\People\Contracts\LocationRegions, and none is bound. Bind it in the host, or set PEOPLE_PROVIDER_LAYER=false to turn the layer off.');
});

it('refuses by name when publication citations are first needed', function (): void {
    hostWithoutProviderBindings();

    expect(fn (): array => app(PublicationCitations::class)->enrichResearchPublicationSelectSets([]))
        ->toThrow(MissingHostBindingException::class, 'The clinical provider layer needs a host binding for UAMSWeb\Aether\People\Contracts\PublicationCitations, and none is bound. Bind it in the host, or set PEOPLE_PROVIDER_LAYER=false to turn the layer off.');
});

it('lets a host binding replace the default, bound before or after the add-on registers', function (): void {
    hostWithoutProviderBindings();

    $regions = HostFakes::locationRegions();
    $citations = HostFakes::publicationCitations();
    app()->singleton(LocationRegions::class, fn (): LocationRegions => $regions);
    app()->singleton(PublicationCitations::class, fn (): PublicationCitations => $citations);

    expect(app(LocationRegions::class))->toBe($regions)
        ->and(app(PublicationCitations::class))->toBe($citations);

    // A host that bound before the add-on registered keeps its binding when registration runs again
    app()->getProvider(ServiceProvider::class)?->register();

    expect(app(LocationRegions::class))->toBe($regions)
        ->and(app(PublicationCitations::class))->toBe($citations);
});
