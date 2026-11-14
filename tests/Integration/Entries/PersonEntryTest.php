<?php

declare(strict_types=1);

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use UAMSWeb\Aether\People\Entries\PersonEntry;

/**
 * Coverage for the People profile-page suppression (UAMS-Web/uams-statamic-archive#43).
 *
 * `PersonEntry::route()` returns null only when `profile_page_query` is
 * explicitly false, making `Entry::uri()` short-circuit to null (a 404 for
 * `people/{slug}`) while the entry stays published and queryable. An unset
 * value (legacy entries) and an explicit true both keep the collection route.
 *
 * The override is exercised with partial mocks so the logic is covered without
 * content or the Stache: `get()`, `collection()`, and `locale()` are the only
 * collaborators `route()` touches. The host site keeps the test that reads a
 * real `people` entry back through its Stache store.
 *
 * @command  vendor/bin/pest tests/Integration/Entries/PersonEntryTest.php
 */
uses(MockeryPHPUnitIntegration::class);

covers(PersonEntry::class);

/**
 * A partial PersonEntry whose `profile_page_query` holds the given value, in a
 * collection whose route for `en` is the given template.
 *
 * @param  mixed  $profilePage  The stored `profile_page_query` value
 * @param  string|null  $collectionRoute  The collection's route template, or null for an un-routed collection
 */
function personEntryRouting(mixed $profilePage, ?string $collectionRoute): PersonEntry
{
    // Make the collection answer with the given route template
    $collection = Mockery::mock();
    $collection->shouldReceive('route')
        ->with('en')
        ->andReturn($collectionRoute);

    // Make the entry read the given toggle value and the collection above
    $entry = Mockery::mock(PersonEntry::class)->makePartial();
    $entry->shouldReceive('get')
        ->with(PersonEntry::PROFILE_PAGE_FIELD)
        ->andReturn($profilePage);
    $entry->shouldReceive('collection')
        ->andReturn($collection);
    $entry->shouldReceive('locale')
        ->andReturn('en');

    // Narrow the mock to the class it extends
    if (! $entry instanceof PersonEntry) {
        throw new LogicException('Mockery did not return a partial PersonEntry.');
    }

    return $entry;
}

it('names the profile-page toggle by its field handle', function (): void {
    expect(PersonEntry::PROFILE_PAGE_FIELD)->toBe('profile_page_query');
});

it('suppresses the route when profile_page_query is explicitly false', function (): void {
    // No profile page, so no route and Statamic 404s the URL
    expect(personEntryRouting(false, 'people/{slug}')->route())->toBeNull();
});

it('keeps the collection route when profile_page_query is true', function (): void {
    expect(personEntryRouting(true, 'people/{slug}')->route())->toBe('people/{slug}');
});

it('keeps the collection route when profile_page_query is unset (default routable)', function (): void {
    // Legacy entries saved before the field existed return null, not false
    expect(personEntryRouting(null, 'people/{slug}')->route())->toBe('people/{slug}');
});

it('keeps the collection route for a falsy value that is not false', function (mixed $stored): void {
    expect(personEntryRouting($stored, 'people/{slug}')->route())->toBe('people/{slug}');
})->with([
    'zero' => [0],
    'empty string' => [''],
    'string "false"' => ['false'],
]);

it('returns null when the collection has no route', function (): void {
    expect(personEntryRouting(true, null)->route())->toBeNull();
});
