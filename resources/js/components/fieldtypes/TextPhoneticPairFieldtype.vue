<template>
	<div class="text-phonetic-pair-fieldtype min-w-0">
		<group-fieldtype-shell
			:border="shellBorder"
			:body-class="shellBodyClass"
		>
			<template v-if="includePhoneticInstructions">
				<PhoneticTextInstructions />
			</template>

			<TextPhoneticPairInput
				:pair="current"
				:text-label="resolvedTextLabel"
				:phonetic-label="resolvedPhoneticLabel"
				:text-id="elementId('tp_text')"
				:pho-id="elementId('tp_pho')"
				:text-name="handle+'[text]'"
				:pho-name="handle+'[phonetic_text]'"
				:quote-straight-on-text="quoteStraightOnText"
				:read-only="isReadOnly"
				:text-errors="errs('text')"
				:phonetic-errors="errs('phonetic_text')"
				:text-required="mustRequireText"
				:phonetic-required="mustRequirePhonetic"
				:split-inputs="splitInputs"
				@update:pair="onPairUpdate"
			/>
		</group-fieldtype-shell>
	</div>
</template>

<script>
/**
 * The Control Panel editor for the `text_phonetic_pair` fieldtype
 * (UAMS-Web/aether-people#123).
 *
 * The phonetic partials are the add-on's own, imported here. `group-fieldtype-shell`
 * and the `custom-input` the partials render are not: the host application
 * registers them globally in its own Control Panel bundle, as it does for the
 * `person_name` fieldtype, so this fieldtype needs a host that registers both.
 */
import { inject } from 'vue';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { publishContextKey } from '@statamic/cms/ui';
import PhoneticTextInstructions from './partials/PhoneticTextInstructions.vue';
import TextPhoneticPairInput from './partials/TextPhoneticPair.vue';
import { subfieldId } from './partials/subfieldId.js';

function emptyPair() {
	return { text: '', phonetic_text: '' };
}

export default {
	name: 'TextPhoneticPairFieldtype',
	components: {
		PhoneticTextInstructions,
		TextPhoneticPairInput
	},
	mixins: [Fieldtype],
	setup() {
		const publishContext = inject(publishContextKey, null);
		return { publishContext };
	},
	computed: {
		fieldValidateHasRequired() {
			const v = this.config?.validate;
			return Array.isArray(v) && v.includes('required');
		},
		fieldMarkedRequired() {
			return !!(this.config?.required || this.fieldValidateHasRequired);
		},
		mustRequireText() {
			return this.fieldMarkedRequired || !!this.config?.require_text;
		},
		mustRequirePhonetic() {
			return !!this.config?.require_phonetic;
		},
		shellBorder() {
			return this.config?.border !== false;
		},
		shellBodyClass() {
			const c = this.config?.body_class;
			return typeof c === 'string' && c.trim() !== '' ? c.trim() : 'space-y-6';
		},
		quoteStraightOnText() {
			return !!this.config?.quote_straight_on_text;
		},
		splitInputs() {
			return this.config?.split_inputs !== false;
		},
		/**
		 * Blueprint `include_phonetic_instructions` (default true): hide IPA help
		 * Alert when false (matches PersonName fieldtype).
		 */
		includePhoneticInstructions() {
			return this.config?.include_phonetic_instructions !== false;
		},
		resolvedTextLabel() {
			const raw = this.config?.text_label;
			if (typeof raw === 'string' && raw.trim() !== '') {
				return raw.trim();
			}
			return this.__('aether-people::fieldtypes.text_phonetic_pair.field.default_text_label');
		},
		resolvedPhoneticLabel() {
			const raw = this.config?.phonetic_label;
			if (typeof raw === 'string' && raw.trim() !== '') {
				return raw.trim();
			}
			return this.__('aether-people::fieldtypes.text_phonetic_pair.field.default_phonetic_label');
		},
		current() {
			const v = this.value;
			const base = emptyPair();
			if (!v || typeof v !== 'object' || Array.isArray(v)) {
				return base;
			}
			return {
				...base,
				...v,
				text: typeof v.text === 'string' ? v.text : '',
				phonetic_text:
					typeof v.phonetic_text === 'string' ? v.phonetic_text : ''
			};
		}
	},
	methods: {
		/** The id of one control here, carrying the publish container (#336). */
		elementId(control) {
			return subfieldId(this.id, this.handle, control);
		},
		errs(subPath) {
			const errs =
				this.publishContext?.errors &&
				typeof this.publishContext.errors === 'object' &&
				'value' in this.publishContext.errors
					? this.publishContext.errors.value
					: this.publishContext?.errors;
			if (!errs) return [];
			const key = `${this.handle}.${subPath}`;
			return errs[key] || [];
		},
		onPairUpdate(pair) {
			this.update(pair);
		}
	}
};
</script>
