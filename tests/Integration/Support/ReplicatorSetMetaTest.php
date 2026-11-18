<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\ReplicatorSetMeta;

/**
 * A sets config covering every path through the builder: a set's own handle, an
 * integer key, a set that is not an array, fields that are not a list, no fields,
 * an unknown fieldtype that throws, and a handle that is not text.
 *
 * @return array<string, mixed>
 */
function replicatorSetMetaConfig(): array
{
    return ['main' => ['sets' => [
        'listed' => ['handle' => 'own', 'fields' => [['handle' => 'value', 'field' => ['type' => 'text']]]],
        4 => ['fields' => [['handle' => 'flag', 'field' => ['type' => 'toggle']]]],
        'not a set' => 'text',
        'fields not a list' => ['fields' => 'text'],
        'no fields' => ['fields' => []],
        'broken' => ['fields' => [['handle' => 'value', 'field' => ['type' => 'no_such_fieldtype']]]],
        'an object handle' => ['handle' => ['x'], 'fields' => [['handle' => 'value', 'field' => ['type' => 'text']]]],
    ]]];
}

it('builds meta and defaults for each set with fields, under its resolved handle', function (string $parentHandle): void {
    $meta = ReplicatorSetMeta::forSets(replicatorSetMetaConfig(), $parentHandle);

    expect(array_keys($meta['new']))->toBe(['own', 4, 'broken', 'an object handle'])
        ->and($meta['defaults'])->toBe([
            'own' => ['value' => null],
            4 => ['flag' => false],
            'broken' => [],
            'an object handle' => ['value' => null],
        ])
        ->and($meta['new']['broken'])->toBe([])
        ->and(\is_array($meta['new']['own']) ? array_keys($meta['new']['own']) : null)->toBe(['value', '_'])
        ->and(data_get($meta, 'new.4._'))->toBe('_');
})->with([
    'the schema object replicator' => ['schema_property_value'],
    'the role title unstructured replicator' => ['role_title_unstructured_rep'],
]);

it('builds the same meta for either parent handle', function (): void {
    expect(ReplicatorSetMeta::forSets(replicatorSetMetaConfig(), 'schema_property_value'))
        ->toBe(ReplicatorSetMeta::forSets(replicatorSetMetaConfig(), 'role_title_unstructured_rep'));
});

it('builds no meta for a config without a main set list', function (mixed $config): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(ReplicatorSetMeta::forSets($typedConfig, 'schema_property_value'))->toBe(['new' => [], 'defaults' => []]);
})->with([
    'no main' => [[]],
    'a main that is not a list' => [['main' => 'sets']],
    'a main with no sets' => [['main' => ['display' => 'Main']]],
    'sets that are not a list' => [['main' => ['sets' => 'number']]],
]);
