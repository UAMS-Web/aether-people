/**
 * Top-level Statamic fieldtype `meta` keys for `role_title` (mirrors `RoleTitleValue`
 * where applicable). Option rows live under `option_label_maps` only; do not
 * duplicate per-slot identifiers here.
 */

/** @type {string} Meta key for `RoleTitleValue::emptyValueSharedPayload()` on preload. */
export const PRELOAD_KEY_EMPTY_VALUE_SHARED = 'empty_value_shared';

/** @type {string} Optional-radio none sentinel (`RoleTitleValue::optionalSelectionNone()`). */
export const PRELOAD_KEY_OPTIONAL_RADIO_NONE = 'optional_radio_none';

/** @type {string} Ordered configuration handles from definition. */
export const META_KEY_CONFIGURATION_HANDLES_CANONICAL = 'configuration_handles_canonical';

/** @type {string} Translated configuration picker labels. */
export const META_KEY_CONFIGURATION_LABELS = 'configuration_labels';

/** @type {string} CP editor blocks per configuration handle. */
export const META_KEY_CP_EDITOR_BLOCKS_BY_CONFIGURATION = 'cp_editor_blocks_by_configuration';

/** @type {string} Optional-radio “none” labels keyed by top-level radios subfield. */
export const META_KEY_OPTIONAL_RADIO_NONE_LABELS_BY_SUBFIELD =
	'optional_radio_none_labels_by_subfield';

/** @type {string} CP labels for subfield blocks. */
export const META_KEY_CP_SUBFIELD_LABELS = 'cp_subfield_labels';

/** @type {string} Nested `group.fields` CP labels (`group.nested` keys). */
export const META_KEY_CP_NESTED_FIELD_LABELS = 'cp_nested_field_labels';

/** @type {string} Translated configuration field label (picker + checklist). */
export const META_KEY_CONFIGURATION_FIELD_LABEL = 'configuration_field_label';

/** @type {string} Top-level object keys shallow-merged when hydrating `current`. */
export const META_KEY_STRUCTURED_MERGE_TOP_LEVEL_KEYS = 'structured_merge_top_level_keys';


/** CP-only replicator keys (mirror `App\Support\RoleTitleUnstructuredCpBridge`). */
export const CP_UNSTRUCTURED_ISCO = '__cp_role_title_unstructured_isco_08';
export const CP_UNSTRUCTURED_ONET = '__cp_role_title_unstructured_onet_soc';
export const CP_UNSTRUCTURED_OCC_SAME_AS = '__cp_role_title_unstructured_occ_same_as';
export const CP_UNSTRUCTURED_FIELD_SAME_AS =
	'__cp_role_title_unstructured_field_same_as';

export const MODE_STRUCTURED = 'structured';
export const MODE_UNSTRUCTURED = 'unstructured';
