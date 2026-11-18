<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use UAMSWeb\Aether\People\Support\Isco08FieldtypeStorage;
use UAMSWeb\Aether\People\Support\RoleTitleUnstructuredCpBridge as Bridge;
use UAMSWeb\Aether\People\Support\RoleTitleValue;

/**
 * Asserts one replicator row carries the CP set shape around the given inner value, and returns its id.
 *
 * @param  array<string, mixed>|string  $inner
 */
function expectBridgeRow(mixed $row, string $fieldHandle, array|string $inner): string
{
    // Narrow the row before reading its keys
    if (! \is_array($row)) {
        throw new RuntimeException('Expected a replicator row array, got '.get_debug_type($row));
    }

    expect(array_keys($row))->toBe(['type', 'id', 'enabled', $fieldHandle])
        ->and($row['type'])->toBe('row')
        ->and($row['enabled'])->toBeTrue()
        ->and($row[$fieldHandle])->toBe($inner);

    $id = Arr::string($row, 'id');
    expect(Str::isUuid($id))->toBeTrue();

    return $id;
}

it('lists the four CP replicator keys in a fixed order', function (): void {
    expect(Bridge::cpKeys())->toBe([
        '__cp_role_title_unstructured_isco_08',
        '__cp_role_title_unstructured_onet_soc',
        '__cp_role_title_unstructured_occ_same_as',
        '__cp_role_title_unstructured_field_same_as',
    ]);
});

it('recognizes only the CP replicator keys as internal', function (string $key, bool $expected): void {
    expect(Bridge::isCpInternalKey($key))->toBe($expected);
})->with([
    'isco' => [Bridge::CP_REPLICATOR_ISCO, true],
    'onet' => [Bridge::CP_REPLICATOR_ONET, true],
    'occupation same_as' => [Bridge::CP_REPLICATOR_OCC_SAME_AS, true],
    'field same_as' => [Bridge::CP_REPLICATOR_FIELD_SAME_AS, true],
    'stored key' => ['occupation', false],
    'prefix only' => ['__cp_role_title_unstructured_', false],
]);

it('treats only the unstructured mode as unstructured', function (array $payload, bool $expected): void {
    expect(Bridge::payloadIsUnstructured(['name' => 'Nurse'] + $payload))->toBe($expected);
})->with([
    'unstructured' => [['mode' => RoleTitleValue::MODE_UNSTRUCTURED], true],
    'structured' => [['mode' => RoleTitleValue::MODE_STRUCTURED], false],
    'missing mode' => [[], false],
    'null mode' => [['mode' => null], false],
    'uppercase mode' => [['mode' => 'UNSTRUCTURED'], false],
]);

it('leaves a payload that is not unstructured unchanged on expand', function (array $payload): void {
    $payload = ['name' => 'Nurse'] + $payload;

    expect(Bridge::expandForCp($payload))->toBe($payload);
})->with([
    'structured' => [['mode' => 'structured', 'occupation' => ['same_as' => ['https://example.org/a']]]],
    'no mode' => [['occupation' => ['category_code' => ['onet_soc' => ['29-1215.00']]]]],
]);

it('expands unstructured lists into replicator rows, trimming and dropping non-strings and blanks', function (): void {
    $payload = [
        'mode' => 'unstructured',
        'occupation' => [
            'category_code' => [
                'isco_08' => [' 2211 ', '9999', '', 7],
                'onet_soc' => [' 29-1215.00 ', '   ', null, '29-1216.00'],
            ],
            'same_as' => ['https://example.org/occ ', ['nested']],
        ],
        'occupational_field' => [
            'same_as' => ['  https://example.org/field'],
        ],
    ];

    $out = Bridge::expandForCp($payload);

    expect(array_diff_key($out, array_flip(Bridge::cpKeys())))->toBe($payload);

    $isco = Arr::array($out, Bridge::CP_REPLICATOR_ISCO);
    expect($isco)->toHaveCount(2);
    $firstIscoId = expectBridgeRow($isco[0], 'code', [
        'major_group' => '2',
        'sub_major_group' => '22',
        'minor_group' => '221',
        'unit_group' => '2211',
    ]);
    $secondIscoId = expectBridgeRow($isco[1], 'code', Isco08FieldtypeStorage::emptyShape());

    $onet = Arr::array($out, Bridge::CP_REPLICATOR_ONET);
    expect($onet)->toHaveCount(2);
    expectBridgeRow($onet[0], 'code', '29-1215.00');
    expectBridgeRow($onet[1], 'code', '29-1216.00');

    $occ = Arr::array($out, Bridge::CP_REPLICATOR_OCC_SAME_AS);
    expect($occ)->toHaveCount(1);
    expectBridgeRow($occ[0], 'url', 'https://example.org/occ');

    $field = Arr::array($out, Bridge::CP_REPLICATOR_FIELD_SAME_AS);
    expect($field)->toHaveCount(1);
    expectBridgeRow($field[0], 'url', 'https://example.org/field');

    expect($firstIscoId)->not->toBe($secondIscoId);
});

it('expands to empty row lists when a stored path is missing, not a list, or not an array', function (array $payload): void {
    $out = Bridge::expandForCp(['mode' => 'unstructured'] + $payload);

    foreach (Bridge::cpKeys() as $key) {
        expect($out)->toHaveKey($key)
            ->and($out[$key])->toBe([]);
    }
})->with([
    'nothing stored' => [[]],
    'occupation is a string' => [['occupation' => 'x', 'occupational_field' => 'y']],
    'category_code is a string' => [['occupation' => ['category_code' => 'x', 'same_as' => 'y']]],
    'lists are maps' => [[
        'occupation' => [
            'category_code' => ['isco_08' => ['a' => '2211'], 'onet_soc' => ['a' => '29-1215.00']],
            'same_as' => ['a' => 'https://example.org'],
        ],
        'occupational_field' => ['same_as' => ['a' => 'https://example.org']],
    ]],
]);

it('strips CP keys and keeps everything else when collapsing a payload that is not unstructured', function (): void {
    $payload = [
        'mode' => 'structured',
        'occupation' => ['same_as' => ['https://example.org/a']],
        Bridge::CP_REPLICATOR_ISCO => [['type' => 'row', 'code' => ['major_group' => '2']]],
        Bridge::CP_REPLICATOR_ONET => [],
        Bridge::CP_REPLICATOR_OCC_SAME_AS => [],
        Bridge::CP_REPLICATOR_FIELD_SAME_AS => [],
    ];

    expect(Bridge::collapseFromCp($payload))->toBe([
        'mode' => 'structured',
        'occupation' => ['same_as' => ['https://example.org/a']],
    ]);
});

it('collapses replicator rows into stored lists and removes the CP keys', function (): void {
    $payload = [
        'mode' => 'unstructured',
        'name' => 'Nurse',
        Bridge::CP_REPLICATOR_ISCO => [
            'not a row',
            ['type' => 'row'],
            ['type' => 'row', 'code' => '2211'],
            ['type' => 'row', 'code' => ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => '221', 'unit_group' => '2211']],
            ['type' => 'row', 'code' => Isco08FieldtypeStorage::emptyShape()],
            ['type' => 'row', 'code' => ['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => null, 'unit_group' => null]],
        ],
        Bridge::CP_REPLICATOR_ONET => [
            7,
            ['code' => ' 29-1215.00 '],
            ['code' => '  '],
            ['code' => ['29-1216.00']],
            ['url' => '29-1217.00'],
            ['code' => '29-1218.00'],
        ],
        Bridge::CP_REPLICATOR_OCC_SAME_AS => [
            null,
            ['url' => ''],
            ['code' => 'https://example.org/wrong-field'],
            ['url' => ' https://example.org/occ '],
        ],
        Bridge::CP_REPLICATOR_FIELD_SAME_AS => [
            ['url' => 5],
            'not a row',
            ['url' => 'https://example.org/field'],
        ],
    ];

    expect(Bridge::collapseFromCp($payload))->toBe([
        'mode' => 'unstructured',
        'name' => 'Nurse',
        'occupation' => [
            'category_code' => [
                'isco_08' => ['2211', '22'],
                'onet_soc' => ['29-1215.00', '29-1218.00'],
            ],
            'same_as' => ['https://example.org/occ'],
        ],
        'occupational_field' => [
            'same_as' => ['https://example.org/field'],
        ],
    ]);
});

it('collapses rows that are not a list to empty stored lists', function (mixed $rows): void {
    $payload = ['mode' => 'unstructured'];

    foreach (Bridge::cpKeys() as $key) {
        $payload[$key] = $rows;
    }

    expect(Bridge::collapseFromCp($payload))->toBe([
        'mode' => 'unstructured',
        'occupation' => [
            'category_code' => ['isco_08' => [], 'onet_soc' => []],
            'same_as' => [],
        ],
        'occupational_field' => ['same_as' => []],
    ]);
})->with([
    'string' => ['rows'],
    'null' => [null],
    'map' => [['a' => ['code' => '29-1215.00', 'url' => 'https://example.org']]],
]);

it('keeps stored lists when their CP key was not posted, and coerces non-array containers', function (): void {
    $out = Bridge::collapseFromCp([
        'mode' => 'unstructured',
        'occupation' => [
            'category_code' => ['isco_08' => ['2211'], 'onet_soc' => ['29-1215.00']],
            'same_as' => ['https://example.org/occ'],
        ],
        'occupational_field' => ['same_as' => ['https://example.org/field']],
    ]);

    expect($out)->toBe([
        'mode' => 'unstructured',
        'occupation' => [
            'category_code' => ['isco_08' => ['2211'], 'onet_soc' => ['29-1215.00']],
            'same_as' => ['https://example.org/occ'],
        ],
        'occupational_field' => ['same_as' => ['https://example.org/field']],
    ]);

    expect(Bridge::collapseFromCp([
        'mode' => 'unstructured',
        'occupation' => 'x',
        'occupational_field' => 'y',
    ]))->toBe([
        'mode' => 'unstructured',
        'occupation' => ['category_code' => []],
        'occupational_field' => [],
    ]);

    expect(Bridge::collapseFromCp([
        'mode' => 'unstructured',
        'occupation' => ['category_code' => 'x', 'same_as' => ['https://example.org/occ']],
    ]))->toBe([
        'mode' => 'unstructured',
        'occupation' => ['category_code' => [], 'same_as' => ['https://example.org/occ']],
        'occupational_field' => [],
    ]);
});

it('round-trips stored lists through expand and collapse', function (): void {
    $stored = [
        'mode' => 'unstructured',
        'occupation' => [
            'category_code' => [
                'isco_08' => ['2211', '22'],
                'onet_soc' => ['29-1215.00'],
            ],
            'same_as' => ['https://example.org/occ'],
        ],
        'occupational_field' => ['same_as' => ['https://example.org/field']],
    ];

    expect(Bridge::collapseFromCp(Bridge::expandForCp($stored)))->toBe($stored);
});
