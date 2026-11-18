<template>
	<div class="role-title-fieldtype min-w-0">
		<GroupFieldtypeShell
			:border="shellBorder"
			:body-class="'space-y-6'"
		>
			<template v-if="showTitleStructurePicker">
				<Field
					:label="titleStructureFieldLabel"
					:required="titleStructureFieldMarkedRequired"
				>
					<RadioGroup
						:inline="false"
						:model-value="current.mode"
						@update:model-value="onTitleStructureChange"
					>
						<Radio
							v-for="opt in titleStructureSelectOptions"
							:key="opt.value"
							:value="opt.value"
							:label="opt.label"
							:disabled="isReadOnly"
							:read-only="isReadOnly"
						/>
					</RadioGroup>
					<ErrorMessage
						v-for="(err, i) in errs('mode')"
						:key="'mode'+i"
						class="mt-1"
						:text="err"
					/>
				</Field>
			</template>

			<template v-if="isStructuredMode">
			<template v-if="showConfigurationPicker">
				<Field
					:label="configurationFieldLabel"
					:required="subfieldsMarkedRequired"
				>
					<RadioGroup
						:inline="false"
						:model-value="current.configuration"
						@update:model-value="onConfigurationChange"
					>
						<Radio
							v-for="opt in configurationSelectOptions"
							:key="opt.value"
							:value="opt.value"
							:label="opt.label"
							:disabled="isReadOnly"
							:read-only="isReadOnly"
						/>
					</RadioGroup>
					<ErrorMessage
						v-for="(err, i) in errs('configuration')"
						:key="'cfg'+i"
						class="mt-1"
						:text="err"
					/>
				</Field>
			</template>

			<template
				v-for="block in cpEditorBlocksForCurrentConfiguration"
				:key="block.type + '-' + block.key"
			>
				<template v-if="block.type === 'checkboxes'">
					<Field
						:label="cpSubfieldLabel(block.key)"
					>
						<CheckboxGroup
							:inline="false"
							:model-value="checkboxModelForBlock(block)"
							@update:model-value="onCheckboxGroupUpdate(block, $event)"
						>
							<Checkbox
								v-for="opt in optionRowsFromMeta(checkboxOptionMapKey(block))"
								:key="opt.value"
								:value="opt.value"
								:label="opt.label"
								:disabled="isReadOnly"
								:read-only="isReadOnly"
							/>
						</CheckboxGroup>
					</Field>
				</template>

				<template v-else-if="block.type === 'radios'">
					<div class="min-w-0">
						<Label
							class="mb-1 block"
							:for="subfieldInputId(block.key)"
							:required="subfieldsMarkedRequired"
						>{{ cpSubfieldLabel(block.key) }}</Label>
						<RoleTitleOptionalRadioGroup
							v-if="block.allow_optional_none"
							:input-id="subfieldInputId(block.key)"
							:model-value="current[block.key]"
							:none-label="optionalNoneLabelFor(block.key)"
							:none-value="optionalRadioNoneSentinel"
							:options="optionRowsFromMeta(block.key)"
							:read-only="isReadOnly"
							@update:model-value="patch(block.key, $event)"
						/>
						<RadioGroup
							v-else
							:inline="false"
							:model-value="current[block.key]"
							@update:model-value="patch(block.key, $event)"
						>
							<Radio
								v-for="opt in optionRowsFromMeta(block.key)"
								:key="opt.value"
								:disabled="isReadOnly"
								:read-only="isReadOnly"
								:value="opt.value"
								:label="opt.label"
							/>
						</RadioGroup>
						<ErrorMessage
							v-for="(err, i) in errs(block.key)"
							:key="block.key + 'e' + i"
							class="mt-1"
							:text="err"
						/>
					</div>
				</template>

				<template v-else-if="block.type === 'group'">
					<div class="space-y-4 min-w-0">
						<div
							v-for="nested in (block.nested || [])"
							:key="nested.storage_path"
							class="min-w-0"
						>
							<template v-if="nested.type === 'radios'">
								<Label
									class="mb-1 block"
									:for="subfieldInputIdFromPath(nested.storage_path)"
									:required="nestedFieldLabelRequired(nested)"
								>{{ nestedCpFieldLabelByPath(nested.storage_path) }}</Label>
								<RoleTitleOptionalRadioGroup
									v-if="nested.allow_optional_none"
									:input-id="subfieldInputIdFromPath(nested.storage_path)"
									:model-value="getAtPath(current, nested.storage_path)"
									:none-label="optionalNoneLabelForNested(nested)"
									:none-value="optionalRadioNoneSentinel"
									:options="nestedOptionRows(nested)"
									:read-only="isReadOnly"
									@update:model-value="patchAtPath(nested.storage_path, $event)"
								/>
								<RadioGroup
									v-else
									:inline="false"
									:model-value="getAtPath(current, nested.storage_path)"
									@update:model-value="patchAtPath(nested.storage_path, $event)"
								>
									<Radio
										v-for="opt in nestedOptionRows(nested)"
										:key="opt.value"
										:disabled="isReadOnly"
										:read-only="isReadOnly"
										:value="opt.value"
										:label="opt.label"
									/>
								</RadioGroup>
								<ErrorMessage
									v-for="(err, i) in errs(nested.storage_path)"
									:key="nested.storage_path + 'e' + i"
									class="mt-1"
									:text="err"
								/>
							</template>
							<template v-else-if="nested.type === 'text'">
								<Label
									class="mb-1 block"
									:for="subfieldInputIdFromPath(nested.storage_path)"
									:required="false"
								>{{ nestedCpFieldLabelByPath(nested.storage_path) }}</Label>
								<Input
									:id="subfieldInputIdFromPath(nested.storage_path)"
									:model-value="textModelForPath(nested.storage_path)"
									:read-only="isReadOnly"
									@update:model-value="patchAtPath(nested.storage_path, $event)"
								/>
								<ErrorMessage
									v-for="(err, i) in errs(nested.storage_path)"
									:key="nested.storage_path + 't' + i"
									class="mt-1"
									:text="err"
								/>
							</template>
							<template v-else-if="nested.type === 'entries'">
								<Label
									class="mb-1 block"
									:required="false"
								>{{ nestedCpFieldLabelByPath(nested.storage_path) }}</Label>
								<component
									:is="'relationship-fieldtype'"
									:config="scopeUnitFieldConfig"
									:meta="scopeUnitMeta"
									:value="scopeUnitValueForPath(nested.storage_path)"
									:handle="scopeUnitHandle"
									:field-path-prefix="publishFieldPathPrefix"
									:meta-path-prefix="publishMetaPathPrefix"
									:read-only="isReadOnly"
									@update:value="onScopeUnitValue(nested.storage_path, $event)"
									@update:meta="onScopeUnitMeta($event)"
								/>
								<ErrorMessage
									v-for="(err, i) in errs(nested.storage_path)"
									:key="nested.storage_path + 'u' + i"
									class="mt-1"
									:text="err"
								/>
							</template>
						</div>
					</div>
				</template>
			</template>

			<template v-if="roleTitleChecklistAlertVisible">
				<Separator />

				<Alert variant="warning" >
					<p>{{ __('aether-people::fieldtypes.role_title.checklist.before_compose') }}</p>
					<ul class="list-disc list-inside space-y-1">
						<li
							v-for="(item, i) in roleTitleComposeChecklistItems"
							:key="'cl'+i"
						>
							{{ item.label }}
						</li>
					</ul>
				</Alert>

			</template>
			</template>

			<template v-else-if="isUnstructuredMode">
				<Field
					:label="__('aether-people::fieldtypes.role_title.field.composed_value')"
					:required="unstructuredSegmentRequired('value')"
				>
					<Input
						:id="subfieldInputId('unstructured_title')"
						:model-value="current.value"
						:read-only="isReadOnly"
						@update:model-value="patch('value', $event)"
					/>
					<ErrorMessage
						v-for="(err, i) in errs('value')"
						:key="'uv'+i"
						class="mt-1"
						:text="err"
					/>
				</Field>

				<Separator />

				<Field
					class="min-w-0"
					:label="__('aether-people::fieldtypes.role_title.field.unstructured.occupation_isco_08')"
					:instructions="
						__(
							'aether-people::fieldtypes.role_title.unstructured.occupation_isco_08_instructions'
						)
					"
					:required="unstructuredSegmentRequired('occupation_isco_08')"
				>
					<component
						:is="'replicator-fieldtype'"
						:config="unstructuredReplicatorIscoConfig"
						:meta="unstructuredReplicatorMeta(CP_UNSTRUCTURED_ISCO)"
						:value="cpReplicatorValue(CP_UNSTRUCTURED_ISCO)"
						:handle="CP_UNSTRUCTURED_ISCO"
						:field-path-prefix="publishFieldPathPrefix"
						:meta-path-prefix="publishMetaPathPrefix"
						:read-only="isReadOnly"
						@update:value="patch(CP_UNSTRUCTURED_ISCO, $event)"
						@update:meta="onUnstructuredReplicatorMeta(CP_UNSTRUCTURED_ISCO, $event)"
					/>
				</Field>

				<Field
					class="min-w-0"
					:label="__('aether-people::fieldtypes.role_title.field.unstructured.occupation_onet_soc')"
					:instructions="
						__(
							'aether-people::fieldtypes.role_title.unstructured.occupation_onet_soc_instructions'
						)
					"
					:required="unstructuredSegmentRequired('occupation_onet_soc')"
				>
					<component
						:is="'replicator-fieldtype'"
						:config="unstructuredReplicatorOnetConfig"
						:meta="unstructuredReplicatorMeta(CP_UNSTRUCTURED_ONET)"
						:value="cpReplicatorValue(CP_UNSTRUCTURED_ONET)"
						:handle="CP_UNSTRUCTURED_ONET"
						:field-path-prefix="publishFieldPathPrefix"
						:meta-path-prefix="publishMetaPathPrefix"
						:read-only="isReadOnly"
						@update:value="patch(CP_UNSTRUCTURED_ONET, $event)"
						@update:meta="onUnstructuredReplicatorMeta(CP_UNSTRUCTURED_ONET, $event)"
					/>
				</Field>

				<Field
					class="min-w-0"
					:label="__('aether-people::fieldtypes.role_title.field.unstructured.occupation_same_as')"
					:instructions="
						__(
							'aether-people::fieldtypes.role_title.unstructured.reference_webpages_instructions'
						)
					"
					:required="unstructuredSegmentRequired('occupation_same_as')"
				>
					<component
						:is="'replicator-fieldtype'"
						:config="unstructuredReplicatorOccSameAsConfig"
						:meta="unstructuredReplicatorMeta(CP_UNSTRUCTURED_OCC_SAME_AS)"
						:value="cpReplicatorValue(CP_UNSTRUCTURED_OCC_SAME_AS)"
						:handle="CP_UNSTRUCTURED_OCC_SAME_AS"
						:field-path-prefix="publishFieldPathPrefix"
						:meta-path-prefix="publishMetaPathPrefix"
						:read-only="isReadOnly"
						@update:value="patch(CP_UNSTRUCTURED_OCC_SAME_AS, $event)"
						@update:meta="onUnstructuredReplicatorMeta(CP_UNSTRUCTURED_OCC_SAME_AS, $event)"
					/>
				</Field>

				<Separator />

				<Field
					class="min-w-0"
					:label="
						__(
							'aether-people::fieldtypes.role_title.field.unstructured.occupational_field_same_as'
						)
					"
					:instructions="
						__(
							'aether-people::fieldtypes.role_title.unstructured.reference_webpages_instructions_occupational_field'
						)
					"
					:required="unstructuredSegmentRequired('occupational_field_same_as')"
				>
					<component
						:is="'replicator-fieldtype'"
						:config="unstructuredReplicatorFieldSameAsConfig"
						:meta="unstructuredReplicatorMeta(CP_UNSTRUCTURED_FIELD_SAME_AS)"
						:value="cpReplicatorValue(CP_UNSTRUCTURED_FIELD_SAME_AS)"
						:handle="CP_UNSTRUCTURED_FIELD_SAME_AS"
						:field-path-prefix="publishFieldPathPrefix"
						:meta-path-prefix="publishMetaPathPrefix"
						:read-only="isReadOnly"
						@update:value="patch(CP_UNSTRUCTURED_FIELD_SAME_AS, $event)"
						@update:meta="onUnstructuredReplicatorMeta(CP_UNSTRUCTURED_FIELD_SAME_AS, $event)"
					/>
				</Field>
			</template>

		</GroupFieldtypeShell>
	</div>
</template>

<script>
import { inject } from 'vue';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import {
	Alert,
	Checkbox,
	CheckboxGroup,
	ErrorMessage,
	Field,
	Input,
	Label,
	Radio,
	RadioGroup,
	Separator,
	publishContextKey
} from '@statamic/cms/ui';
import {
	roleTitleComposeChecklistOutstanding as computeRoleTitleComposeChecklistOutstanding,
	roleTitleHasOutstandingChecklist
} from './role-title/roleTitleComposePrerequisites.js';
import { emptyRoleTitle } from './role-title/emptyRoleTitle.js';
import {
	CP_UNSTRUCTURED_FIELD_SAME_AS,
	CP_UNSTRUCTURED_ISCO,
	CP_UNSTRUCTURED_OCC_SAME_AS,
	CP_UNSTRUCTURED_ONET,
	META_KEY_CONFIGURATION_FIELD_LABEL,
	META_KEY_CONFIGURATION_HANDLES_CANONICAL,
	META_KEY_CONFIGURATION_LABELS,
	META_KEY_CP_EDITOR_BLOCKS_BY_CONFIGURATION,
	META_KEY_CP_NESTED_FIELD_LABELS,
	META_KEY_CP_SUBFIELD_LABELS,
	META_KEY_OPTIONAL_RADIO_NONE_LABELS_BY_SUBFIELD,
	META_KEY_STRUCTURED_MERGE_TOP_LEVEL_KEYS,
	MODE_STRUCTURED,
	MODE_UNSTRUCTURED,
	PRELOAD_KEY_OPTIONAL_RADIO_NONE
} from './role-title/roleTitlePreloadKeys.js';
import RoleTitleOptionalRadioGroup from './role-title/RoleTitleOptionalRadioGroup.vue';
import GroupFieldtypeShell from '../../../../vendor/uams-web/aether-fieldtypes/resources/js/components/fieldtypes/partials/GroupFieldtypeShell.vue';

/** Meta key the picker's live meta is stored under, beside the preloaded `scope_unit_meta`. */
const SCOPE_UNIT_LIVE_META_KEY = 'scope_unit_meta_live';

export default {
	name: 'RoleTitleFieldtype',
	components: {
		Alert,
		Checkbox,
		CheckboxGroup,
		ErrorMessage,
		Field,
		GroupFieldtypeShell,
		Input,
		Label,
		Radio,
		RadioGroup,
		RoleTitleOptionalRadioGroup,
		Separator
	},
	mixins: [Fieldtype],
	setup() {
		const publishContext = inject(publishContextKey, null);
		return { publishContext };
	},
	data() {
		return {
			CP_UNSTRUCTURED_FIELD_SAME_AS,
			CP_UNSTRUCTURED_ISCO,
			CP_UNSTRUCTURED_OCC_SAME_AS,
			CP_UNSTRUCTURED_ONET,
			MODE_STRUCTURED,
			MODE_UNSTRUCTURED
		};
	},
	computed: {
		/** Stock `entries` config for the scope unit picker, from preload (UAMS-Web/uams-statamic-archive#2146). */
		scopeUnitFieldConfig() {
			const cfg = this.meta?.scope_unit_field_config;
			return cfg && typeof cfg === 'object' && !Array.isArray(cfg) ? cfg : {};
		},
		/** Live picker meta when the picker has emitted one, else the preloaded meta. */
		scopeUnitMeta() {
			const live = this.meta?.[SCOPE_UNIT_LIVE_META_KEY];
			if (live && typeof live === 'object' && !Array.isArray(live)) {
				return live;
			}
			const pre = this.meta?.scope_unit_meta;
			return pre && typeof pre === 'object' && !Array.isArray(pre) ? pre : {};
		},
		scopeUnitHandle() {
			return `${this.handle}_scope_unit`;
		},
		configurationFieldLabel() {
			const m = this.meta?.[META_KEY_CONFIGURATION_FIELD_LABEL];
			if (typeof m === 'string' && m !== '') {
				return m;
			}
			return this.__(
				'aether-people::fieldtypes.role_title.field.configuration'
			);
		},
		fieldValidateHasRequired() {
			const v = this.config?.validate;
			return Array.isArray(v) && v.includes('required');
		},
		fieldMarkedRequired() {
			return !!(this.config?.required || this.fieldValidateHasRequired);
		},
		/**
		 * Required asterisk on subfields when the blueprint marks this field
		 * required (excludes Title Options checkboxes; matches PersonName pattern).
		 */
		subfieldsMarkedRequired() {
			return this.fieldMarkedRequired;
		},
		titleStructureFieldMarkedRequired() {
			return this.showTitleStructurePicker && this.subfieldsMarkedRequired;
		},
		/**
		 * Whether an unstructured segment shows a required asterisk when the
		 * blueprint marks this field required and the segment is selected under
		 * `required_unstructured_subfields`.
		 *
		 * @returns {(segment: string) => boolean}
		 */
		unstructuredSegmentRequired() {
			return (segment) => {
				if (!this.subfieldsMarkedRequired) {
					return false;
				}
				const req = this.meta?.required_unstructured_subfields;
				if (!Array.isArray(req)) {
					return segment === 'value';
				}
				return req.includes(segment);
			};
		},
		shellBorder() {
			const metaBorder = this.meta?.border;
			if (metaBorder === false) {
				return false;
			}
			if (this.config?.border === false) {
				return false;
			}
			return true;
		},
		/**
		 * Mirrors fieldtype config: show title configuration only when more than one
		 * mode is enabled (`selectionCount:gt,1` on default_configuration).
		 */
		showConfigurationPicker() {
			return this.enabledConfigurationKeys.length > 1;
		},
		showTitleStructurePicker() {
			return this.enabledTitleStructureKeys.length > 1;
		},
		enabledTitleStructureKeys() {
			const order = [MODE_STRUCTURED, MODE_UNSTRUCTURED];
			const raw = this.meta?.enabled_title_structures;
			if (Array.isArray(raw) && raw.length > 0) {
				const ordered = order.filter((k) => raw.includes(k));
				if (ordered.length > 0) {
					return ordered;
				}
			}
			return [MODE_STRUCTURED];
		},
		titleStructureFieldLabel() {
			return this.__('aether-people::fieldtypes.role_title.field.mode');
		},
		titleStructureSelectOptions() {
			return this.enabledTitleStructureKeys.map((value) => ({
				value,
				label:
					value === MODE_UNSTRUCTURED
						? this.__('aether-people::fieldtypes.role_title.structure.unstructured')
						: this.__('aether-people::fieldtypes.role_title.structure.structured')
			}));
		},
		isStructuredMode() {
			return this.current.mode !== MODE_UNSTRUCTURED;
		},
		isUnstructuredMode() {
			return this.current.mode === MODE_UNSTRUCTURED;
		},
		/**
		 * Dot path to this field’s value in the publish container (same idea as
		 * `fullPath` in `Field.vue`). Nested inside a Replicator/Grid this is e.g.
		 * `institutional_roles.0.title`, not just `title`.
		 */
		publishFieldPathPrefix() {
			return [this.fieldPathPrefix, this.handle]
				.filter((p) => p != null && p !== '')
				.join('.');
		},
		/** Dot path to this field’s meta subtree (same idea as `metaFullPath` in `Field.vue`). */
		publishMetaPathPrefix() {
			return [this.metaPathPrefix, this.handle]
				.filter((p) => p != null && p !== '')
				.join('.');
		},
		unstructuredReplicatorIscoConfig() {
			return this.normalizedUnstructuredReplicatorFieldConfig(
				this.meta?.unstructured_replicator_isco_config
			);
		},
		unstructuredReplicatorOnetConfig() {
			return this.normalizedUnstructuredReplicatorFieldConfig(
				this.meta?.unstructured_replicator_onet_config
			);
		},
		unstructuredReplicatorOccSameAsConfig() {
			return this.normalizedUnstructuredReplicatorFieldConfig(
				this.meta?.unstructured_replicator_occ_same_as_config
			);
		},
		unstructuredReplicatorFieldSameAsConfig() {
			return this.normalizedUnstructuredReplicatorFieldConfig(
				this.meta?.unstructured_replicator_field_same_as_config
			);
		},
		enabledConfigurationKeys() {
			const canonical = this.meta?.[META_KEY_CONFIGURATION_HANDLES_CANONICAL];
			const raw = this.meta?.enabled_configurations;
			if (!Array.isArray(canonical) || canonical.length === 0) {
				return [];
			}
			if (Array.isArray(raw) && raw.length > 0) {
				const ordered = canonical.filter((k) => raw.includes(k));
				if (ordered.length > 0) {
					return ordered;
				}
			}
			const def = this.meta?.default_configuration;
			if (typeof def === 'string' && def !== '' && canonical.includes(def)) {
				return [def];
			}
			return [];
		},
		configurationSelectOptions() {
			const labels = this.meta?.[META_KEY_CONFIGURATION_LABELS];
			return this.enabledConfigurationKeys.map((value) => ({
				value,
				label:
					labels &&
					typeof labels === 'object' &&
					typeof labels[value] === 'string'
						? labels[value]
						: value
			}));
		},
		optionalRadioNoneSentinel() {
			const v = this.meta?.[PRELOAD_KEY_OPTIONAL_RADIO_NONE];
			return typeof v === 'string' ? v : '';
		},
		current() {
			const base = emptyRoleTitle(
				typeof this.meta?.default_configuration === 'string'
					? this.meta.default_configuration
					: null,
				this.meta
			);
			const v = this.value;
			// Start from defaults when the bound value is not a plain object
			if (!v || typeof v !== 'object' || Array.isArray(v)) {
				return this.coerceConfiguration({ ...base });
			}
			const merged = {
				...base,
				...v
			};
			const structRaw = this.meta?.[META_KEY_STRUCTURED_MERGE_TOP_LEVEL_KEYS];
			const structKeys = Array.isArray(structRaw) ? structRaw : [];
			for (const k of structKeys) {
				const b = base[k];
				const incoming = v[k];
				const baseObj =
					b && typeof b === 'object' && !Array.isArray(b) ? b : {};
				const vObj =
					incoming &&
					typeof incoming === 'object' &&
					!Array.isArray(incoming)
						? incoming
						: {};
				merged[k] = { ...baseObj, ...vObj };
			}
			// Treat null composed title like an empty string in the CP
			if (merged.value == null) {
				merged.value = '';
			}
			return this.coerceConfiguration(merged);
		},
		cpEditorBlocksForCurrentConfiguration() {
			const map = this.meta?.[META_KEY_CP_EDITOR_BLOCKS_BY_CONFIGURATION];
			const cfg =
				typeof this.current?.configuration === 'string'
					? this.current.configuration
					: '';
			if (
				map &&
				typeof map === 'object' &&
				cfg !== '' &&
				Array.isArray(map[cfg])
			) {
				return map[cfg];
			}
			return [];
		},
		roleTitleComposeChecklistItems() {
			return computeRoleTitleComposeChecklistOutstanding(this.current, this.meta, {
				showConfigurationPicker:
					this.showConfigurationPicker && this.isStructuredMode
			});
		},
		/**
		 * Show the checklist when required subfields are still unset (composed
		 * title is produced on save, not in the CP).
		 */
		roleTitleChecklistAlertVisible() {
			return roleTitleHasOutstandingChecklist(this.roleTitleComposeChecklistItems);
		}
	},
	methods: {
		/**
		 * Normalize preload replicator config: newer payloads are `{ sets, instructions? }`;
		 * legacy array shape is wrapped as `{ sets }`.
		 *
		 * @param {unknown} raw
		 * @returns {{ sets: unknown[], instructions?: string }}
		 */
		normalizedUnstructuredReplicatorFieldConfig(raw) {
			if (
				raw &&
				typeof raw === 'object' &&
				!Array.isArray(raw) &&
				Array.isArray(raw.sets)
			) {
				return raw;
			}
			if (Array.isArray(raw)) {
				return { sets: raw };
			}
			return { sets: [] };
		},
		checkboxOptionMapKey(block) {
			return block.option_map_key != null && block.option_map_key !== ''
				? block.option_map_key
				: block.key;
		},
		checkboxModelForBlock(block) {
			const storageKey = block.key;
			const mapKey = this.checkboxOptionMapKey(block);
			const inner = this.current[storageKey];
			const rowMap =
				inner && typeof inner === 'object' && !Array.isArray(inner)
					? inner
					: {};
			return this.optionRowsFromMeta(mapKey)
				.map((o) => o.value)
				.filter((k) => !!rowMap[k]);
		},
		onCheckboxGroupUpdate(block, vals) {
			const storageKey = block.key;
			const mapKey = this.checkboxOptionMapKey(block);
			const selected = new Set(Array.isArray(vals) ? vals : []);
			const next = { ...this.current };
			const inner = { ...(next[storageKey] || {}) };
			for (const k of this.optionRowsFromMeta(mapKey).map((o) => o.value)) {
				inner[k] = selected.has(k);
			}
			next[storageKey] = inner;
			this.update(next);
		},
		getAtPath(root, path) {
			if (!root || typeof path !== 'string') {
				return undefined;
			}
			const parts = path.split('.');
			let cur = root;
			for (const p of parts) {
				if (cur == null || typeof cur !== 'object') {
					return undefined;
				}
				cur = cur[p];
			}
			return cur;
		},
		patchAtPath(path, value) {
			const parts = path.split('.');
			if (parts.length === 1) {
				this.patch(parts[0], value);
				return;
			}
			const top = parts[0];
			const inner = { ...(this.current[top] || {}) };
			inner[parts[1]] = value;
			this.patch(top, inner);
		},
		textModelForPath(path) {
			const v = this.getAtPath(this.current, path);
			return typeof v === 'string' ? v : '';
		},
		/** The linked scope unit as the relationship picker's id list (UAMS-Web/uams-statamic-archive#2146). */
		scopeUnitValueForPath(path) {
			const v = this.getAtPath(this.current, path);
			return typeof v === 'string' && v !== '' ? [v] : [];
		},
		/** Store the one picked unit id, or null when the pick is cleared. */
		onScopeUnitValue(path, value) {
			const first = Array.isArray(value) ? value[0] : value;
			this.patchAtPath(
				path,
				typeof first === 'string' && first !== '' ? first : null
			);
		},
		onScopeUnitMeta(meta) {
			const setFieldMeta = this.publishContext?.setFieldMeta;
			if (typeof setFieldMeta !== 'function') {
				return;
			}
			setFieldMeta(`${this.publishMetaPathPrefix}.${SCOPE_UNIT_LIVE_META_KEY}`, meta);
		},
		nestedFieldLabelRequired(nested) {
			if (
				typeof nested.label_required_when_sibling_path_nonempty === 'string' &&
				nested.label_required_when_sibling_path_nonempty !== ''
			) {
				const raw = this.getAtPath(
					this.current,
					nested.label_required_when_sibling_path_nonempty
				);
				return typeof raw === 'string' && raw.trim() !== '';
			}
			if (nested.type === 'radios') {
				return this.subfieldsMarkedRequired;
			}
			return false;
		},
		nestedCpFieldLabelByPath(path) {
			const m = this.meta?.[META_KEY_CP_NESTED_FIELD_LABELS];
			if (m && typeof m === 'object' && typeof m[path] === 'string') {
				return m[path];
			}
			return '';
		},
		optionalNoneLabelForNested(nested) {
			if (!nested.allow_optional_none) {
				return '';
			}
			const m = this.meta?.[META_KEY_OPTIONAL_RADIO_NONE_LABELS_BY_SUBFIELD];
			if (!m || typeof m !== 'object') {
				return '';
			}
			if (typeof m[nested.storage_path] === 'string') {
				return m[nested.storage_path];
			}
			if (typeof m[nested.nested_key] === 'string') {
				return m[nested.nested_key];
			}
			return '';
		},
		cpSubfieldLabel(key) {
			const m = this.meta?.[META_KEY_CP_SUBFIELD_LABELS];
			if (m && typeof m === 'object' && typeof m[key] === 'string') {
				return m[key];
			}
			return key;
		},
		optionalNoneLabelFor(subfieldKey) {
			const m = this.meta?.[META_KEY_OPTIONAL_RADIO_NONE_LABELS_BY_SUBFIELD];
			if (
				m &&
				typeof m === 'object' &&
				typeof m[subfieldKey] === 'string'
			) {
				return m[subfieldKey];
			}
			return '';
		},
		subfieldInputId(key) {
			const base = String(this.publishFieldPathPrefix).replace(
				/[^a-zA-Z0-9_-]/g,
				'_'
			);
			return `field_${base}_${String(key).replace(/[^a-zA-Z0-9_-]/g, '_')}`;
		},
		subfieldInputIdFromPath(path) {
			const base = String(this.publishFieldPathPrefix).replace(
				/[^a-zA-Z0-9_-]/g,
				'_'
			);
			return `field_${base}_${String(path).replace(/[^a-zA-Z0-9_-]/g, '_')}`;
		},
		/**
		 * Whether a preload option row applies to the given configuration handle.
		 *
		 * @param {{ available_in?: string[] }} row
		 * @param {string} configuration
		 */
		optionRowAppliesToConfiguration(row, configuration) {
			const a = row?.available_in;
			if (!Array.isArray(a) || a.length === 0) {
				return true;
			}
			return typeof configuration === 'string' && a.includes(configuration);
		},
		/**
		 * Build `{ value, label }` rows from `meta.option_label_maps` for the
		 * active `configuration` (array rows with `key`, `label`, `available_in`).
		 *
		 * @param {string} mapKey
		 * @returns {Array<{ value: string, label: string }>}
		 */
		optionRowsFromMeta(mapKey) {
			const raw = this.meta?.option_label_maps?.[mapKey];
			const cfg =
				typeof this.current?.configuration === 'string'
					? this.current.configuration
					: '';
			if (!Array.isArray(raw)) {
				return [];
			}
			return raw
				.filter(
					(row) =>
						row &&
						typeof row.key === 'string' &&
						this.optionRowAppliesToConfiguration(row, cfg)
				)
				.map((row) => ({
					value: row.key,
					label:
						typeof row.label === 'string' && row.label !== ''
							? row.label
							: row.key
				}));
		},
		/**
		 * Option rows for a nested `group.fields` radios field.
		 *
		 * @param {{ storage_path?: string, option_map_key?: string }} nested
		 * @returns {Array<{ value: string, label: string }>}
		 */
		nestedOptionRows(nested) {
			return this.optionRowsFromMeta(nested?.option_map_key);
		},
		coerceConfiguration(merged) {
			const enabled = this.enabledConfigurationKeys;
			const structs = this.enabledTitleStructureKeys;
			let next = { ...merged };
			if (!enabled.includes(next.configuration)) {
				next = {
					...next,
					configuration: enabled[0] ?? null
				};
			}
			if (typeof next.mode !== 'string' || !structs.includes(next.mode)) {
				next = {
					...next,
					mode: structs[0] ?? MODE_STRUCTURED
				};
			}
			return next;
		},
		cpReplicatorValue(cpKey) {
			const v = this.current[cpKey];
			return Array.isArray(v) ? v : [];
		},
		unstructuredReplicatorMeta(cpKey) {
			const map = {
				[CP_UNSTRUCTURED_ISCO]: 'unstructured_replicator_isco_meta',
				[CP_UNSTRUCTURED_ONET]: 'unstructured_replicator_onet_meta',
				[CP_UNSTRUCTURED_OCC_SAME_AS]: 'unstructured_replicator_occ_same_as_meta',
				[CP_UNSTRUCTURED_FIELD_SAME_AS]: 'unstructured_replicator_field_same_as_meta'
			};
			const preloadKey = map[cpKey];
			const pre =
				this.meta &&
				typeof this.meta[preloadKey] === 'object' &&
				this.meta[preloadKey] !== null &&
				!Array.isArray(this.meta[preloadKey])
					? this.meta[preloadKey]
					: null;
			const live =
				this.meta &&
				typeof this.meta[cpKey] === 'object' &&
				this.meta[cpKey] !== null &&
				!Array.isArray(this.meta[cpKey])
					? this.meta[cpKey]
					: null;
			const preObj =
				pre && typeof pre === 'object' ? pre : { new: {}, defaults: {} };
			const raw =
				live && typeof live === 'object'
					? { ...preObj, ...live }
					: preObj;
			const newMeta =
				raw.new && typeof raw.new === 'object' ? { ...raw.new } : {};
			const newDefaults =
				raw.defaults && typeof raw.defaults === 'object'
					? { ...raw.defaults }
					: {};
			let existing =
				raw.existing &&
				typeof raw.existing === 'object' &&
				!Array.isArray(raw.existing)
					? { ...raw.existing }
					: {};
			const rows = this.cpReplicatorValue(cpKey);
			if (Array.isArray(rows)) {
				for (const row of rows) {
					const id = row && typeof row === 'object' ? row._id : null;
					const type = row && typeof row === 'object' ? row.type : null;
					if (!id || !type || !Object.prototype.hasOwnProperty.call(newMeta, type)) {
						continue;
					}
					if (!Object.prototype.hasOwnProperty.call(existing, id)) {
						existing[id] = JSON.parse(JSON.stringify(newMeta[type]));
					}
				}
			}
			const collapsed = Array.isArray(raw.collapsed) ? [...raw.collapsed] : [];
			return {
				new: newMeta,
				defaults: newDefaults,
				existing,
				collapsed
			};
		},
		onUnstructuredReplicatorMeta(cpKey, meta) {
			const setFieldMeta = this.publishContext?.setFieldMeta;
			if (typeof setFieldMeta !== 'function') {
				return;
			}
			setFieldMeta(`${this.publishMetaPathPrefix}.${cpKey}`, meta);
		},
		onTitleStructureChange(mode) {
			if (mode === MODE_UNSTRUCTURED) {
				const cfg = this.enabledConfigurationKeys[0] ?? null;
				const next = emptyRoleTitle(cfg, this.meta);
				next.mode = MODE_UNSTRUCTURED;
				next.configuration = null;
				next.value = '';
				next[CP_UNSTRUCTURED_ISCO] = [];
				next[CP_UNSTRUCTURED_ONET] = [];
				next[CP_UNSTRUCTURED_OCC_SAME_AS] = [];
				next[CP_UNSTRUCTURED_FIELD_SAME_AS] = [];
				next.occupation = {
					same_as: [],
					category_code: { isco_08: [], onet_soc: [] }
				};
				next.occupational_field = { same_as: [] };
				this.update(next);
				return;
			}
			const cfg =
				typeof this.meta?.default_configuration === 'string'
					? this.meta.default_configuration
					: null;
			const next = emptyRoleTitle(cfg, this.meta);
			next.mode = MODE_STRUCTURED;
			this.update(next);
		},
		errs(subPath) {
			// Resolve nested publish errors for this field value
			const errs =
				this.publishContext?.errors &&
				typeof this.publishContext.errors === 'object' &&
				'value' in this.publishContext.errors
					? this.publishContext.errors.value
					: this.publishContext?.errors;
			if (!errs) {
				return [];
			}
			const key = `${this.publishFieldPathPrefix}.${subPath}`;
			return errs[key] || [];
		},
		patch(key, val) {
			const next = { ...this.current, [key]: val };
			this.update(next);
		},
		onConfigurationChange(cfg) {
			const next = emptyRoleTitle(cfg, this.meta);
			if (this.current.mode === MODE_UNSTRUCTURED) {
				next.mode = MODE_UNSTRUCTURED;
			}
			this.update(next);
		}
	}
};
</script>
