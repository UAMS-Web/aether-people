<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Lenses\Gates\ResearcherLensGate;
use UAMSWeb\Aether\People\Lenses\PeopleLensRegistry;

covers(PeopleLensRegistry::class);

/**
 * Pins how the registry reads a lens row, so a malformed row is skipped rather than half-built.
 *
 * @command vendor/bin/pest tests/Integration/Lenses/PeopleLensRegistryMutationTest.php
 */

/**
 * A valid lens row, with the given overrides.
 *
 * @param  array<array-key, mixed>  $overrides
 * @return array<string, mixed>
 */
function lensRow(array $overrides = []): array
{
    return array_merge(['template' => 'people/x/show', 'route' => 'x/{slug}', 'gate' => null, 'sites' => ['*']], $overrides);
}

it('keys a lens by its handle even when the config keys it by number', function (): void {
    config()->set('people_lenses.lenses', [7 => lensRow()]);

    $registry = new PeopleLensRegistry;

    expect(array_keys($registry->all()))->toBe([7])
        ->and($registry->get('7')?->handle)->toBe('7');
});

it('lists only the rows that build, in config order', function (): void {
    config()->set('people_lenses.lenses', [
        'first' => lensRow(),
        'broken' => 'not a row',
        'second' => lensRow(['route' => 'y/{slug}']),
    ]);

    expect(array_keys((new PeopleLensRegistry)->all()))->toBe(['first', 'second']);
});

it('skips a row with an empty handle, however valid it is', function (): void {
    config()->set('people_lenses.lenses', ['' => lensRow()]);

    expect((new PeopleLensRegistry)->all())->toBe([]);
});

it('skips a row that is not an array', function (mixed $row): void {
    config()->set('people_lenses.lenses', ['x' => $row]);

    expect((new PeopleLensRegistry)->all())->toBe([]);
})->with(['string' => ['text'], 'null' => [null], 'int' => [3]]);

it('skips a row whose template or route is empty or not a string', function (array $override): void {
    config()->set('people_lenses.lenses', ['x' => lensRow($override)]);

    expect((new PeopleLensRegistry)->all())->toBe([]);
})->with([
    'empty template' => [['template' => '']],
    'empty route' => [['route' => '']],
    'numeric template' => [['template' => 5]],
    'numeric route' => [['route' => 5]],
    'missing template' => [['template' => null]],
    'missing route' => [['route' => null]],
]);

it('keeps a gate that is a lens gate and drops one that is not', function (): void {
    config()->set('people_lenses.lenses', [
        'good' => lensRow(['gate' => ResearcherLensGate::class]),
        'bad' => lensRow(['gate' => stdClass::class]),
    ]);

    $all = (new PeopleLensRegistry)->all();

    expect($all['good']->gate)->toBe(ResearcherLensGate::class)
        ->and($all['bad']->gate)->toBeNull();
});

it('reduces an explicit sites list to its non-empty strings, reindexed', function (): void {
    config()->set('people_lenses.lenses', ['x' => lensRow(['sites' => ['health', '', 5, null, 'law']])]);

    expect((new PeopleLensRegistry)->get('x')?->sites)->toBe(['health', 'law']);
});

it('reads an absent or malformed sites value as every site', function (array $row): void {
    config()->set('people_lenses.lenses', ['x' => $row]);

    expect((new PeopleLensRegistry)->get('x')?->sites)->toBe(['*']);
})->with([
    'absent' => [['template' => 't', 'route' => 'r/{slug}']],
    'a string' => [['template' => 't', 'route' => 'r/{slug}', 'sites' => 'health']],
]);
