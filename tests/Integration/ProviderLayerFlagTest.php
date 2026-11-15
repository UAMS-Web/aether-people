<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Collection;
use UAMSWeb\Aether\People\Listeners\RefreshProviderEligibilityForLocation;
use UAMSWeb\Aether\People\Listeners\StripProviderPeopleVirtualFieldsOnEntrySaving;
use UAMSWeb\Aether\People\ServiceProvider;
use UAMSWeb\Aether\People\Support\Features;

/**
 * The `people.features.provider_layer` switch: what the add-on registers with it on and with it off.
 *
 * Each case boots its own application, so the flag is read where a host sets it, before the add-on's
 * provider boots. The switch covers the computed fields, the entry listeners and the Find-a-Doc routes;
 * the `clinical_provider_eligible` modifier and the eligibility scope are always registered.
 *
 * @command vendor/bin/pest tests/Integration/ProviderLayerFlagTest.php
 */
covers(Features::class, ServiceProvider::class);

/**
 * Switch the provider layer through the environment variable `config/people.php` reads, then boot a fresh
 * application, so the flag is in place before the add-on's provider registers, as it is when a host sets it.
 *
 * @param  string|null  $value  The variable's value, or null to leave it unset.
 */
function providerLayerBootWithEnv(?string $value): void
{
    if ($value === null) {
        putenv('PEOPLE_PROVIDER_LAYER');
        unset($_ENV['PEOPLE_PROVIDER_LAYER'], $_SERVER['PEOPLE_PROVIDER_LAYER']);
    } else {
        putenv("PEOPLE_PROVIDER_LAYER={$value}");
        $_ENV['PEOPLE_PROVIDER_LAYER'] = $value;
        $_SERVER['PEOPLE_PROVIDER_LAYER'] = $value;
    }

    test()->refreshApplication();
}

afterEach(function (): void {
    // Leave the variable unset for the next test, however this one ended
    putenv('PEOPLE_PROVIDER_LAYER');
    unset($_ENV['PEOPLE_PROVIDER_LAYER'], $_SERVER['PEOPLE_PROVIDER_LAYER']);
});

/**
 * The shared computed handles the registrar adds to the `people` collection.
 */
const PROVIDER_LAYER_PEOPLE_COMPUTEDS = ['display_name_full', 'full_name', 'searchable', 'clinical_title', 'hide_medical_ontology', 'clinical_provider_profile_eligible_query'];

/**
 * The classes registered as listeners of an event, read from the class listeners' closures.
 *
 * @return list<string>
 */
function providerLayerListenerClasses(string $event): array
{
    $classes = [];

    foreach (app(Dispatcher::class)->getListeners($event) as $listener) {
        $listenerClass = new ReflectionFunction(mustBe(Closure::class, $listener, 'a listener'))->getStaticVariables()['listener'] ?? null;

        if (is_string($listenerClass)) {
            $classes[] = $listenerClass;
        }
    }

    return $classes;
}

/**
 * How many times a class is registered as a listener of an event.
 *
 * @param  class-string  $listener
 */
function providerLayerListenerCount(string $event, string $listener): int
{
    return count(array_filter(providerLayerListenerClasses($event), fn (string $class): bool => $class === $listener));
}

/**
 * Whether the router serves a path for GET.
 */
function providerLayerServesRoute(string $uri): bool
{
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if ($route->uri() === $uri && in_array('GET', $route->methods(), true)) {
            return true;
        }
    }

    return false;
}

describe('with the provider layer on (the default)', function (): void {
    it('registers the computed fields on the people collection', function (): void {
        $callbacks = Collection::getComputedCallbacks('people');

        foreach (PROVIDER_LAYER_PEOPLE_COMPUTEDS as $handle) {
            expect($callbacks->has($handle))->toBeTrue("people should register {$handle}");
        }
    });

    it('registers the entry listeners', function (): void {
        // Exactly once each: Statamic discovers the classes under `src/Listeners`, so a second `Event::listen`
        // for the same class would run the listener twice per event
        expect(providerLayerListenerCount(EntrySaving::class, StripProviderPeopleVirtualFieldsOnEntrySaving::class))->toBe(1)
            ->and(providerLayerListenerCount(EntrySaved::class, RefreshProviderEligibilityForLocation::class))->toBe(1)
            ->and(providerLayerListenerCount(EntryDeleted::class, RefreshProviderEligibilityForLocation::class))->toBe(1);
    });

    it('serves the Find-a-Doc routes', function (): void {
        expect(providerLayerServesRoute('api/v1/find-a-doc/providers'))->toBeTrue()
            ->and(providerLayerServesRoute('api/v1/find-a-doc/providers/{provider}'))->toBeTrue();
    });

    it('registers the clinical_provider_eligible modifier', function (): void {
        expect(app('statamic.modifiers')->has('clinical_provider_eligible'))->toBeTrue();
    });

    it('reports the layer on', function (): void {
        expect(Features::providerLayer())->toBeTrue();
    });
});

describe('with the provider layer off', function (): void {
    beforeEach(function (): void {
        providerLayerBootWithEnv('false');
    });

    it('reads the flag as off', function (): void {
        expect(config('people.features.provider_layer'))->toBeFalse()
            ->and(Features::providerLayer())->toBeFalse();
    });

    it('registers no computed fields on the people collection', function (): void {
        $callbacks = Collection::getComputedCallbacks('people');

        foreach (PROVIDER_LAYER_PEOPLE_COMPUTEDS as $handle) {
            expect($callbacks->has($handle))->toBeFalse("people should not register {$handle}");
        }
    });

    it('registers no entry listeners', function (): void {
        expect(providerLayerListenerCount(EntrySaving::class, StripProviderPeopleVirtualFieldsOnEntrySaving::class))->toBe(0)
            ->and(providerLayerListenerCount(EntrySaved::class, RefreshProviderEligibilityForLocation::class))->toBe(0)
            ->and(providerLayerListenerCount(EntryDeleted::class, RefreshProviderEligibilityForLocation::class))->toBe(0);
    });

    it('serves no Find-a-Doc routes', function (): void {
        expect(providerLayerServesRoute('api/v1/find-a-doc/providers'))->toBeFalse()
            ->and(providerLayerServesRoute('api/v1/find-a-doc/providers/{provider}'))->toBeFalse();
    });

    it('still registers the clinical_provider_eligible modifier', function (): void {
        expect(app('statamic.modifiers')->has('clinical_provider_eligible'))->toBeTrue();
    });
});

it('treats a switch as on unless its config value is exactly false', function (mixed $value, bool $expected): void {
    config()->set('people.features', ['provider_layer' => $value]);

    expect(Features::providerLayer())->toBe($expected)
        ->and(Features::enabled(Features::PROVIDER_LAYER))->toBe($expected);
})->with([
    'true' => [true, true],
    'false' => [false, false],
    'null' => [null, true],
    'zero' => [0, true],
    'the string false' => ['false', true],
]);

it('keeps the layer on when the host config carries no features key', function (): void {
    config()->set('people.features', []);

    expect(Features::providerLayer())->toBeTrue()
        ->and(Features::enabled('a_feature_nothing_configured'))->toBeTrue();
});
