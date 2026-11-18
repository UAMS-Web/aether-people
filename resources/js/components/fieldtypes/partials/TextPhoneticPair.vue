<!--
 * Reusable CP control: paired plain text (optional quote delimiters) and
 * phonetic (IPA) input. Emits `update:pair` with spread `pair` and updated
 * `text` / `phonetic_text` keys. Imported by the add-on's `person_name` and
 * `text_phonetic_pair` fieldtypes rather than registered globally, so they use
 * this copy whatever a host registers under the same name. `custom-input` is the
 * host's global component.
 -->
<template>
	<div class="min-w-0">
		<div
			v-if="splitInputs && includePhonetic"
			class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start min-w-0"
		>
			<div class="space-y-3 min-w-0">
				<div data-ui-field-text="" class="mb-1">
					<Label
						:for="textId"
						:text="textLabel"
						:required="textRequired"
					/>
				</div>
				<custom-input
					:id="textId"
					:name="textName"
					:model-value="pair.text"
					:readonly="readOnly"
					:prepend="quoteChar"
					:append="quoteChar"
					type="text"
					:classes="{ input: 'w-full' }"
					base-class="input-text"
					@update:model-value="onText"
				/>
				<ErrorMessage
					v-for="(err, i) in textErrors"
					:key="'te'+i"
					class="mt-1"
					:text="err"
				/>
			</div>
			<div class="min-w-0">
				<PhoneticTextInput
					:id="phoId"
					:name="phoName"
					:model-value="pair.phonetic_text"
					:label="phoneticLabel"
					:label-required="phoneticRequired"
					:read-only="readOnly"
					:errors="phoneticErrors"
					label-wrapper-class="mb-1"
					@update:model-value="onPhonetic"
				/>
			</div>
		</div>
		<div
			v-else
			class="space-y-3 min-w-0"
		>
			<div data-ui-field-text="" class="mb-1">
				<Label
					:for="textId"
					:text="textLabel"
					:required="textRequired"
				/>
			</div>
			<custom-input
				:id="textId"
				:name="textName"
				:model-value="pair.text"
				:readonly="readOnly"
				:prepend="quoteChar"
				:append="quoteChar"
				type="text"
				:classes="{ input: 'w-full' }"
				base-class="input-text"
				@update:model-value="onText"
			/>
			<ErrorMessage
				v-for="(err, i) in textErrors"
				:key="'te'+i"
				class="mt-1"
				:text="err"
			/>

			<PhoneticTextInput
				v-if="includePhonetic"
				:id="phoId"
				:name="phoName"
				:model-value="pair.phonetic_text"
				:label="phoneticLabel"
				:label-required="phoneticRequired"
				:read-only="readOnly"
				:errors="phoneticErrors"
				label-wrapper-class="mb-1 mt-4"
				@update:model-value="onPhonetic"
			/>
		</div>
	</div>
</template>

<script>
import { ErrorMessage, Label } from '@statamic/cms/ui';

import PhoneticTextInput from './PhoneticTextInput.vue';

export default {
	name: 'TextPhoneticPair',
	components: {
		ErrorMessage,
		Label,
		PhoneticTextInput
	},
	props: {
		pair: {
			type: Object,
			required: true
		},
		textLabel: {
			type: String,
			required: true
		},
		phoneticLabel: {
			type: String,
			required: true
		},
		textName: {
			type: String,
			default: ''
		},
		phoName: {
			type: String,
			default: ''
		},
		textId: {
			type: String,
			required: true
		},
		phoId: {
			type: String,
			required: true
		},
		/**
		 * Show straight double quotes as prepend/append on the text input only
		 * (display-only; stored values do not include quote characters).
		 */
		quoteStraightOnText: {
			type: Boolean,
			default: false
		},
		readOnly: {
			type: Boolean,
			default: false
		},
		textErrors: {
			type: Array,
			default: () => []
		},
		phoneticErrors: {
			type: Array,
			default: () => []
		},
		textRequired: {
			type: Boolean,
			default: false
		},
		phoneticRequired: {
			type: Boolean,
			default: false
		},
		/**
		 * When true (default), text and phonetic inputs sit in a 50/50 grid from
		 * the `md` breakpoint up; when false, each block is full width and stacked.
		 */
		splitInputs: {
			type: Boolean,
			default: true
		},
		/**
		 * When false, only the text input is shown: the phonetic text is kept
		 * in a field of its own (UAMS-Web/uams-statamic-archive#3186).
		 */
		includePhonetic: {
			type: Boolean,
			default: true
		}
	},
	emits: ['update:pair'],
	computed: {
		quoteChar() {
			return this.quoteStraightOnText ? '\u0022' : null;
		}
	},
	methods: {
		onText(v) {
			this.$emit('update:pair', {
				...this.pair,
				text: v
			});
		},
		onPhonetic(v) {
			this.$emit('update:pair', {
				...this.pair,
				phonetic_text: v
			});
		}
	}
};
</script>
