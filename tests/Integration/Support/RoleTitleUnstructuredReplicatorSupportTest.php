<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
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
