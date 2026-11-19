<!--
 * Optional subfield radios: first option is “none” (sentinel), stored as null.
 -->
<template>
	<div
		:id="inputId"
		class="space-y-2 min-w-0"
	>
		<RadioGroup
			:inline="inline"
			:model-value="radioModel"
			@update:model-value="onUpdate"
		>
			<Radio
				:value="noneValue"
				:label="noneLabel"
				:disabled="readOnly"
				:read-only="readOnly"
			/>
			<Radio
				v-for="opt in normalizedOptions"
				:key="String(opt.value)"
				:value="opt.value"
				:label="opt.label"
				:disabled="readOnly"
				:read-only="readOnly"
			/>
		</RadioGroup>
	</div>
</template>

<script>
import { Radio, RadioGroup } from '@statamic/cms/ui';

export default {
	name: 'RoleTitleOptionalRadioGroup',
	components: { Radio, RadioGroup },
	props: {
		modelValue: {
			default: null
		},
		options: {
			type: Array,
			default: () => []
		},
		noneLabel: {
			type: String,
			required: true
		},
		/**
		 * Sentinel value for the explicit “none” radio (from fieldtype preload).
		 */
		noneValue: {
			type: String,
			required: true
		},
		inputId: {
			type: String,
			required: true
		},
		inline: {
			type: Boolean,
			default: false
		},
		readOnly: {
			type: Boolean,
			default: false
		}
	},
	emits: ['update:modelValue'],
	computed: {
		radioModel() {
			const v = this.modelValue;
			// Map unset values to the none sentinel so RadioGroup stays controlled
			if (v === null || v === undefined || v === '') {
				return this.noneValue;
			}
			return v;
		},
		normalizedOptions() {
			// Coerce mixed option shapes to `{ value, label }` rows
			return (this.options || []).map((o) => {
				if (o && typeof o === 'object' && 'value' in o) {
					return {
						value: o.value,
						label: o.label || o.value
					};
				}
				return { value: o, label: String(o) };
			});
		}
	},
	methods: {
		onUpdate(v) {
			// Persist explicit none as null for optional radios
			if (v === this.noneValue) {
				this.$emit('update:modelValue', null);
			} else {
				this.$emit('update:modelValue', v);
			}
		}
	}
};
</script>
