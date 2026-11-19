<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Statamic\Fields\Field;
use Statamic\Fields\Fields;

/**
 * Replicator `config.sets` + `meta` for unstructured `role_title` list editors.
 */
final class RoleTitleUnstructuredReplicatorSupport
{
    /**
     * @return array<string, mixed>
     */
    public static function iscoSetsConfig(): array
    {
        return self::wrapMain(self::rowSet(
            [
                [
                    'handle' => 'code',
                    'field' => [
                        'type' => 'isco_08',
                        'display' => __('aether-people::fieldtypes.isco_08.title'),
                        'required' => true,
                        'validate' => [
                            'required',
                        ],
                    ],
                ],
            ],
            __('aether-people::fieldtypes.role_title.unstructured.replicator_row_isco_08')
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function onetSetsConfig(): array
    {
        return self::wrapMain(self::rowSet(
            [
                [
                    'handle' => 'code',
                    'field' => [
                        'type' => 'text',
                        'display' => __('aether-people::fieldtypes.role_title.unstructured.onet_soc_code'),
                        'required' => true,
                        'validate' => [
                            'required',
                        ],
                    ],
                ],
            ],
            __('aether-people::fieldtypes.role_title.unstructured.replicator_row_onet_soc')
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function occupationSameAsSetsConfig(): array
    {
        return self::wrapMain(self::rowSet(
            self::unstructuredOccupationReferenceUrlFields(),
            __('aether-people::fieldtypes.role_title.unstructured.replicator_row_occupation_same_as')
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function occupationalFieldSameAsSetsConfig(): array
    {
        return self::wrapMain(self::rowSet(
            self::unstructuredOccupationReferenceUrlFields(),
            __('aether-people::fieldtypes.role_title.unstructured.replicator_row_occupational_field_same_as')
        ));
    }

    /**
     * Nested fields for occupation / occupational-field “sameAs” URL replicator rows.
     *
     * @return list<array<string, mixed>>
     */
    private static function unstructuredOccupationReferenceUrlFields(): array
    {
        return [
            [
                'handle' => 'url',
                'field' => [
                    'type' => 'url',
                    'display' => __('aether-people::fieldtypes.role_title.unstructured.reference_webpages'),
                    'required' => true,
                    'validate' => [
                        'required',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{
     *   unstructured_replicator_isco_config: array<string, mixed>,
     *   unstructured_replicator_isco_meta: array{new: array<string, mixed>, defaults: array<string, mixed>},
     *   unstructured_replicator_onet_config: array<string, mixed>,
     *   unstructured_replicator_onet_meta: array{new: array<string, mixed>, defaults: array<string, mixed>},
     *   unstructured_replicator_occ_same_as_config: array<string, mixed>,
     *   unstructured_replicator_occ_same_as_meta: array{new: array<string, mixed>, defaults: array<string, mixed>},
     *   unstructured_replicator_field_same_as_config: array<string, mixed>,
     *   unstructured_replicator_field_same_as_meta: array{new: array<string, mixed>, defaults: array<string, mixed>}
     * }
     */
    public static function preloadReplicatorSlice(): array
    {
        $iscoCfg = self::iscoSetsConfig();
        $onetCfg = self::onetSetsConfig();
        $occCfg = self::occupationSameAsSetsConfig();
        $fieldCfg = self::occupationalFieldSameAsSetsConfig();

        return [
            'unstructured_replicator_isco_config' => self::configSetsForVue(
                $iscoCfg,
                'aether-people::fieldtypes.role_title.unstructured.add_row_isco_08'
            ),
            'unstructured_replicator_isco_meta' => self::metaForSets($iscoCfg),
            'unstructured_replicator_onet_config' => self::configSetsForVue(
                $onetCfg,
                'aether-people::fieldtypes.role_title.unstructured.add_row_onet_soc'
            ),
            'unstructured_replicator_onet_meta' => self::metaForSets($onetCfg),
            'unstructured_replicator_occ_same_as_config' => self::configSetsForVue(
                $occCfg,
                'aether-people::fieldtypes.role_title.unstructured.add_row_occupation_same_as'
            ),
            'unstructured_replicator_occ_same_as_meta' => self::metaForSets($occCfg),
            'unstructured_replicator_field_same_as_config' => self::configSetsForVue(
                $fieldCfg,
                'aether-people::fieldtypes.role_title.unstructured.add_row_occupational_field_same_as'
            ),
            'unstructured_replicator_field_same_as_meta' => self::metaForSets($fieldCfg),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  string  $setDisplay  Translated replicator set row label (CP)
     * @return array<string, mixed>
     */
    private static function rowSet(
        array $fields,
        string $setDisplay
    ): array {
        return [
            'row' => [
                ['handle' => 'row'],
                'display' => $setDisplay,
                'fields' => $fields,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $sets
     * @return array<string, mixed>
     */
    private static function wrapMain(array $sets): array
    {
        return [
            'main' => [
                'display' => __('Main'),
                'sets' => $sets,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $setsConfig
     * @return array{new: array<string, mixed>, defaults: array<string, mixed>}
     */
    private static function metaForSets(array $setsConfig): array
    {
        return ReplicatorSetMeta::forSets($setsConfig, 'role_title_unstructured_rep');
    }

    /**
     * @param  array<string, mixed>  $setsConfig
     * @param  string  $buttonLabelKey  Statamic translation key for the replicator “Add” button (passed to `__()` in the CP)
     * @return array<string, mixed>
     */
    private static function configSetsForVue(
        array $setsConfig,
        string $buttonLabelKey
    ): array {
        $main = $setsConfig['main'] ?? null;

        // Unreachable: every caller passes a config wrapMain() built, whose `main` always holds a `sets` array
        // @pest-mutate-ignore: BooleanOrToBooleanAnd, RemoveArrayItem, RemoveEarlyReturn
        if (! \is_array($main) || ! isset($main['sets']) || ! \is_array($main['sets'])) {
            return ['sets' => []];
        }

        $setsArray = [];

        /** @var array<array-key, mixed> $sets */
        $sets = $main['sets'];

        foreach ($sets as $handle => $set) {
            $set = \is_array($set) ? $set : [];
            $fields = [];

            $setFields = $set['fields'] ?? [];

            if (\is_array($setFields)) {
                foreach ($setFields as $field) {
                    $field = \is_array($field) ? $field : [];
                    $nestedField = $field['field'] ?? [];
                    // Equivalent for the '' fallback: every field this class builds declares its handle
                    // @pest-mutate-ignore: EmptyStringToNotEmpty
                    $fields[] = [
                        'handle' => $field['handle'] ?? '',
                        ...(\is_array($nestedField) ? $nestedField : []),
                    ];
                }
            }

            // Carry the resolved handle once, at the top level and at index 0
            $setsArray[] = ReplicatorVueSet::fromSet($handle, $set, $fields);
        }

        // Equivalent for the fallback: wrapMain() always sets `display` to the same translated `Main`
        // @pest-mutate-ignore: CoalesceRemoveLeft
        $out = [
            'sets' => [
                [
                    'display' => $main['display'] ?? __('Main'),
                    'sets' => $setsArray,
                ],
            ],
        ];

        $out['button_label'] = $buttonLabelKey;

        return $out;
    }
}
