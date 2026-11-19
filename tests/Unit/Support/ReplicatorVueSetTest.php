<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\ReplicatorVueSet;

it('carries the resolved handle once, at the top level and at index 0', function (): void {
    $set = ReplicatorVueSet::fromSet('row', [['handle' => 'row'], 'display' => 'Row', 'fields' => []], [['handle' => 'code', 'type' => 'text']]);

    expect($set)->toBe([
        'handle' => 'row',
        0 => ['handle' => 'row'],
        'display' => 'Row',
        'fields' => [['handle' => 'code', 'type' => 'text']],
    ]);
});

it('resolves the handle as text', function (int|string $key, array $set, string $handle): void {
    $vueSet = ReplicatorVueSet::fromSet($key, $set, []);

    expect($vueSet['handle'])->toBe($handle)
        ->and($vueSet[0])->toBe(['handle' => $handle])
        ->and(ReplicatorVueSet::handleFor($key, $set))->toBe($handle);
})->with([
    'the set key when the set names no handle' => ['row', [], 'row'],
    'an integer set key' => [3, [], '3'],
    "the set's own text handle" => ['row', ['handle' => 'named'], 'named'],
    "the set's own numeric handle" => ['row', ['handle' => 7], '7'],
    "the set key when the set's own handle is an array" => ['row', ['handle' => ['x']], 'row'],
    "the set key when the set's own handle is null" => ['row', ['handle' => null], 'row'],
]);

it('keeps every entry that is not a handle', function (): void {
    $set = ReplicatorVueSet::fromSet('row', [
        ['handle' => 'stale'],
        ['handle' => 'kept', 'display' => 'Two keys'],
        ['other' => 'kept'],
        'note' => ['handle' => 'named'],
        'display' => 'Row',
    ], []);

    expect($set)->toBe([
        'handle' => 'row',
        0 => ['handle' => 'row'],
        1 => ['handle' => 'kept', 'display' => 'Two keys'],
        2 => ['other' => 'kept'],
        'note' => ['handle' => 'named'],
        'display' => 'Row',
        'fields' => [],
    ]);
});

it('replaces the set fields with the flattened ones', function (): void {
    $set = ReplicatorVueSet::fromSet('row', ['fields' => [['handle' => 'raw', 'field' => ['type' => 'text']]]], [['handle' => 'raw', 'type' => 'text']]);

    expect($set['fields'])->toBe([['handle' => 'raw', 'type' => 'text']]);
});
