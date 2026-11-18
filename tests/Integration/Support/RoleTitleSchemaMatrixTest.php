<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Tests\Support\RoleTitleSchemaMatrix;

/**
 * Proves that a migration touching the `emeritus` / `emerita` guards in
 * `config/role_title.php` changed no schema output.
 *
 * 34 of the 37 honorific conditions are `not_contains_any` guards of the form
 * "this rule applies only when `title_options` does not carry the honorific".
 * When the honorific moves out of `title_options`, every one of those becomes
 * vacuously true: the condition still evaluates, still passes, and the rule it
 * guards starts firing for exactly the titles it was written to exclude. Nothing
 * errors and no other test fails, so this matrix is the instrument that would
 * notice.
 *
 * The matrix is DERIVED from the config rather than enumerated here, so a schema
 * rule added later is covered without a test edit.
 *
 * @command  php artisan test --compact tests/Integration/Support/RoleTitleSchemaMatrixTest.php
 *
 * THE POSITIVE CONTROL, WRITTEN DOWN SO IT CAN BE RE-RUN RATHER THAN TRUSTED.
 *
 * A matrix that silently covers nothing passes exactly like one that covers
 * everything, so the only evidence that this file can fail is watching it fail.
 * Invert one guard, confirm red, restore, confirm green:
 *
 *   # 1. copy the config aside -- NOT `git stash`, which is shared between
 *   #    worktrees and pops another session's entry
 *   cp config/role_title.php /tmp/role_title.php.orig
 *
 *   # 2. invert exactly ONE `not_contains_any` guard, asserting the edit applied.
 *   #    An edit that silently fails to match reads as a passing control and is
 *   #    the failure this step exists to exclude.
 *   python3 - <<'EOF'
 *   import io
 *   p = 'config/role_title.php'
 *   s = io.open(p, encoding='utf-8').read()
 *   before = s.count("'operator' => 'not_contains_any'")
 *   assert before == 34, before
 *   s = s.replace("'operator' => 'not_contains_any'", "'operator' => 'contains_any'", 1)
 *   assert s.count("'operator' => 'not_contains_any'") == 33
 *   io.open(p, 'w', encoding='utf-8').write(s)
 *   EOF
 *
 *   # 3. expect RED, naming the rule, the configuration and the honorific state
 *   php artisan test --compact tests/Integration/Support/RoleTitleSchemaMatrixTest.php
 *
 *   # 4. restore and expect GREEN
 *   cp /tmp/role_title.php.orig config/role_title.php
 *
 * Measured on macOS (Darwin 25.6.0), PHP 8.4.23, at `e3d214487`. Inverting the
 * first guard moved 16 cases -- rule `#0 role=chancellor` across all four
 * configurations and all four honorific states -- and each was reported by label:
 *
 *   CHANGED: #0 role=chancellor hierarchical_modifier=null positional_qualifier=null :: faculty :: none
 *   CHANGED: #0 role=chancellor hierarchical_modifier=null positional_qualifier=null :: academic_leadership :: emeritus
 *   ...
 *
 * Restoring the guard returned all four tests to green with the config
 * byte-identical to HEAD.
 */

/**
 * Absolute path to the recorded matrix.
 */
function roleTitleMatrixFixturePath(): string
{
    return __DIR__.'/../../Fixtures/role_title_schema_matrix.json';
}

it('reproduces the recorded role_title schema matrix', function (): void {
    $path = roleTitleMatrixFixturePath();

    expect(file_exists($path))->toBeTrue();

    $decoded = json_decode((string) file_get_contents($path), true);

    expect($decoded)->toBeArray();

    /** @var array<string, mixed> $expected */
    $expected = $decoded;

    $actual = RoleTitleSchemaMatrix::build();

    // Name what moved rather than diffing 592 entries. Each label already carries
    // the rule, the configuration and the honorific state, which is what a reader
    // needs to find the guard that changed.
    $movedCases = [];

    foreach ($expected as $label => $recordedOutput) {
        if (! array_key_exists($label, $actual)) {
            $movedCases[] = 'MISSING: '.$label;

            continue;
        }

        if ($actual[$label] !== $recordedOutput) {
            $movedCases[] = 'CHANGED: '.$label;
        }
    }

    foreach (array_keys($actual) as $label) {
        if (! array_key_exists($label, $expected)) {
            $movedCases[] = 'NEW: '.$label;
        }
    }

    expect($movedCases)->toBe([]);
});

it('covers every honorific-guarded rule the config declares', function (): void {
    $guarded = RoleTitleSchemaMatrix::honorificGuardedRules();

    // A rule added to the config lands here without a test edit; the count is
    // asserted so that it fails loudly and names the drift rather than silently
    // widening the matrix.
    expect($guarded)->toHaveCount(37);

    // Both directions are covered. A rule that should fire and stops firing is as
    // much a regression as one that starts, so the 3 positive `equals_any`
    // conditions are asserted alongside the 34 negative `equals => null` guards.
    // (Since UAMS-Web/uams-statamic-archive#2138 the honorific is a radios slot: the guards moved from
    // `not_contains_any` over `title_options` to `equals => null` over `honorific`,
    // and the positives from `contains_any` to `equals_any`.)
    expect(RoleTitleSchemaMatrix::honorificOperatorCensus())->toBe([
        'equals' => 34,
        'equals_any' => 3,
    ]);
});

it('crosses every configuration and every honorific state', function (): void {
    $configurations = RoleTitleSchemaMatrix::configurationHandles();
    $states = RoleTitleSchemaMatrix::honorificStates();

    expect($configurations)->toBe([
        'faculty',
        'academic_leadership',
        'institutional_leadership',
        'clinical_leadership',
    ]);

    expect(array_keys($states))->toBe(['none', 'emeritus', 'emerita', 'both']);

    // 37 guarded rules x 4 configurations x 4 honorific states.
    expect(RoleTitleSchemaMatrix::build())->toHaveCount(592);
});

it('records output that actually varies, so the matrix cannot pass vacuously', function (): void {
    $matrix = RoleTitleSchemaMatrix::build();

    $nonEmpty = array_filter($matrix, static fn (array $output): bool => $output !== []);

    // A matrix of 592 empty outputs would compare equal to a fixture of 592 empty
    // outputs and prove nothing. This is the floor that stops that reading.
    expect(count($nonEmpty))->toBeGreaterThan(100);

    // And the honorific axis must actually change something, or the guards are not
    // being exercised at all.
    $byState = [];

    foreach ($matrix as $label => $output) {
        $state = substr($label, (int) strrpos($label, ' :: ') + 4);
        $byState[$state] = ($byState[$state] ?? 0) + ($output === [] ? 0 : 1);
    }

    expect($byState['none'])->not->toBe($byState['emeritus']);
});
