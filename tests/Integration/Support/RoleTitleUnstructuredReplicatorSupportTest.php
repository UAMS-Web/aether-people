<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use UAMSWeb\Aether\People\Support\ReplicatorSetMeta;
use UAMSWeb\Aether\People\Support\ReplicatorVueSet;
use UAMSWeb\Aether\People\Support\RoleTitleUnstructuredReplicatorSupport;

it('carries one handle entry per Vue set in each unstructured replicator', function (string $configKey): void {
    $sets = Arr::array(RoleTitleUnstructuredReplicatorSupport::preloadReplicatorSlice(), $configKey.'.sets.0.sets');

    expect($sets)->not->toBeEmpty()->each->toBeArray();

    /** @var list<array<int|string, mixed>> $sets */
    foreach ($sets as $set) {
        $handleEntries = array_filter(
            $set,
            static fn (mixed $value, mixed $key): bool => \is_int($key) && $value === ['handle' => 'row'],
            ARRAY_FILTER_USE_BOTH
        );

        expect($set['handle'])->toBe('row')
            ->and($set[0])->toBe(['handle' => 'row'])
            ->and($handleEntries)->toHaveCount(1);
    }
})->with([
    'unstructured_replicator_isco_config',
    'unstructured_replicator_onet_config',
    'unstructured_replicator_occ_same_as_config',
    'unstructured_replicator_field_same_as_config',
]);

/**
 * The one-set replicator config each unstructured list builds, around its fields.
 *
 * @param  list<array<string, mixed>>  $fields  The set's fields
 * @return array<string, mixed>
 */
function roleTitleUnstructuredSetsConfig(
    array $fields,
    string $rowLabelKey
): array {
    return ['main' => [
        'display' => __('Main'),
        'sets' => ['row' => [
            ['handle' => 'row'],
            'display' => __('aether-people::fieldtypes.role_title.unstructured.'.$rowLabelKey),
            'fields' => $fields,
        ]],
    ]];
}

/**
 * One required field of the given type and display key.
 *
 * @return list<array<string, mixed>>
 */
function roleTitleUnstructuredRequiredField(
    string $handle,
    string $type,
    string $displayKey
): array {
    return [[
        'handle' => $handle,
        'field' => [
            'type' => $type,
            'display' => __($displayKey),
            'required' => true,
            'validate' => ['required'],
        ],
    ]];
}

it('builds each unstructured replicator sets config in full', function (): void {
    $url = roleTitleUnstructuredRequiredField('url', 'url', 'aether-people::fieldtypes.role_title.unstructured.reference_webpages');

    expect(RoleTitleUnstructuredReplicatorSupport::iscoSetsConfig())->toBe(roleTitleUnstructuredSetsConfig(
        roleTitleUnstructuredRequiredField('code', 'isco_08', 'aether-people::fieldtypes.isco_08.title'),
        'replicator_row_isco_08'
    ))->and(RoleTitleUnstructuredReplicatorSupport::onetSetsConfig())->toBe(roleTitleUnstructuredSetsConfig(
        roleTitleUnstructuredRequiredField('code', 'text', 'aether-people::fieldtypes.role_title.unstructured.onet_soc_code'),
        'replicator_row_onet_soc'
    ))->and(RoleTitleUnstructuredReplicatorSupport::occupationSameAsSetsConfig())->toBe(roleTitleUnstructuredSetsConfig(
        $url,
        'replicator_row_occupation_same_as'
    ))->and(RoleTitleUnstructuredReplicatorSupport::occupationalFieldSameAsSetsConfig())->toBe(roleTitleUnstructuredSetsConfig(
        $url,
        'replicator_row_occupational_field_same_as'
    ));
});

it('preloads each replicator as a Vue config with its add button, beside its set meta', function (string $name, string $setsMethod, string $buttonKey): void {
    $slice = RoleTitleUnstructuredReplicatorSupport::preloadReplicatorSlice();
    /** @var array<string, mixed> $setsConfig */
    $setsConfig = RoleTitleUnstructuredReplicatorSupport::{$setsMethod}();
    /** @var array<int|string, mixed> $set */
    $set = data_get($setsConfig, 'main.sets.row');
    /** @var list<array<string, mixed>> $fields */
    $fields = $set['fields'];

    expect($slice['unstructured_replicator_'.$name.'_config'])->toBe([
        'sets' => [[
            'display' => __('Main'),
            'sets' => [ReplicatorVueSet::fromSet(
                'row',
                $set,
                array_map(
                    static fn (array $field): array => ['handle' => $field['handle'], ...Arr::array($field, 'field')],
                    $fields
                )
            )],
        ]],
        'button_label' => 'aether-people::fieldtypes.role_title.unstructured.'.$buttonKey,
    ])->and($slice['unstructured_replicator_'.$name.'_meta'])
        ->toBe(ReplicatorSetMeta::forSets($setsConfig, 'role_title_unstructured_rep'))
        ->and($slice['unstructured_replicator_'.$name.'_meta'])->not->toBe(['new' => [], 'defaults' => []]);
})->with([
    'ISCO-08 codes' => ['isco', 'iscoSetsConfig', 'add_row_isco_08'],
    'O*NET-SOC codes' => ['onet', 'onetSetsConfig', 'add_row_onet_soc'],
    'occupation references' => ['occ_same_as', 'occupationSameAsSetsConfig', 'add_row_occupation_same_as'],
    'occupational field references' => ['field_same_as', 'occupationalFieldSameAsSetsConfig', 'add_row_occupational_field_same_as'],
]);

it('preloads exactly the four configs and their meta', function (): void {
    expect(array_keys(RoleTitleUnstructuredReplicatorSupport::preloadReplicatorSlice()))->toBe([
        'unstructured_replicator_isco_config',
        'unstructured_replicator_isco_meta',
        'unstructured_replicator_onet_config',
        'unstructured_replicator_onet_meta',
        'unstructured_replicator_occ_same_as_config',
        'unstructured_replicator_occ_same_as_meta',
        'unstructured_replicator_field_same_as_config',
        'unstructured_replicator_field_same_as_meta',
    ]);
});
