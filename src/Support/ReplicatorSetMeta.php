<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Illuminate\Support\Collection;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Fieldtype;

/**
 * Builds the `new` and `defaults` meta a Control Panel replicator reads to add a set without a request.
 *
 * Shared by the host's schema property replicator sets and
 * `RoleTitleUnstructuredReplicatorSupport`, which each pass their own synthetic
 * parent field handle (UAMS-Web/uams-statamic-archive#3253). Each set's handle comes from
 * {@see ReplicatorVueSet::handleFor()}, the same rule the Vue payload uses.
 */
final class ReplicatorSetMeta
{
    /**
     * Build the replicator meta for every set in the `main` group that has fields.
     *
     * A set that is not an array, or whose fields are not a non-empty list, is
     * skipped. A set whose fields cannot be built (an unknown fieldtype, or a
     * fieldtype that throws) is kept with empty meta and empty defaults.
     *
     * @param  array<string, mixed>  $setsConfig  A replicator sets config: `main` => [`display`, `sets` => [key => set config]]
     * @param  string  $parentHandle  The handle of the synthetic replicator field the sets belong to
     * @return array{new: array<string, mixed>, defaults: array<string, array<string, mixed>>}
     */
    public static function forSets(
        array $setsConfig,
        string $parentHandle
    ): array {
        $main = $setsConfig['main'] ?? null;
        if (! \is_array($main) || ! isset($main['sets']) || ! \is_array($main['sets'])) {
            return ['new' => [], 'defaults' => []];
        }

        $new = [];
        $defaults = [];
        // No set field either replicator builds reads its parent field's type, so meta and defaults come out the same without it (measured)
        // @pest-mutate-ignore: RemoveArrayItem
        $parentField = new Field($parentHandle, ['type' => 'replicator']);

        /** @var array<int|string, mixed> $sets */
        $sets = $main['sets'];
        foreach ($sets as $key => $setConfig) {
            // Skip a set with no usable fields before resolving its handle
            if (! \is_array($setConfig)) {
                continue;
            }

            $fieldsConfig = $setConfig['fields'] ?? [];
            if (! \is_array($fieldsConfig)) {
                continue;
            }

            if ($fieldsConfig === []) {
                continue;
            }

            // Resolve the handle by the rule the Vue payload uses
            $handle = ReplicatorVueSet::handleFor($key, $setConfig);

            try {
                $replicatorFields = new Fields(
                    $fieldsConfig,
                    null,
                    $parentField,
                    // No set field either replicator builds reads the parent index, so meta and defaults come out the same for any index (measured)
                    // @pest-mutate-ignore: DecrementInteger, IncrementInteger
                    -1
                );

                /** @var Collection<int|string, Field> $allFields */
                $allFields = $replicatorFields->all();
                /** @var array<string, mixed> $defaultValues */
                $defaultValues = $allFields
                    ->mapWithKeys(function (Field $field): array {
                        $rawHandle = $field->handle();
                        $fieldHandle = \is_scalar($rawHandle) ? (string) $rawHandle : '';
                        // Field::fieldtype() returns FieldtypeRepository::find()->setField(), which is
                        // a Fieldtype or throws, and the catch below empties the set on a throw; a
                        // runtime check here would have no input that reaches its other arm
                        /** @var Fieldtype $fieldtype */
                        $fieldtype = $field->fieldtype();

                        return [
                            $fieldHandle => $fieldtype->preProcess(
                                $field->defaultValue()
                            ),
                        ];
                    })
                    ->all();

                $defaults[$handle] = $defaultValues;
                /** @var Fields $fieldsWithValues */
                $fieldsWithValues = $replicatorFields->addValues($defaultValues);
                /** @var Collection<int|string, mixed> $meta */
                $meta = $fieldsWithValues->meta();
                $new[$handle] = $meta
                    ->put('_', '_')
                    ->toArray();
            } catch (\Throwable) {
                $defaults[$handle] = [];
                $new[$handle] = [];
            }
        }

        return ['new' => $new, 'defaults' => $defaults];
    }
}
