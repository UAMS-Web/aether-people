<!--
 * Chained ISCO-08 selector: major → sub-major → minor → unit. The `value` key
 * (resolved 1–4 digit code) is not shown here but is kept in sync for the publish
 * payload; PHP always recomputes it from the tier keys on save. Sentinel tiers use
 * `false` in the CP payload, normalized to `null` in YAML by PHP.
 -->
<template>
	<section class="w-full">
		<div class="bg-white dark:bg-gray-800 dark:border-dark-900 rounded-lg border">
			<div class="publish-fields pt-4 px-4 py-4 space-y-2">
				<div
					v-for="row in rows"
					:key="row.key"
					class="min-w-0 form-group select-fieldtype field-w-undefined"
					data-ui-input-group
				>
					<div data-ui-field-text="" class="mb-1.5">
						<label
							:for="inputId(row.key)"
							class="flex justify-between mb-1.5 text-sm font-medium [&_button]:font-medium text-gray-925 select-none dark:text-gray-300"
							data-ui-label=""
						>
							<span>{{ row.label }}</span>
						</label>
					</div>
					<div dir="ltr">
						<Combobox
							:id="inputId(row.key)"
							:model-value="comboboxModelFor(row.key)"
							:options="row.comboboxOptions"
							:placeholder="__('Select…')"
							:clearable="true"
							:disabled="isReadOnly"
							:read-only="isReadOnly"
							:searchable="true"
							:adaptive-width="true"
							@update:model-value="(v) => onLevelChange(row, v)"
						/>
					</div>
					<Alert
						v-if="row.alertDetail"
						variant="default"
						class="mt-3"
					>
						<h2 class="text-lg">{{ row.alertDetail.titleLine }}</h2>
						<p
							v-if="row.alertDetail.description"
							class="mt-2 text-sm text-gray-600 dark:text-gray-400"
						>
							{{ row.alertDetail.description }}
						</p>
					</Alert>
				</div>
			</div>
		</div>
	</section>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Alert, Combobox } from '@statamic/cms/ui';

const NONE_SENTINEL = '__isco_08_none__';

export default {
	components: { Alert, Combobox },

	mixins: [Fieldtype],

	computed: {
		/**
		 * @returns {object}
		 */
		pre() {
			return this.meta || {};
		},

		/**
		 * @returns {object}
		 */
		stored() {
			return this.value && typeof this.value === 'object' && !Array.isArray(this.value)
				? this.value
				: {};
		},

		/**
		 * @returns {Array<{key: string, label: string, comboboxOptions: Array, options: Array, showNone: boolean, alertDetail: { titleLine: string, description: string } | null}>}
		 */
		rows() {
			// Read preload metadata for majors and parent→children maps
			const meta = this.pre;
			const majors = meta.majors || [];
			const byParent = meta.children_by_parent || {};
			// Snapshot the current stored tier object
			const v = this.stored;

			const tiers = [];

			// Always expose the major-group row first
			tiers.push({
				key: 'major_group',
				label: __('aether-people::fieldtypes.isco_08.field.major_group'),
				options: majors,
				showNone: false,
			});

			const major = v.major_group;
			// Check whether a major code is selected so deeper tiers can appear
			if (typeof major === 'string' && major !== '') {
				// Build sub-major options for the selected major
				const subOpts = byParent[major] || [];
				tiers.push({
					key: 'sub_major_group',
					label: __('aether-people::fieldtypes.isco_08.field.sub_major_group'),
					options: subOpts,
					showNone: true,
				});

				const sub = v.sub_major_group;
				// Check whether a concrete sub-major code is selected (not sentinel)
				if (typeof sub === 'string' && sub !== '') {
					// Build minor options for the selected sub-major
					const minorOpts = byParent[sub] || [];
					tiers.push({
						key: 'minor_group',
						label: __('aether-people::fieldtypes.isco_08.field.minor_group'),
						options: minorOpts,
						showNone: true,
					});

					const minor = v.minor_group;
					// Check whether a concrete minor code is selected
					if (typeof minor === 'string' && minor !== '') {
						// Build unit options for the selected minor
						const unitOpts = byParent[minor] || [];
						tiers.push({
							key: 'unit_group',
							label: __('aether-people::fieldtypes.isco_08.field.unit_group'),
							options: unitOpts,
							showNone: true,
						});
					}
				}
			}

			// Attach combobox option lists and per-tier summary alerts for concrete selections
			return tiers.map((t) => {
				const comboboxOptions = t.showNone ? this.withNoneComboboxOptions(t.options) : this.baseComboboxOptions(t.options);
				const raw = v[t.key];
				let alertDetail = null;

				if (typeof raw === 'string' && raw !== '') {
					const preloadRow = (t.options || []).find((o) => o.key === raw) ?? this.findPreloadRowByCode(raw);

					if (preloadRow) {
						alertDetail = {
							titleLine: preloadRow.value,
							description: typeof preloadRow.description === 'string' ? preloadRow.description : '',
						};
					}
				}

				return {
					...t,
					comboboxOptions,
					alertDetail,
				};
			});
		},
	},

	methods: {
		/**
		 * @param {string} code
		 * @returns {{ key: string, value: string, description?: string }|null}
		 */
		findPreloadRowByCode(code) {
			const meta = this.pre;
			const majors = meta.majors || [];
			const hitMajor = majors.find((r) => r.key === code);

			if (hitMajor) {
				return hitMajor;
			}

			const byParent = meta.children_by_parent || {};

			for (const rows of Object.values(byParent)) {
				const hit = rows.find((r) => r.key === code);

				if (hit) {
					return hit;
				}
			}

			return null;
		},

		/**
		 * @param {string} key
		 * @returns {string}
		 */
		inputId(key) {
			return `${this.fieldId}-${key}`;
		},

		/**
		 * @param {Array<{key: string, value: string}>} options
		 * @returns {Array<{value: string, label: string}>}
		 */
		baseComboboxOptions(options) {
			// Map preload rows to combobox `{ value, label }` entries
			return (options || []).map((o) => ({
				value: o.key,
				label: o.value,
			}));
		},

		/**
		 * @param {Array<{key: string, value: string}>} options
		 * @returns {Array<{value: string, label: string}>}
		 */
		withNoneComboboxOptions(options) {
			// Build the standard code/title rows first
			const base = this.baseComboboxOptions(options);

			// Prepend the explicit “stop at this tier” sentinel option
			return [
				{
					value: NONE_SENTINEL,
					label: __('aether-people::fieldtypes.isco_08.option.none'),
				},
				...base,
			];
		},

		/**
		 * @param {string} key
		 * @returns {string|null}
		 */
		comboboxModelFor(key) {
			// Read the raw tier value from the publish payload
			const raw = this.stored[key];

			// Map CP sentinel `false` to the combobox “none” token
			if (raw === false) {
				return NONE_SENTINEL;
			}

			// Treat empty tiers as an unset combobox selection
			if (raw === undefined || raw === null || raw === '') {
				return null;
			}

			// Return the selected ISCO code string
			return raw;
		},

		/**
		 * @param {{key: string}} row
		 * @param {string|null} picked
		 */
		onLevelChange(row, picked) {
			// Clone the stored object so Vue reactivity sees a replacement
			const next = { ...this.stored };

			// Check if the editor cleared the combobox selection
			if (picked === null || picked === undefined || picked === '') {
				// Remove this tier and any inconsistent deeper tiers
				delete next[row.key];
				this.pruneDescendants(next, row.key);
				// Recompute the hidden `value` key for the publish payload
				this.applyResolvedValue(next);
				// Push the updated object back to Statamic
				this.update(next);
				return;
			}

			// Check if the editor chose the explicit “none” sentinel row
			if (picked === NONE_SENTINEL) {
				// Persist sentinel intent as `false` until PHP normalizes to null
				next[row.key] = false;
				this.pruneDescendants(next, row.key);
				this.applyResolvedValue(next);
				this.update(next);
				return;
			}

			// Store the selected structure code for this tier
			next[row.key] = picked;
			this.pruneDescendants(next, row.key);
			this.applyResolvedValue(next);
			this.update(next);
		},

		/**
		 * @param {object} data
		 */
		applyResolvedValue(data) {
			// Derive the deepest selected code from the tier chain
			const resolved = this.computeResolvedValue(data);

			// Check whether any tier yields a resolvable code
			if (resolved === null || resolved === undefined || resolved === '') {
				// Drop `value` when the chain does not resolve to a code
				delete data.value;
			} else {
				// Mirror PHP’s `value` so the CP payload stays consistent before save
				data.value = resolved;
			}
		},

		/**
		 * @param {object} data
		 * @param {string} editedKey
		 */
		pruneDescendants(data, editedKey) {
			// Compute the ordered tier keys implied by the current selections
			const order = this.chainKeyOrder(data);
			// Find the edited tier’s index in that order
			const idx = order.indexOf(editedKey);

			// Abort when the edited key is not part of the active chain
			if (idx === -1) {
				return;
			}

			// Keep tiers up to and including the edited row; drop deeper keys
			const keep = new Set(order.slice(0, idx + 1));

			// Remove any tier keys outside the kept prefix
			['major_group', 'sub_major_group', 'minor_group', 'unit_group'].forEach((k) => {
				if (!keep.has(k)) {
					delete data[k];
				}
			});
		},

		/**
		 * @param {object} data
		 * @returns {string[]}
		 */
		chainKeyOrder(data) {
			// Start the chain with the major tier only
			const keys = ['major_group'];

			// Stop after major when no major is selected
			if (!data.major_group) {
				return keys;
			}

			keys.push('sub_major_group');

			const sub = data.sub_major_group;
			// Stop after sub-major when sentinel or unset blocks deeper tiers
			if (sub === false || sub === undefined || sub === null || sub === '') {
				return keys;
			}

			keys.push('minor_group');

			const minor = data.minor_group;
			// Stop after minor when sentinel or unset blocks the unit tier
			if (minor === false || minor === undefined || minor === null || minor === '') {
				return keys;
			}

			keys.push('unit_group');

			return keys;
		},

		/**
		 * @param {object} data
		 * @returns {string|null}
		 */
		computeResolvedValue(data) {
			// Walk tiers in major→unit order
			const order = this.chainKeyOrder(data);
			let previous = null;

			for (let i = 0; i < order.length; i++) {
				const k = order[i];
				const part = data[k];

				// Return the last concrete code before an explicit sentinel tier
				if (part === false) {
					return previous;
				}

				// Track the latest non-empty string code seen so far
				if (typeof part === 'string' && part !== '') {
					previous = part;
				}
			}

			// Return the deepest string code when no sentinel short-circuits the walk
			return previous;
		},
	},
};
</script>
