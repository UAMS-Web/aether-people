<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

/**
 * Builds one replicator set in the shape the Control Panel's Vue replicator reads.
 *
 * Shared by the host's schema property replicator sets and
 * `RoleTitleUnstructuredReplicatorSupport`, which each build a replicator
 * `config.sets` payload of their own. The handle rules live here so the two
 * cannot drift apart again (UAMS-Web/uams-statamic-archive#3011, UAMS-Web/uams-statamic-archive#3249, UAMS-Web/uams-statamic-archive#3253).
 */
final class ReplicatorVueSet
{
    /**
     * Resolve a set's handle as text: its own `handle` when that is a scalar, otherwise its key.
     *
     * The one home of this rule; {@see ReplicatorSetMeta::forSets()} keys a set's meta by it too.
     *
     * @param  int|string  $key  The set's key in the replicator's `sets` map
     * @param  array<int|string, mixed>  $set  The set config
     */
    public static function handleFor(
        int|string $key,
        array $set
    ): string {
        return isset($set['handle']) && \is_scalar($set['handle'])
            ? (string) $set['handle']
            : (string) $key;
    }

    /**
     * Build a Vue set from a set config, carrying its handle exactly once.
     *
     * The handle is always text: the set's own `handle` when it is a scalar,
     * otherwise the set's key. The set's own `handle` key and any
     * numbered `['handle' => ...]` entry are dropped, so the resolved handle
     * appears once at the top level and once at index 0. Every other entry,
     * including a string-keyed value that happens to be a handle array, is kept.
     *
     * @param  int|string  $key  The set's key in the replicator's `sets` map
     * @param  array<int|string, mixed>  $set  The set config
     * @param  list<array<mixed>>  $fields  The set's fields, already flattened for Vue
     * @return array<int|string, mixed>
     */
    public static function fromSet(
        int|string $key,
        array $set,
        array $fields
    ): array {
        // Resolve the handle as text from the set's own scalar handle, or else its key
        $handle = self::handleFor($key, $set);

        // Drop the set's own handle key and its numbered handle entry, so the resolved handle is the only one
        $body = array_filter(
            [
                ...$set,
                'fields' => $fields,
            ],
            static fn (mixed $value, int|string $entryKey): bool => $entryKey !== 'handle'
                && ! (\is_int($entryKey) && \is_array($value) && array_keys($value) === ['handle']),
            ARRAY_FILTER_USE_BOTH
        );

        // Top-level 'handle' so Vue and SetPicker get item.handle; otherwise the set is null in POST
        return [
            'handle' => $handle,
            ['handle' => $handle],
            ...$body,
        ];
    }
}
