<!--
 * Preferred display format for a person's name, kept in a field of its own
 * beside `person_name` (UAMS-Web/uams-statamic-archive#3186). The options are built from the name joined with
 * the nickname and pronunciation fields, read live from the publish form, so
 * they follow the name exactly as the format select inside `person_name` did.
 -->
<template>
	<PersonNameDisplayFormatSelect
		:id="fieldId"
		:model-value="current"
		:name-state="nameState"
		:options="options"
		:read-only="isReadOnly"
		:format-errors="formatErrors"
		@update:model-value="update($event)"
	/>
</template>

<script>
import { inject } from 'vue';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { publishContextKey } from '@statamic/cms/ui';
import { buildDisplayFormatOptions } from './person-name/personNameDisplayFormats.js';
import { composePersonName } from './person-name/personNameComposite.js';
import PersonNameDisplayFormatSelect from './person-name/PersonNameDisplayFormatSelect.vue';

export default {
	name: 'PersonNameDisplayFormatFieldtype',
	components: {
		PersonNameDisplayFormatSelect
	},
	mixins: [Fieldtype],
	setup() {
		const publishContext = inject(publishContextKey, null);
		return { publishContext };
	},
	computed: {
		fieldId() {
			// Statamic's id carries the publish container, and its label points at it (#327).
			return String(this.id || `field_${this.handle}`).replace(/\./g, '_');
		},
		current() {
			return typeof this.value === 'string' && this.value !== '' ? this.value : null;
		},
		/**
		 * The person's name joined with its refinement fields, from the form.
		 */
		nameState() {
			const values = this.publishContainer?.values ?? {};
			return composePersonName(values) ?? {};
		},
		options() {
			return buildDisplayFormatOptions(this.nameState);
		},
		formatErrors() {
			const errs =
				this.publishContext?.errors &&
				typeof this.publishContext.errors === 'object' &&
				'value' in this.publishContext.errors
					? this.publishContext.errors.value
					: this.publishContext?.errors;
			if (!errs) return [];
			return errs[this.handle] || [];
		}
	}
};
</script>
