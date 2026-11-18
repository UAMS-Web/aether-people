<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\RoleTitleValue;

/**
 * A stored `scope_joiner` outside the configured option keys falls back to the
 * default joiner, and no fixture relies on that (UAMS-Web/uams-statamic-archive#2408, UAMS-Web/uams-statamic-archive#2546).
 *
 * The fallback is correct: an unknown stored key should degrade to the default
 * rather than emit a broken phrase. It is also silent, which is what let two
 * fixtures store `of` -- then never a configured key -- and compose `for` for as
 * long as they existed. Nothing failed, so nothing said so. Since UAMS-Web/uams-statamic-archive#2546 `of` IS a
 * configured key and the five combined keys are not, so a row that was never
 * migrated by `role-title:collapse-scope-joiners` degrades the same silent way.
 *
 * These tests pin both halves: that the degradation is deliberate, and that no
 * fixture is depending on it.
 *
 * @command  php artisan test --compact tests/Integration/Support/RoleTitleScopeJoinerFallbackTest.php
 */

/**
 * Compose an institutional-leadership title carrying the given joiner.
 */
function composeWithJoiner(string $joiner, string $name = 'science operations'): string
{
    return RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP,
        'title_options' => [],
        'hierarchical_modifier' => null,
        'role' => 'director',
        'scope' => ['scope_joiner' => $joiner, 'scope_name' => $name],
    ]);
}

it('falls back to the default joiner for a stored key that is not configured', function (string $joiner): void {
    $default = RoleTitleValue::firstOrderedOptionKeyForNestedField('scope', 'scope_joiner');

    // The unknown key composes exactly as the default key does
    expect(composeWithJoiner($joiner))->toBe(composeWithJoiner($default));
})->with([
    'a legacy combined key' => ['of_with_the'],
    'another legacy combined key' => ['of_without_the'],
    'an invented key' => ['alongside'],
    'empty' => [''],
]);

it('composes the default joiner`s own phrasing, not the one the stored key names', function (): void {
    // The not-yet-migrated `of_without_the` reads as "of" and composes "for", which is
    // the whole defect: the fallback is right and gives no sign that it fired
    expect(composeWithJoiner('of_without_the'))->toBe('director for science operations');
    expect(composeWithJoiner('of'))->toBe('director of science operations');
});

it('keeps the default joiner as the first configured option', function (): void {
    // The fallback target is positional, so a reordering of the options would
    // silently change every unknown-key composition
    $ordered = RoleTitleValue::orderedOptionKeysForSubfield('scope', 'scope_joiner');

    expect(RoleTitleValue::firstOrderedOptionKeyForNestedField('scope', 'scope_joiner'))
        ->toBe($ordered[0]);
});
