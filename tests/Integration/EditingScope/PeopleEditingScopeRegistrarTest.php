<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Validator;
use Statamic\Events\EntrySaving;
use UAMSWeb\Aether\People\EditingScope\EnforcePeopleEditingScope;
use UAMSWeb\Aether\People\EditingScope\EnforcePeopleScopeOnProviderSync;
use UAMSWeb\Aether\People\EditingScope\PeopleEditingScopeRegistrar;

covers(PeopleEditingScopeRegistrar::class);

/**
 * What the editing scope registrar registers while the add-on boots. The listing hook's behavior is
 * pinned in `ScopedPeopleEditingTest`; this file pins the validation rule and the listener order.
 *
 * @command vendor/bin/pest tests/Integration/EditingScope/PeopleEditingScopeRegistrarTest.php
 */

/**
 * The classes subscribed to `EntrySaving`, in the order they run, read from the class listeners' closures.
 *
 * @return list<string>
 */
function registrarEntrySavingListeners(): array
{
    $classes = [];

    foreach (app(Dispatcher::class)->getListeners(EntrySaving::class) as $listener) {
        $listenerClass = new ReflectionFunction(mustBe(Closure::class, $listener, 'a listener'))->getStaticVariables()['listener'] ?? null;

        if (is_string($listenerClass)) {
            $classes[] = $listenerClass;
        }
    }

    return $classes;
}

it('registers the validation rule the user group blueprint names', function (): void {
    // An unregistered rule throws instead of passing
    expect(Validator::make(['organization' => 'org-none'], ['organization' => 'not_top_level_organization'])->passes())->toBeTrue();
});

it('subscribes the two save listeners once each, the scope check first', function (): void {
    $classes = registrarEntrySavingListeners();
    $positions = array_flip($classes);

    expect(array_keys($classes, EnforcePeopleEditingScope::class, true))->toHaveCount(1)
        ->and(array_keys($classes, EnforcePeopleScopeOnProviderSync::class, true))->toHaveCount(1)
        ->and($positions[EnforcePeopleEditingScope::class])->toBeLessThan($positions[EnforcePeopleScopeOnProviderSync::class]);
});
