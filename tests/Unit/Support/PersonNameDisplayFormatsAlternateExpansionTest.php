<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\PersonNameDisplayFormats;

/**
 * Alternate-name expansion helpers on PersonNameDisplayFormats
 * (app/Support/PersonNameDisplayFormats.php): the merged phonetic-text map
 * (first non-null wins per trimmed text), and the alternate-name display-expansion
 * rows (skipping the stored option key, and emitting phonetic text without straight
 * double quotes).
 *
 * Scope: this is the FOCUSED alternate-expansion subset. The broad class coverage lives
 * in Feature\Support\PersonNameDisplayFormatsTest; these 3 property tests transitively
 * execute many formatting/IPA helpers but only assert the alternate-expansion behavior,
 * so a class-scoped `--mutate` here is dominated by branches that the main test owns.
 *
 * @command  vendor/bin/pest tests/Unit/Support/PersonNameDisplayFormatsAlternateExpansionTest.php
 */
$buildNormalized = fn (): array => PersonNameDisplayFormats::normalizedFromAlternateNameShell(
    ['text' => 'Foo', 'phonetic_text' => 'fu'],
    ['text' => 'Bar', 'phonetic_text' => 'bɑr'],
    ['text' => 'Baz', 'phonetic_text' => 'bæz'],
    ['text' => 'Qux', 'phonetic_text' => 'kʌks'],
    'Jr.'
);

it('keeps the first non-null phonetic per trimmed text', function (): void {
    $map = PersonNameDisplayFormats::mergedPhoneticTextByTrimmedText([
        ['text' => 'Foo', 'phonetic_text' => null],
        ['text' => 'Foo', 'phonetic_text' => 'fu'],
    ]);

    expect($map)->toBe(['Foo' => 'fu']);
});

it('skips the stored option key in the expansion rows', function () use ($buildNormalized): void {
    $normalized = $buildNormalized();

    $opts = PersonNameDisplayFormats::buildOptions($normalized);
    expect($opts)->not->toBeEmpty();

    $skipKey = $opts[0]['key'];

    $allRows = PersonNameDisplayFormats::alternateNameDisplayExpansionRows($normalized, null, false);
    $skippedRows = PersonNameDisplayFormats::alternateNameDisplayExpansionRows($normalized, $skipKey, false);

    expect($skippedRows)->toHaveCount(count($allRows) - 1);
});

it('omits straight double quotes from phonetic rows', function () use ($buildNormalized): void {
    $rows = PersonNameDisplayFormats::alternateNameDisplayExpansionRows($buildNormalized(), null, true);

    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        $phonetic = $row['phonetic_text'];

        if ($phonetic === null) {
            continue;
        }

        expect($phonetic)->not->toContain('"');
    }
});
