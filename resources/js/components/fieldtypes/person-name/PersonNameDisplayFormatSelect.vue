<template>
	<div class="space-y-4">
		<Combobox
			v-if="comboOptions.length"
			:id="id"
			:model-value="innerValue"
			:options="comboOptions"
			option-label="label"
			option-value="value"
			:placeholder="__('Select display format')"
			:read-only="readOnly"
			:clearable="true"
			:searchable="true"
			:adaptive-width="true"
			@update:model-value="onPick"
		/>
		<Alert
			variant="warning"
			v-if="!comboOptions.length"
		>
			<p>{{ __('aether-people::fieldtypes.person_name.checklist.before_display_format') }}</p>
			<ul class="list-disc list-inside space-y-1">
				<li v-if="unresolved.firstName">{{ __('aether-people::fieldtypes.person_name.checklist.first_name') }}</li>
				<li v-if="unresolved.middle">
					{{ __('aether-people::fieldtypes.person_name.checklist.middle') }}
				</li>
				<li v-if="unresolved.nickname">
					{{ __('aether-people::fieldtypes.person_name.checklist.nickname') }}
				</li>
				<li v-if="unresolved.lastName">{{ __('aether-people::fieldtypes.person_name.checklist.last_name') }}</li>
				<li v-if="unresolved.suffix">
					{{ __('aether-people::fieldtypes.person_name.checklist.generational_suffix') }}
				</li>
			</ul>
		</Alert>
		<ErrorMessage
			v-for="(err, i) in formatErrors"
			:key="'fe'+i"
			class="mt-1"
			:text="err"
		/>
	</div>
</template>

<script>
import {
	Alert,
	Combobox,
	ErrorMessage
} from '@statamic/cms/ui';

import { displayFormatUnresolvedPrerequisites } from './personNameDisplayFormats.js';

export default {
	name: 'PersonNameDisplayFormatSelect',
	components: {
		Alert,
		Combobox,
		ErrorMessage
	},
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
		},
		formatErrors: {
			type: Array,
			default: () => []
		},
		/**
		 * Current person_name value (same shape as `buildDisplayFormatOptions` input).
		 */
		nameState: {
			type: Object,
			default: () => ({})
		},
		/**
		 * Blueprint `include_nickname` (default true). When false, nickname is omitted
		 * from prerequisites and format options.
		 */
		includeNickname: {
			type: Boolean,
			default: true
		}
	},
	emits: ['update:modelValue'],
	computed: {
		unresolved() {
			return displayFormatUnresolvedPrerequisites(this.nameState, {
				includeNickname: this.includeNickname
			});
		},
		/**
		 * Single list: formats with generational suffix first, then without
		 * (order from `buildDisplayFormatOptions` by global `seq`).
		 */
		comboOptions() {
			return (this.options || []).map((o) => ({
				value: o.key,
				label: o.label
			}));
		},
		innerValue() {
			const allowed = new Set(this.comboOptions.map((o) => o.value));
			return this.modelValue && allowed.has(this.modelValue)
				? this.modelValue
				: null;
		}
	},
	watch: {
		options: {
			deep: true,
			handler() {
				const allowed = new Set(
					(this.options || []).map((o) => o.key)
				);
				if (this.modelValue && !allowed.has(this.modelValue)) {
					this.$emit('update:modelValue', null);
				}
			}
		}
	},
	methods: {
		onPick(v) {
			this.$emit('update:modelValue', v);
		}
	}
};
</script>
