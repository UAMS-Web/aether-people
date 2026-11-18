<!--
 * Reusable CP control: IPA / phonetic transcription text input with slash
 * prepend/append (display hint), Label, and ErrorMessage list. Imported by
 * `TextPhoneticPair.vue` rather than registered globally. `custom-input` is the
 * host's global component.
 -->
<template>
	<div class="min-w-0">
		<div
			:class="labelWrapperClass"
			data-ui-field-text=""
		>
			<Label
				:for="id"
				:text="label"
				:required="labelRequired"
			/>
		</div>
		<custom-input
			:id="id"
			:name="name"
			:model-value="modelValue"
			:readonly="readOnly"
			:prepend="prependDisplay"
			:append="appendDisplay"
			type="text"
			:classes="mergedInputClasses"
			base-class="input-text"
			@update:model-value="$emit('update:modelValue', $event)"
		/>
		<ErrorMessage
			v-for="(err, i) in errors"
			:key="'pe'+i"
			class="mt-1"
			:text="err"
		/>
	</div>
</template>

<script>
import { ErrorMessage, Label } from '@statamic/cms/ui';

export default {
	name: 'PhoneticTextInput',
	components: {
		ErrorMessage,
		Label
	},
	props: {
		/** HTML id for the input (and label `for`). */
		id: {
			type: String,
			required: true
		},
		/** Form name attribute for the input. */
		name: {
			type: String,
			default: ''
		},
		/** Bound value (IPA symbols only in typical use). */
		modelValue: {
			type: [String, Number],
			default: ''
		},
		/** Visible label text. */
		label: {
			type: String,
			required: true
		},
		/** When true, Label shows the required asterisk. */
		labelRequired: {
			type: Boolean,
			default: false
		},
		readOnly: {
			type: Boolean,
			default: false
		},
		/** Validation messages for this input. */
		errors: {
			type: Array,
			default: () => []
		},
		/**
		 * Classes on the label row wrapper (e.g. `mb-1` or `mb-1 mt-4` when
		 * stacked below another field).
		 */
		labelWrapperClass: {
			type: String,
			default: 'mb-1'
		},
		/**
		 * Prepend/append shown as visual delimiters (default IPA slash hint).
		 * Pass null to omit (if custom-input treats null as no group).
		 */
		prepend: {
			type: String,
			default: '/'
		},
		append: {
			type: String,
			default: '/'
		},
		/** Optional extra classes for the inner input (merged with w-full). */
		inputClass: {
			type: String,
			default: ''
		}
	},
	emits: ['update:modelValue'],
	computed: {
		prependDisplay() {
			return this.prepend === '' ? null : this.prepend;
		},
		appendDisplay() {
			return this.append === '' ? null : this.append;
		},
		mergedInputClasses() {
			const base = { input: 'w-full' };
			if (this.inputClass) {
				base.input += ' ' + this.inputClass;
			}
			return base;
		}
	}
};
</script>
