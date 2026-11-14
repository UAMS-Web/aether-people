<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use UAMSWeb\Aether\People\Support\CanonicalPeopleResolver;

covers(CanonicalPeopleResolver::class);

/**
 * Narrow mutate coverage for UAMSWeb\Aether\People\Support\CanonicalPeopleResolver.
 *
 * @command  php artisan test --compact tests/Unit/Support/People/CanonicalPeopleResolverMutationTest.php
 */
it('reads the canonical site from config with a default fallback', function (mixed $configured, string $expected): void {
    if ($configured === '__absent__') {
        config()->offsetUnset('people_lenses.canonical_site');
    } else {
        config()->set('people_lenses.canonical_site', $configured);
    }

    expect((new CanonicalPeopleResolver)->canonicalSite())->toBe($expected);
})->with([
    'string site' => ['health', 'health'],
    'empty string' => ['', 'default'],
    'whitespace only stays as configured string' => ['   ', '   '],
    'null' => [null, 'default'],
    'integer' => [12, 'default'],
    'absent key uses default arg' => ['__absent__', 'default'],
]);

it('does not query when the slug is empty', function (): void {
    EntryFacade::shouldReceive('query')->never();

    expect((new CanonicalPeopleResolver)->resolve(''))->toBeNull();
});

it('queries people on the canonical site for the slug', function (): void {
    config()->set('people_lenses.canonical_site', 'health');

    $entry = Mockery::mock(Entry::class);

    $query = Mockery::mock();
    $query->shouldReceive('where')->with('collection', 'people')->once()->andReturnSelf();
    $query->shouldReceive('where')->with('site', 'health')->once()->andReturnSelf();
    $query->shouldReceive('where')->with('slug', 'spock')->once()->andReturnSelf();
    $query->shouldReceive('first')->once()->andReturn($entry);

    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    expect((new CanonicalPeopleResolver)->resolve('spock'))->toBe($entry);
});

it('returns null when first() is not an Entry', function (mixed $first): void {
    // InstanceOfToTrue would treat a stdClass as a hit and return the wrong type.
    config()->set('people_lenses.canonical_site', 'default');

    $query = Mockery::mock();
    $query->shouldReceive('where')->times(3)->andReturnSelf();
    $query->shouldReceive('first')->once()->andReturn($first);

    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    expect((new CanonicalPeopleResolver)->resolve('nobody'))->toBeNull();
})->with([
    'null' => [null],
    'stdClass' => [new stdClass],
]);
