<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\Isco08FieldtypeStorage;

/**
 * `Isco08FieldtypeStorage`: tier codes and the four-tier chain the ISCO-08
 * fieldtype stores (#130).
 *
 * @command  vendor/bin/pest tests/Unit/Support/Isco08FieldtypeStorageTest.php
 */
it('normalizes one tier code to trimmed text, or null', function (mixed $value, ?string $expected): void {
    expect(Isco08FieldtypeStorage::normalizeTierCode($value))->toBe($expected);
})->with([
    'a code' => ['22', '22'],
    'a padded code' => [' 22 ', '22'],
    'a blank code' => ['   ', null],
    'an integer code' => [22, '22'],
    'a decimal' => [2.5, '2.5'],
    'nothing' => [null, null],
    'the CP sentinel' => [false, null],
    'true' => [true, null],
    'a list' => [['22'], null],
]);

it('keeps the chain up to the first tier that does not nest under the one above', function (array $data, array $expected): void {
    /** @var array<string, mixed> $data */
    expect(Isco08FieldtypeStorage::normalizeChain($data))->toBe($expected);
})->with([
    'a full chain' => [
        ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => '221', 'unit_group' => '2211', 'value' => 'x'],
        ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => '221', 'unit_group' => '2211'],
    ],
    'a minor under another sub-major' => [
        ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => '231', 'unit_group' => '2310'],
        ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => null, 'unit_group' => null],
    ],
    'an unknown minor' => [
        ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => '299'],
        ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => null, 'unit_group' => null],
    ],
    'a unit under another minor' => [
        ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => '221', 'unit_group' => '2310'],
        ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => '221', 'unit_group' => null],
    ],
    'an unknown unit' => [
        ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => '221', 'unit_group' => '2299'],
        ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => '221', 'unit_group' => null],
    ],
]);

it('reads a chain as empty when it has no major tier', function (array $normalized, bool $expected): void {
    /** @var array<string, mixed> $normalized */
    expect(Isco08FieldtypeStorage::isFullyEmpty($normalized))->toBe($expected);
})->with([
    'no chain' => [[], true],
    'a major tier' => [['major_group' => '2'], false],
    'an empty major tier' => [['major_group' => null, 'sub_major_group' => '22'], true],
    'no major tier' => [['sub_major_group' => '22'], true],
]);
