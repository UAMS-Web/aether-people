<?php

declare(strict_types=1);

/**
 * Canonical role_title definition (v3): unified `subfields` tree.
 *
 * Labels for CP / validation resolve from `lang/.../fieldtypes.php` via
 * `role_title.field.option.{key}`. `UAMSWeb\Aether\People\Support\RoleTitleDefinition` reads this
 * file and derives union order, per-mode checkbox lists, radio keys, inactive
 * clears, and preload maps from `available_in` (empty means all configurations).
 *
 * `configurations.handles` is an ordered list of stored `configuration` values.
 * `configurations.default` is the stored handle used when a blueprint does not
 * pick a default mode (must appear in `handles`).
 * CP `option_label_maps`: each preload slot is a list of `{ key, label,
 * available_in }` rows (`available_in` empty = all configured modes). The Vue
 * fieldtype filters rows by the active `configuration`.
 * Each `subfields` entry’s map key is the stored field name (e.g. `role`,
 * `title_options`). The `value` row uses `composed_line` (composed one-line
 * title; empty template is null). Title Options key order is the insertion
 * order of `subfields.title_options.options`.
 *
 * Each top-level `radios` / `checkboxes` row is one preload slot (`preload_slot`
 * overrides the subfield key); each nested `radios` / `checkboxes` under
 * `group` is one slot (defaults to the nested key, e.g. `scope_joiner`).
 *
 * Top-level `radios` rows may set `validation.per_configuration_options` to
 * true so `RoleTitlePerConfigurationOptionRule` validates the stored key
 * against `mapOptionKeysByConfigurationHandles()` for the active `configuration`.
 *
 * Stored empty shapes follow each row’s `type` in PHP (`RoleTitleDefinition`):
 * `composed_line` uses `default` (typically null); `checkboxes` / `group`
 * resolve to array defaults; optional radios may use `default` null. For
 * `checkboxes`, omit `default`, use null, or `[]` to mean
 * “every option key false”; a non-empty `default` map is used as-is. Groups
 * use nested `fields` defaults only (no top-level `default` on the group row).
 *
 * CP field chrome: optional `cp_label` (full `__()` key) or
 * `cp_label_lang_suffix` (suffix under `aether-people::fieldtypes.role_title.field`)
 * on any `checkboxes` / `radios` row, or on nested `radios` / `text` under a
 * `group`. When both are omitted, the storage key is used as the lang suffix.
 * Nested labels are exposed on preload as `cp_nested_field_labels` keyed
 * `{group}.{nested}` (e.g. `scope.scope_joiner`).
 *
 * A nested `entries` row under a `group` stores one linked entry id (or null)
 * and carries its Statamic `entries` field config under `field`; the CP renders
 * it with the stock relationship picker (see `RoleTitleScopeUnit`).
 */

return [

    /**
     * Integer marker for the config shape consumed by `RoleTitleDefinition`.
     * Version `3` denotes the unified `subfields` tree and related derivation
     * rules in application code and tests.
     */
    'schema_version' => 3,

    /**
     * Special string values used across the CP and persistence layer.
     * `optional_radio_none` is the sentinel optional radios use for "no
     * selection" before `RoleTitle` normalizes it to null on save.
     */
    'sentinel' => [
        'optional_radio_none' => '__role_title_none__',
    ],

    /**
     * Stored `configuration` mode handles, default mode, and validation wiring.
     * `handles` is canonical order for UI, preload, and inactive clears;
     * `default` must appear in `handles`. `root_field_validation_suffixes`
     * maps nested validation attribute suffixes for the composed line and
     * configuration picker under `lang/.../fieldtypes.php`.
     */
    'configurations' => [
        'handles' => [
            'faculty',
            'academic_leadership',
            'institutional_leadership',
            'clinical_leadership',
        ],
        'default' => 'faculty',
        'root_field_validation_suffixes' => [
            'configuration' => 'configuration',
            'value' => 'composed_value',
        ],
    ],

    /**
     * Unified storage map for the `role_title` field: each key is a stored
     * subfield name. Row `type` drives defaults, CP editor blocks, preload slots,
     * compose order, and validation. Option maps use `available_in` per option
     * or subfield row to scope modes; `position` on checkbox options affects
     * composed title segment order (`prefix`, `suffix`, or inline).
     */
    'subfields' => [

        /**
         * Composed one-line title. `composed_line` stores the template output;
         * an empty template is null. Row `available_in` empty applies to every
         * configuration.
         */
        'value' => [
            'type' => 'composed_line',
            'default' => null,
            'available_in' => [],
        ],

        /**
         * Selector for title modifier options (e.g., interim, visiting, emeritus, etc.).
         *
         * The order is intentionally not alphabetical to match the order of natural
         * language when composing the title.
         *
         * The row `available_in` limits the block to specific configuration modes.
         *
         * Each option sets `position` (`prefix` or `suffix`) for composition order and
         * its own `available_in` to limit which modes list it.
         *
         * Control panel label is set by `cp_label_lang_suffix`.
         */
        'title_options' => [
            'type' => 'checkboxes',
            'cp_label_lang_suffix' => 'toggle_options',
            'default' => null,
            'available_in' => [],
            'options' => [
                'interim' => [
                    'position' => 'prefix',
                    'available_in' => [
                        'academic_leadership',
                        'institutional_leadership',
                        'clinical_leadership',
                    ],
                ],
                'co_prefix' => [
                    'position' => 'prefix',
                    'available_in' => [
                        'academic_leadership',
                        'institutional_leadership',
                        'clinical_leadership',
                    ],
                ],
                'regional' => [
                    'position' => 'prefix',
                    'available_in' => [
                        'academic_leadership',
                        'institutional_leadership',
                    ],
                ],
                'senior' => [
                    'position' => 'prefix',
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'executive' => [
                    'position' => 'prefix',
                    'available_in' => [
                        'academic_leadership',
                        'institutional_leadership',
                    ],
                ],
                'visiting' => [
                    'position' => 'prefix',
                    'available_in' => [
                        'faculty',
                    ],
                ],
                'distinguished' => [
                    'position' => 'prefix',
                    'available_in' => [
                        'faculty',
                    ],
                ],
                'adjunct' => [
                    'position' => 'prefix',
                    'available_in' => [
                        'faculty',
                    ],
                ],
            ],
        ],

        /**
         * Selector for scope qualifier wording (e.g., "clinical", "research") meant to
         * broadly qualify the scope of the  role (e.g., "clinical professor" and
         * "research instructor").
         *
         * The row `available_in` limits the block to specific configuration modes.
         *
         * With `allow_optional_none`, editors may clear the slot to the sentinel then
         * null on save.
         *
         * Each option sets its own `available_in` to limit which modes list it.
         */
        'scope_qualifier' => [
            'type' => 'radios',
            'allow_optional_none' => true,
            'default' => null,
            'available_in' => [
                'faculty',
            ],
            'options' => [
                'clinical' => [
                    'available_in' => [],
                ],
                'research' => [
                    'available_in' => [],
                ],
            ],
        ],

        /**
         * Selector for hierarchical modifier wording (e.g., "associate",  "assistant"),
         * meant to indicate the hierarchical rank of the role.
         *
         * The row `available_in` limits the block to specific configuration modes.
         *
         * With `allow_optional_none`, editors may clear the slot to the sentinel then
         * null on save.
         *
         * Each option sets its own `available_in` to limit which modes list it.
         */
        'hierarchical_modifier' => [
            'type' => 'radios',
            'allow_optional_none' => true,
            'default' => null,
            'available_in' => [],
            'options' => [
                'associate' => [
                    'available_in' => [],
                ],
                'assistant' => [
                    'available_in' => [],
                ],
            ],
        ],

        /**
         * Selector for indicating the object led (e.g., "department", "division").
         *
         * The row `available_in` limits the block to specific configuration modes.
         *
         * With `allow_optional_none`, editors may clear the slot to the sentinel then
         * null on save.
         *
         * Each option sets its own `available_in` to limit which modes list it.
         */
        'object' => [
            'type' => 'radios',
            'allow_optional_none' => true,
            'default' => null,
            'available_in' => [
                'academic_leadership',
                'clinical_leadership',
            ],
            // Each option names its configurations explicitly. An empty list means every
            // mode the row is available in, so the academic objects would otherwise appear
            // under clinical leadership the moment that handle joined the row (UAMS-Web/uams-statamic-archive#2143).
            'options' => [
                'department' => [
                    'available_in' => [
                        'academic_leadership',
                    ],
                ],
                'division' => [
                    'available_in' => [
                        'academic_leadership',
                    ],
                ],
                'fellowship_program' => [
                    'available_in' => [
                        'academic_leadership',
                    ],
                ],
                'medical_student_clerkship' => [
                    'available_in' => [
                        'academic_leadership',
                    ],
                ],
                'residency_program' => [
                    'available_in' => [
                        'academic_leadership',
                    ],
                ],
                'section' => [
                    'available_in' => [
                        'academic_leadership',
                    ],
                ],
                'service_line' => [
                    'available_in' => [
                        'clinical_leadership',
                    ],
                ],
                'training' => [
                    'available_in' => [
                        'academic_leadership',
                    ],
                ],
            ],
        ],

        /**
         * Selector for positional qualifier wording (e.g., "deputy", "vice"), meant to be
         * a qualifier for the role (e.g., "deputy chair" and "vice chancellor").
         *
         * The row `available_in` limits the block to specific configuration modes.
         *
         * With `allow_optional_none`, editors may clear the slot to the sentinel then
         * null on save.
         *
         * Each option sets its own `available_in` to limit which modes list it.
         */
        'positional_qualifier' => [
            'type' => 'radios',
            'allow_optional_none' => true,
            'default' => null,
            'available_in' => [
                'academic_leadership',
                'institutional_leadership',
            ],
            'options' => [
                'deputy' => [
                    'available_in' => [],
                ],
                'vice' => [
                    'available_in' => [],
                ],
            ],
        ],

        /**
         * Selector for role wording (e.g., "professor", "chair", "provost"), meant to
         * indicate the role of the person (e.g., "department chair").
         *
         * The row `available_in` limits the block to specific configuration modes.
         *
         * `validation.per_configuration_options` enables rules that validate
         * the stored key against the active `configuration`.
         *
         * With `allow_optional_none` set to false, the slot is required and cannot be
         * cleared to the sentinel then null on save.
         *
         * Each option sets its own `available_in` to limit which modes list it.
         */
        'role' => [
            'type' => 'radios',
            'allow_optional_none' => false,
            'default' => null,
            'available_in' => [],
            'validation' => [
                'per_configuration_options' => true,
            ],
            'options' => [
                'chair' => [
                    'available_in' => [
                        'academic_leadership',
                    ],
                ],
                'chairperson' => [
                    'available_in' => [
                        'academic_leadership',
                    ],
                ],
                'chairman' => [
                    'available_in' => [
                        'academic_leadership',
                    ],
                ],
                'chairwoman' => [
                    'available_in' => [
                        'academic_leadership',
                    ],
                ],
                'chancellor' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_communications_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_executive_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_financial_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_human_resources_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_information_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_legal_counsel' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_legal_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_marketing_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_of_staff' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_operating_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_people_and_culture_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_strategy_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'chief_technology_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'dean' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'administrator' => [
                    'available_in' => [
                        'clinical_leadership',
                    ],
                ],
                'director' => [
                    'available_in' => [
                        'academic_leadership',
                        'clinical_leadership',
                        'institutional_leadership',
                    ],
                ],
                'medical_director' => [
                    'available_in' => [
                        'clinical_leadership',
                    ],
                ],
                'nursing_director' => [
                    'available_in' => [
                        'clinical_leadership',
                    ],
                ],
                'general_counsel' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'instructor' => [
                    'available_in' => [
                        'faculty',
                    ],
                ],
                'lecturer' => [
                    'available_in' => [
                        'faculty',
                    ],
                ],
                'president' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'professor' => [
                    'available_in' => [
                        'faculty',
                    ],
                ],
                'provost' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],
                'public_relations_officer' => [
                    'available_in' => [
                        'institutional_leadership',
                    ],
                ],

            ],
        ],

        /**
         * Single-choice honorific (`emeritus` / `emerita`), composed immediately
         * after the role in every configuration that offers it. A radios row makes
         * the two gendered forms structurally exclusive -- one value or none -- where
         * the former `title_options` checkboxes allowed both at once and composed in
         * list order rather than after the role (UAMS-Web/uams-statamic-archive#2138).
         *
         * `allow_optional_none` lets an editor clear the slot to the sentinel, nulled
         * on save. Clinical leadership offers no honorific and omits this row.
         */
        'honorific' => [
            'type' => 'radios',
            'allow_optional_none' => true,
            'default' => null,
            'available_in' => [
                'faculty',
                'academic_leadership',
                'institutional_leadership',
            ],
            'options' => [
                'emeritus' => [
                    'available_in' => [],
                ],
                'emerita' => [
                    'available_in' => [],
                ],
            ],
        ],

        /**
         * Nested `group` for leadership scope phrasing: a preposition
         * (`scope_joiner`), then either a linked unit (`scope_unit`, which always
         * takes `the`) or free text (`scope_name`) with its own article choice
         * (`scope_article`).
         * `required_with_nested_field` requires a joiner when sibling
         * `scope_name` is non-empty.
         *
         * The row `available_in` limits the block to specific configuration modes.
         */
        'scope' => [
            'type' => 'group',
            'available_in' => [
                'academic_leadership',
                'clinical_leadership',
                // Faculty carries a scope more often than any other configuration
                // and had no slot for one (UAMS-Web/uams-statamic-archive#2145): `professor in the Department of
                // Internal Medicine`, `professor emeritus of medicine`. Measured at
                // 68 exports for `professor in the department of …` and 101 for
                // `professor of <discipline>`.
                'faculty',
                'institutional_leadership',
            ],
            'fields' => [
                'scope_joiner' => [
                    'type' => 'radios',
                    'allow_optional_none' => false,
                    // Laravel: joiner is required when sibling `scope_name` is non-empty.
                    'required_with_nested_field' => 'scope_name',
                    'default' => 'for',
                    'available_in' => [],
                    /**
                     * The joiner is the preposition alone (UAMS-Web/uams-statamic-archive#2546). The article is
                     * not part of it: a linked unit always takes `the`, and a typed
                     * scope carries its own `scope_article` choice.
                     *
                     * Before UAMS-Web/uams-statamic-archive#2546 the joiner stored the preposition and the article
                     * as one value (`for_without_the`, `of_with_the`, `in_the`, ...),
                     * which let a row link a unit while choosing an article-less
                     * joiner. `role-title:collapse-scope-joiners` migrates stored
                     * values.
                     */
                    'options' => [
                        'for' => [
                            'available_in' => [],
                        ],
                        'of' => [
                            'available_in' => [],
                        ],
                        // ---
                        // `in` introduces only units: `in the UAMS College of
                        // Medicine` outruns the bare form 8,687 to 26 in the exports.
                        // A typed `in` scope must therefore carry the article, which
                        // `requires_article` declares and `RoleTitleScopeJoinerRule`
                        // enforces; a linked one carries it already.
                        // ---
                        'in' => [
                            'available_in' => [],
                            'requires_article' => true,
                        ],
                    ],
                ],
                /**
                 * The article a TYPED scope composes with (UAMS-Web/uams-statamic-archive#2546): `the`, or none.
                 *
                 * It is stored separately from the typed name rather than folded
                 * into it, so a name stays a name. A linked unit ignores it and
                 * always composes with `the`, and linking a unit clears it on save.
                 */
                'scope_article' => [
                    'type' => 'radios',
                    'allow_optional_none' => true,
                    'default' => null,
                    'available_in' => [],
                    'options' => [
                        'the' => [
                            'available_in' => [],
                        ],
                    ],
                ],
                /**
                 * The organizational unit a unit-naming scope names, as a link
                 * rather than typed text (UAMS-Web/uams-statamic-archive#2146). When set, the composed scope
                 * reads the unit's display name at compose time, so renaming the
                 * unit renames every title that links it, and `scope_name` is
                 * cleared on save so the two can never disagree.
                 *
                 * A discipline or portfolio scope (`professor of pediatrics`)
                 * has no `organizations` row to link and stays free text in
                 * `scope_name`.
                 *
                 * `field` is the Statamic `entries` config the CP picker and the
                 * existence rule are built from. `replaces_nested_field` names the
                 * siblings a link supersedes: each is cleared whenever a link is set.
                 */
                'scope_unit' => [
                    'type' => 'entries',
                    'default' => null,
                    'available_in' => [],
                    'replaces_nested_field' => [
                        'scope_name',
                        'scope_article',
                    ],
                    'field' => [
                        'collections' => [
                            'organizations',
                        ],
                        'max_items' => 1,
                        'create' => false,
                        'select_across_sites' => true,
                        'mode' => 'default',
                    ],
                ],
                'scope_name' => [
                    'type' => 'text',
                    'default' => '',
                    'available_in' => [],
                ],
            ],
        ],
    ],

    /**
     * Instructions for how to compute the relevant schema property values based on
     * the selections in the `role_title` fieldtype.
     *
     * Define a new array for each `role_title` scenario. The array should have the
     * following structure:
     *
     * [
     *     'conditions' => [
     *         'role' => [
     *             [
     *                 'operator' => 'equals',
     *                 'value' => 'chancellor',
     *             ],
     *         ],
     *     ],
     *     'output' => [
     *         'occupation' => [
     *             'same_as' => [
     *                 'https://www.wikidata.org/wiki/Q61061', // 'chancellor' (leader of a university or a college)
     *             ],
     *             'category_code' => [
     *                 'onet_soc' => [
     *                     //
     *                 ],
     *                 'isco_08' => [
     *                     //
     *                 ],
     *             ],
     *         ],
     *         'occupational_field' => [
     *             'same_as' => [
     *                 //
     *             ],
     *             'category_code' => [
     *                 //
     *             ],
     *         ],
     *     ],
     * ]
     *
     * Supported operators include:
     *   - equals (expects string for `value`)
     *   - not_equals (expects string for `value`)
     *   - equals_any (expects list<string> for `value`)
     *   - not_equals_any (expects list<string> for `value`)
     *   - equals_all (expects list<string> for `value`)
     *   - not_equals_all (expects list<string> for `value`)
     *   - contains_any (expects list<string> for `value`)
     *   - not_contains_any (expects list<string> for `value`)
     *   - contains_all (expects list<string> for `value`)
     *   - not_contains_all (expects list<string> for `value`)
     *
     * 'output.same_as'
     *   - `output.same_as` is an array of key-value pairs.
     *   - The expected keys are 'occupation' and 'occupational_field'.
     *   - The expected values are a list of URLs as strings that would each satisfy
     *     https://schema.org/sameAs.
     *       - The value of the 'occupation' key should be URLs of reference webpages
     *         that unambiguously indicate the identify of the role's occupation
     *         (e.g., web developer).
     *       - The value of the 'occupation' key should be URLs of reference webpages
     *         that unambiguously indicate the identify of the role's occupational
     *         field  (e.g., web development).
     *
     * 'output.occupation'
     *   - 'output.occupation' is an array of key-value pairs representing the role's
     *     occupation.
     *   - The expected keys are 'same_as' and 'category_code'.
     *
     * 'output.occupation.same_as'
     *   - 'output.occupation.same_as' is an array of key-value pairs.
     *   - The values are lists of URLs as strings that would each satisfy
     *     https://schema.org/sameAs.
     *
     * 'output.occupation.category_code'
     *   - 'output.occupation.category_code' is an array of key-value pairs.
     *   - The keys are meant to represent specific controlled vocabularies for
     *     classifying occupations.
     *   - The values are lists of code values for items in the respective controlled
     *     vocabularies representing the role's occupation.
     *
     * 'output.occupation.category_code.onet_soc'
     *   - 'output.occupation.category_code.onet_soc' is a list of code value strings from the
     *     O*Net-SOC controlled vocabulary.
     *   - Each code value should be for a specific item in the controlled vocabulary
     *     representing the role's occupation.
     *
     * 'output.occupation.category_code.isco_08'
     *   - 'output.occupation.category_code.isco_08' is a list of code value strings from the ISCO-08
     *     controlled vocabulary.
     *   - Each code value should be for a specific item in the controlled vocabulary
     *     representing the role's occupation.
     *
     * 'output.occupational_field'
     *   - 'output.occupational_field' is an array of key-value pairs representing the role's
     *     occupational field.
     *   - The expected keys are 'same_as' and 'category_code'.
     *
     * 'output.occupational_field.same_as'
     *   - 'output.occupational_field.same_as' is an array of key-value pairs.
     *   - The values are lists of URLs as strings that would each satisfy
     *     https://schema.org/sameAs.
     *
     * 'output.occupational_field.category_code'
     *   - 'output.occupational_field.category_code' is an array of key-value pairs.
     *   - The keys are meant to represent specific controlled vocabularies for
     *     classifying occupational fields.
     *   - The values are lists of code values (from each controlled vocabulary)
     *     representing the role's occupational field.
     */
    'schema' => [
        // chancellor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chancellor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q61061', // 'chancellor' (leader of a university or a college)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-9033.00', // 'Education Administrators, Postsecondary'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'business administration' (administration of the business transactions of a profit-oriented enterprise)
                        'https://www.wikidata.org/wiki/Q4671162', // 'academic administration' (branch of university or college employees responsible for the maintenance and supervision of the institution)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // vice chancellor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chancellor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'vice',
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q12416358', // 'vice-chancellor' (academic position)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-9033.00', // 'Education Administrators, Postsecondary'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'academic administration' (branch of university or college employees responsible for the maintenance and supervision of the institution)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // provost
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'provost',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q2114175', // 'provost' (senior academic administrator at many institutions of higher education)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-9033.00', // 'Education Administrators, Postsecondary'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'academic administration' (branch of university or college employees responsible for the maintenance and supervision of the institution)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // dean
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'dean',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q723682', // 'dean' (in academics, a person with significant authority over a specific academic unit)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-9033.00', // 'Education Administrators, Postsecondary'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'academic administration' (branch of university or college employees responsible for the maintenance and supervision of the institution)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // associate dean
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'dean',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'associate',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q42880144', // 'associate dean' (academic position)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-9033.00', // 'Education Administrators, Postsecondary'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'academic administration' (branch of university or college employees responsible for the maintenance and supervision of the institution)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // assistant dean
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'dean',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'assistant',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q124162007', // 'assistant dean' (academic position)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-9033.00', // 'Education Administrators, Postsecondary'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'academic administration' (branch of university or college employees responsible for the maintenance and supervision of the institution)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // president
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'president',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1255921', // 'president' (leader of an organization)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'business administration' (administration of the business transactions of a profit-oriented enterprise)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // vice president
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'president',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'vice',
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'not_contains_any',
                        'value' => [
                            'senior',
                        ],
                    ],
                ],
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q42178', // 'vice president' (officer in government, business or educational institutions)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // senior vice president
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'president',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'vice',
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'contains_all',
                        'value' => [
                            'senior',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q11307381', // 'senior vice president' (officer in business)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // chief executive officer
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chief_executive_officer',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q484876', // 'chief executive officer' (highest-ranking corporate officer)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'business administration' (administration of the business transactions of a profit-oriented enterprise)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // chief financial officer
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chief_financial_officer',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q623268', // 'chief financial officer' (corporate title)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                            '11-3031.00', // 'Financial Managers'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                            '1211', // 'Financial Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4738155', // 'financial management' (management of money in such a manner as to accomplish the objectives of the organization)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // chief operating officer
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chief_operating_officer',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q623279', // 'chief operating officer' (executive position)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                            '11-1021.00', // 'General and Operations Managers'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1423657', // 'operations management' (area of management concerned with designing and controlling the process of production and redesigning business operations)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // chief human resources officer
        // chief people and culture officer
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'chief_human_resources_officer',
                            'chief_people_and_culture_officer',
                        ],
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q10624502', // 'chief human resources officer' (corporate title)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                            '11-3121.00', // 'Human Resources Managers'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                            '1212', // 'Human Resource Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1056396', // 'human resource management' (field in economy)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // chief information officer
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chief_information_officer',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q2589465', // 'chief information officer' (information technology executive)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                            '11-3021.00', // 'Computer and Information Systems Managers'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                            '1330', // 'Information and Communications Technology Service Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1473265', // 'information technology management' (the discipline whereby all of the information technology resources of a firm are managed in accordance with its needs and priorities)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // chief technology officer
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chief_technology_officer',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q5287861', // 'chief technology officer' (executive-level position overseeing technical, technological or scientific areas)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                            '11-3021.00', // 'Computer and Information Systems Managers'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                            '1330', // 'Information and Communications Technology Service Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1417293', // 'technology management' (set of management disciplines that allows organizations to manage their technological fundamentals to create competitive advantage)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // chief communications officer / public relations officer
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'chief_communications_officer',
                            'public_relations_officer',
                        ],
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q3659898', // 'public relations officer' (head of communications in an organization)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                            '11-2032.00', // 'Public Relations Managers'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                            '1222', // 'Advertising and Public Relations Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q133080', // 'public relations' (broad term for the management of public communication of organizations)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // chief marketing officer
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chief_marketing_officer',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1072339', // 'chief marketing officer' (highest ranking executive-level position focusing on marketing)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                            '11-2021.00', // 'Marketing Managers'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                            '1222', // 'Advertising and Public Relations Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1143466', // 'marketing management' (practical application of marketing in organizations)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // chief strategy officer
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chief_strategy_officer',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q5097093', // 'chief strategy officer' (corporate title)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                            '1213', // 'Policy and Planning Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q376657', // 'strategic management' (managing a company's responses to external issues, aiming to provide overall organizational direction and objectives, as well as policies and plans for implementation)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // chief legal counsel / chief legal officer / general counsel
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'chief_legal_counsel',
                            'chief_legal_officer',
                            'general_counsel',
                        ],
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q564612', // 'general counsel' (chief lawyer of a legal department)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '23-1011.00', // 'Lawyers'
                            '11-1011.00', // 'Chief Executives'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q12586741', // 'legal management' (field of business administration on legal issues)
                        'https://www.wikidata.org/wiki/Q7748', // 'law' (system of rules and guidelines, generally backed by governmental authority)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // associate vice chancellor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chancellor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'associate',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'vice',
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q139503444', // 'associate vice chancellor' (high-ranking academic administrator who supports a vice chancellor and acts as their primary deputy or substitute)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-9033.00', // 'Education Administrators, Postsecondary'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'academic administration' (branch of university or college employees responsible for the maintenance and supervision of the institution)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // assistant vice chancellor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chancellor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'assistant',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'vice',
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q139503454', // 'assistant vice chancellor' (senior academic administrator responsible for a specific functional area, reporting to a vice chancellor or associate vice chancellor)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-9033.00', // 'Education Administrators, Postsecondary'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'academic administration' (branch of university or college employees responsible for the maintenance and supervision of the institution)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // professor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'professor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q121594', // 'professor' (academic position at universities and other education and research institutions)
                        'https://www.wikidata.org/wiki/Q25339110', // 'full professor' (highest administrative rank in the teaching career, assimilated to the highest academic rank, this being that of doctor)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '25-1071.00', // 'Health Specialties Teachers, Postsecondary'
                        ],
                        'isco_08' => [
                            '2310', // 'University and Higher Education Teachers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q352842', // 'teaching' (process or activity of imparting knowledge and skills)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // professor emeritus / professor emerita
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'professor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'emeritus',
                            'emerita',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1240569', // 'professor emeritus' (retired university professor who maintains links with their former institution)
                    ],
                    'category_code' => [], // Intentionally empty. Retired people do not technically have an occupation.
                ],
                'occupational_field' => [], // Intentionally empty. Retired people do not technically have an occupation.
            ],
        ],
        // adjunct professor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'professor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'contains_all',
                        'value' => [
                            'adjunct',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q357813', // 'adjunct professor' (academic title or rank typically indicating non-full time employment as a professor)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '25-1071.00', // 'Health Specialties Teachers, Postsecondary'
                        ],
                        'isco_08' => [
                            '2310', // 'University and Higher Education Teachers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q352842', // 'teaching' (process or activity of imparting knowledge and skills)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // associate professor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'professor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'associate',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'not_contains_any',
                        'value' => [
                            'adjunct',
                        ],
                    ],
                ],
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q9344260', // 'associate professor' (academic rank and position)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '25-1071.00', // 'Health Specialties Teachers, Postsecondary'
                        ],
                        'isco_08' => [
                            '2310', // 'University and Higher Education Teachers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q352842', // 'teaching' (process or activity of imparting knowledge and skills)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // associate professor emeritus / associate professor emerita
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'professor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'associate',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'emeritus',
                            'emerita',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q105099669', // 'associate professor emeritus' (emeritus or emerita is an honorary title for associate professors who want to stay active in scholarship following retirement)
                    ],
                    'category_code' => [], // Intentionally empty. Retired people do not technically have an occupation.
                ],
                'occupational_field' => [], // Intentionally empty. Retired people do not technically have an occupation.
            ],
        ],
        // associate professor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'professor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'associate',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'contains_all',
                        'value' => [
                            'adjunct',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q102162072', // 'adjunct associate professor' (academic title or rank typically indicating non-full time employment as an associate professor)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '25-1071.00', // 'Health Specialties Teachers, Postsecondary'
                        ],
                        'isco_08' => [
                            '2310', // 'University and Higher Education Teachers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q352842', // 'teaching' (process or activity of imparting knowledge and skills)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // assistant professor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'professor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'assistant',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'not_contains_any',
                        'value' => [
                            'adjunct',
                        ],
                    ],
                ],
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q5669847', // 'assistant professor' (academic rank and position)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '25-1071.00', // 'Health Specialties Teachers, Postsecondary'
                        ],
                        'isco_08' => [
                            '2310', // 'University and Higher Education Teachers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q352842', // 'teaching' (process or activity of imparting knowledge and skills)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // assistant professor emeritus / assistant professor emerita
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'professor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'assistant',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'emeritus',
                            'emerita',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q131390797', // 'assistant professor emeritus' (an honorary title granted to someone who retires from as an assistant professor, but is allowed to continue using the previous title)
                    ],
                    'category_code' => [], // Intentionally empty. Retired people do not technically have an occupation.
                ],
                'occupational_field' => [], // Intentionally empty. Retired people do not technically have an occupation.
            ],
        ],
        // assistant professor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'professor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'assistant',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'contains_all',
                        'value' => [
                            'adjunct',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q102162094', // 'adjunct assistant professor' (academic title or rank typically indicating non-full time employment as an assistant professor)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '25-1071.00', // 'Health Specialties Teachers, Postsecondary'
                        ],
                        'isco_08' => [
                            '2310', // 'University and Higher Education Teachers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q352842', // 'teaching' (process or activity of imparting knowledge and skills)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // instructor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'instructor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'not_contains_any',
                        'value' => [
                            'adjunct',
                        ],
                    ],
                ],
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q64760507', // 'instructional staff' (low-level academic rank)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '25-1071.00', // 'Health Specialties Teachers, Postsecondary'
                        ],
                        'isco_08' => [
                            '2310', // 'University and Higher Education Teachers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q352842', // 'teaching' (process or activity of imparting knowledge and skills)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // adjunct instructor
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'instructor',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'contains_all',
                        'value' => [
                            'adjunct',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q139503561', // 'adjunct instructor' (academic title or rank typically indicating non-full time employment as an instructor)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '25-1071.00', // 'Health Specialties Teachers, Postsecondary'
                        ],
                        'isco_08' => [
                            '2310', // 'University and Higher Education Teachers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q352842', // 'teaching' (process or activity of imparting knowledge and skills)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // lecturer
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'lecturer',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'not_contains_any',
                        'value' => [
                            'adjunct',
                        ],
                    ],
                ],
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1569495', // 'lecturer' (expert who is hired to teach on a full or part time basis)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '25-1071.00', // 'Health Specialties Teachers, Postsecondary'
                        ],
                        'isco_08' => [
                            '2310', // 'University and Higher Education Teachers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q352842', // 'teaching' (process or activity of imparting knowledge and skills)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // adjunct lecturer
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'lecturer',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'contains_all',
                        'value' => [
                            'adjunct',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q139503570', // 'adjunct lecturer' (expert who is hired to teach on a part-time basis)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '25-1071.00', // 'Health Specialties Teachers, Postsecondary'
                        ],
                        'isco_08' => [
                            '2310', // 'University and Higher Education Teachers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q352842', // 'teaching' (process or activity of imparting knowledge and skills)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // executive director
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'director',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // title options, e.g. 'senior', 'adjunct'
                'title_options' => [
                    [
                        'operator' => 'contains_all',
                        'value' => [
                            'executive',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q267936', // 'executive director' (chief executive officer (CEO) or managing director of an organization, company, or corporation, who serves as a board member)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1011.00', // 'Chief Executives'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // director
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'director',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'department', 'section', 'division'
                'object' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1162163', // 'director' (person who leads a particular area of a company or organization)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1021.00', // 'General and Operations Managers'
                        ],
                        'isco_08' => [
                            '1', // 'Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // associate director
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'director',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'associate',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'department', 'section', 'division'
                'object' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q20155512', // 'associate director' (person who works directly for the director of an organization)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1021.00', // 'General and Operations Managers'
                        ],
                        'isco_08' => [
                            '1', // 'Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // assistant director
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'director',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'assistant',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'department', 'section', 'division'
                'object' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q136323334', // 'assistant director' (person who assists or deputizes for a director in a company, government, or organization)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1021.00', // 'General and Operations Managers'
                        ],
                        'isco_08' => [
                            '1', // 'Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // fellowship program director
        // residency program director
        // medical student clerkship director
        // training director
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'director',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'department', 'section', 'division'
                'object' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'fellowship_program',
                            'residency_program',
                            'medical_student_clerkship',
                            'training',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1162163', // 'director' (person who leads a particular area of a company or organization)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1021.00', // 'General and Operations Managers'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // fellowship program associate director
        // residency program associate director
        // medical student clerkship associate director
        // training associate director
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'director',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'associate',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'department', 'section', 'division'
                'object' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'fellowship_program',
                            'residency_program',
                            'medical_student_clerkship',
                            'training',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q20155512', // 'associate director' (person who works directly for the director of an organization)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1021.00', // 'General and Operations Managers'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // fellowship program assistant director
        // residency program assistant director
        // medical student clerkship assistant director
        // training assistant director
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'director',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => 'assistant',
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'department', 'section', 'division'
                'object' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'fellowship_program',
                            'residency_program',
                            'medical_student_clerkship',
                            'training',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q136323334', // 'assistant director' (person who assists or deputizes for a director in a company, government, or organization)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1021.00', // 'General and Operations Managers'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // department chair / department chairperson / department chairman / department chairwoman
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'chair',
                            'chairperson',
                            'chairman',
                            'chairwoman',
                        ],
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'department', 'section', 'division'
                'object' => [
                    [
                        'operator' => 'equals',
                        'value' => 'department',
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q140686', // chairperson (leading or presiding officer of an organized group)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-9033.00', // 'Education Administrators, Postsecondary'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'academic administration' (branch of university or college employees responsible for the maintenance and supervision of the institution)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // department vice chair / department vice chairperson / department vice chairman / department vice chairwoman
        // department deputy chair / department deputy chairperson / department deputy chairman / department deputy chairwoman
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'chair',
                            'chairperson',
                            'chairman',
                            'chairwoman',
                        ],
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'vice',
                            'deputy',
                        ],
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'department', 'section', 'division'
                'object' => [
                    [
                        'operator' => 'equals',
                        'value' => 'department',
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q1127270', // deputy chairperson (sub class of Chairman)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-9033.00', // 'Education Administrators, Postsecondary'
                        ],
                        'isco_08' => [
                            '1345', // 'Education Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q4671162', // 'academic administration' (branch of university or college employees responsible for the maintenance and supervision of the institution)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // chief of staff
        [
            'conditions' => [
                // e.g., 'chancellor', 'provost', 'chief executive officer', 'professor', etc.
                'role' => [
                    [
                        'operator' => 'equals',
                        'value' => 'chief_of_staff',
                    ],
                ],
                // e.g., 'associate' or 'assistant'
                'hierarchical_modifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // e.g., 'vice'
                'positional_qualifier' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
                // honorific: 'emeritus' / 'emerita'
                'honorific' => [
                    [
                        'operator' => 'equals',
                        'value' => null,
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q112119463', // chief of staff (identifies the leader of a complex organization, institution, or body of persons)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-1021.00', // 'General and Operations Managers'
                        ],
                        'isco_08' => [
                            '1120', // 'Managing Directors and Chief Executives'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                        'https://www.wikidata.org/wiki/Q376657', // 'strategic management' (managing a company's responses to external issues, aiming to provide overall organizational direction and objectives, as well as policies and plans for implementation)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
        // service line administrator / director / medical director / nursing director
        // (clinical-leadership configuration; all map to health administrator)
        //
        // Both conditions are load-bearing since UAMS-Web/uams-statamic-archive#2143 decomposed the unit out of the role.
        // `director` is now a bare key shared with academic and institutional leadership, so
        // matching on the role alone would give an academic department director the health
        // administrator occupation. The `service_line` object is what makes it clinical.
        [
            'conditions' => [
                // e.g., 'medical_director'
                'role' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'administrator',
                            'director',
                            'medical_director',
                            'nursing_director',
                        ],
                    ],
                ],
                // e.g., 'service_line'
                'object' => [
                    [
                        'operator' => 'equals_any',
                        'value' => [
                            'service_line',
                        ],
                    ],
                ],
            ],
            'output' => [
                'occupation' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q26701778', // 'health administrator' (person who manages a hospital or other health care facility)
                    ],
                    'category_code' => [
                        'onet_soc' => [
                            '11-9111.00', // 'Medical and Health Services Managers'
                        ],
                        'isco_08' => [
                            '1342', // 'Health Services Managers'
                        ],
                    ],
                ],
                'occupational_field' => [
                    'same_as' => [
                        'https://www.wikidata.org/wiki/Q2920921', // 'management' (administration of an organization, including activities to set the strategy of an organization and coordinate employees to accomplish its objectives)
                        'https://www.wikidata.org/wiki/Q484275', // 'leadership' (ability or activity of an individual or organization to guide other individuals, teams, or entire organizations)
                    ],
                    'category_code' => [
                        //
                    ],
                ],
            ],
        ],
    ],
];
