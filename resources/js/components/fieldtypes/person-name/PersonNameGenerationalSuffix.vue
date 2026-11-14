<template>
	<Combobox
		:id="id"
		:model-value="modelValue"
		:options="formatOptions(options)"
		option-label="label"
		option-value="value"
		:placeholder="__('Select suffix')"
		:read-only="readOnly"
		:clearable="true"
		:searchable="true"
		@update:model-value="$emit('update:modelValue', $event)"
	/>
</template>

<script>
import { Combobox } from '@statamic/cms/ui';

export default {
	name: 'PersonNameGenerationalSuffix',
	components: { Combobox },
	props: {
		id: {
			type: String,
			required: true
		},
		modelValue: {
			type: [String, null],
			default: null
		},
		options: {
			type: Array,
			default: () => []
		},
		readOnly: {
			type: Boolean,
			default: false
		}
	},
	emits: ['update:modelValue'],
	methods: {
		formatOptions(opts) {
			if (!opts || !opts.length) return [];
			return opts.map((o) =>
				typeof o === 'object' && o !== null
					? {
							value: o.value ?? o.label,
							label:
								typeof __ !== 'undefined'
									? __(o.label ?? o.value ?? '')
									: o.label ?? o.value
						}
					: { value: o, label: o }
			);
		}
	}
};
</script>
