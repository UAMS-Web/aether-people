<template>
	<div class="person-name-fieldtype space-y-6 min-w-0">
		<group-fieldtype-shell>
			<template v-if="includePhoneticInstructions">
				<phonetic-text-instructions-alert />
			</template>

			<text-phonetic-pair
				:pair="current.first_name"
				:text-label="__('aether-people::fieldtypes.person_name.field.first_name_text')"
				:phonetic-label="__('aether-people::fieldtypes.person_name.field.first_name_phonetic_text')"
				:text-id="elementId('fn_text')"
				:pho-id="elementId('fn_pho')"
				:text-name="handle+'[first_name][text]'"
				:pho-name="handle+'[first_name][phonetic_text]'"
				:text-required="mustRequireText"
				:phonetic-required="mustRequirePhonetic"
				:include-phonetic="includePhonetic"
				:read-only="isReadOnly"
				:text-errors="errs('first_name.text')"
				:phonetic-errors="errs('first_name.phonetic_text')"
				@update:pair="patch('first_name', $event)"
			/>

			<Separator />

			<div class="grid grid-cols-1 gap-4 items-start">
				<div class="space-y-2 min-w-0">
					<Label>{{ __('aether-people::fieldtypes.person_name.field.no_middle_name') }}</Label>
					<Switch
						:model-value="current.no_middle_name"
						:disabled="isReadOnly"
						@update:model-value="onNoMiddleName($event)"
					/>
				</div>
				<div
					v-if="!current.no_middle_name"
					class="min-w-0"
				>
					<text-phonetic-pair
						:pair="current.middle_name"
						:text-label="__('aether-people::fieldtypes.person_name.field.middle_name_text')"
						:phonetic-label="__('aether-people::fieldtypes.person_name.field.middle_name_phonetic_text')"
						:text-id="elementId('mn_text')"
						:pho-id="elementId('mn_pho')"
						:text-name="handle+'[middle_name][text]'"
						:pho-name="handle+'[middle_name][phonetic_text]'"
						:text-required="mustRequireText"
						:phonetic-required="mustRequirePhonetic"
						:include-phonetic="includePhonetic"
						:read-only="isReadOnly"
						:text-errors="errs('middle_name.text')"
						:phonetic-errors="errs('middle_name.phonetic_text')"
						@update:pair="patch('middle_name', $event)"
					/>
				</div>
			</div>

			<template v-if="includeNickname">
				<Separator />

				<div class="grid grid-cols-1 gap-4 items-start">
					<div class="space-y-2 min-w-0">
						<Label>{{ __('aether-people::fieldtypes.person_name.field.no_nickname') }}</Label>
						<Switch
							:model-value="current.no_nickname"
							:disabled="isReadOnly"
							@update:model-value="onNoNickname($event)"
						/>
					</div>
					<div
						v-if="!current.no_nickname"
						class="min-w-0"
					>
						<text-phonetic-pair
							:pair="current.nickname"
							:text-label="__('aether-people::fieldtypes.person_name.field.nickname_text')"
							:phonetic-label="__('aether-people::fieldtypes.person_name.field.nickname_phonetic_text')"
							:text-id="elementId('nick_text')"
							:pho-id="elementId('nick_pho')"
							:text-name="handle+'[nickname][text]'"
							:pho-name="handle+'[nickname][phonetic_text]'"
							:quote-straight-on-text="true"
							:text-required="mustRequireText"
							:phonetic-required="mustRequirePhonetic"
							:include-phonetic="includePhonetic"
							:read-only="isReadOnly"
							:text-errors="errs('nickname.text')"
							:phonetic-errors="errs('nickname.phonetic_text')"
							@update:pair="patch('nickname', $event)"
						/>
					</div>
				</div>
			</template>

			<Separator />

			<text-phonetic-pair
				:pair="current.last_name"
				:text-label="__('aether-people::fieldtypes.person_name.field.last_name_text')"
				:phonetic-label="__('aether-people::fieldtypes.person_name.field.last_name_phonetic_text')"
				:text-id="elementId('ln_text')"
				:pho-id="elementId('ln_pho')"
				:text-name="handle+'[last_name][text]'"
				:pho-name="handle+'[last_name][phonetic_text]'"
				:text-required="mustRequireText"
				:phonetic-required="mustRequirePhonetic"
				:include-phonetic="includePhonetic"
				:read-only="isReadOnly"
				:text-errors="errs('last_name.text')"
				:phonetic-errors="errs('last_name.phonetic_text')"
				@update:pair="patch('last_name', $event)"
			/>

			<Separator />

			<div class="grid grid-cols-1 gap-4 items-start">
				<div class="space-y-2 min-w-0">
					<Label>{{ __('aether-people::fieldtypes.person_name.field.no_generational_suffix') }}</Label>
					<Switch
						:model-value="current.no_generational_suffix"
						:disabled="isReadOnly"
						@update:model-value="onNoGen($event)"
					/>
				</div>
				<div
					v-if="!current.no_generational_suffix"
					class="min-w-0"
				>
					<Label
						class="mb-1 block"
						:for="elementId('gen')"
						:required="generationalSuffixLabelRequired"
					>{{ __('aether-people::fieldtypes.person_name.field.generational_suffix') }}</Label>
					<PersonNameGenerationalSuffix
						:id="elementId('gen')"
						:model-value="current.generational_suffix"
						:options="suffixOptions"
						:read-only="isReadOnly"
						@update:model-value="patch('generational_suffix', $event)"
					/>
					<ErrorMessage
						v-for="(err, i) in errs('generational_suffix')"
						:key="'ge'+i"
						class="mt-1"
						:text="err"
					/>
				</div>
			</div>

			<template v-if="includeDisplayFormat">
				<Separator />

				<Field
					:label="__('aether-people::fieldtypes.person_name.field.preferred_display_format')"
					:instructions="__('aether-people::fieldtypes.person_name.field.display_format_instructions')"
					:required="fieldMarkedRequired"
				>
					<PersonNameDisplayFormatSelect
						:id="elementId('df')"
						:model-value="current.display_format"
						:name-state="current"
						:include-nickname="includeNickname"
						:options="displayFormatOptions"
						:read-only="isReadOnly"
						:format-errors="errs('display_format')"
						@update:model-value="patch('display_format', $event)"
					/>
				</Field>
			</template>
		</group-fieldtype-shell>
	</div>
</template>

<script>
/**
 * The Control Panel editor for the `person_name` fieldtype.
 *
 * `group-fieldtype-shell`, `text-phonetic-pair` and
 * `phonetic-text-instructions-alert` are not imported here: the host
 * application registers them globally in its own Control Panel bundle, and
 * Vue resolves them by name when this component renders, so the fieldtype
 * needs a host that registers all three.
 */
import { inject } from 'vue';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import {
	ErrorMessage,
	Field,
	Label,
	Separator,
	Switch,
	publishContextKey
} from '@statamic/cms/ui';
import { buildDisplayFormatOptions } from './person-name/personNameDisplayFormats.js';
import PersonNameDisplayFormatSelect from './person-name/PersonNameDisplayFormatSelect.vue';
import PersonNameGenerationalSuffix from './person-name/PersonNameGenerationalSuffix.vue';
import { subfieldId } from './partials/subfieldId.js';

function emptyPersonName() {
	return {
		first_name: { text: '', phonetic_text: '' },
		no_middle_name: false,
		middle_name: { text: '', phonetic_text: '' },
		no_nickname: false,
		nickname: { text: '', phonetic_text: '' },
		last_name: { text: '', phonetic_text: '' },
		no_generational_suffix: false,
		generational_suffix: null,
		display_format: null
	};
}

export default {
	name: 'PersonNameFieldtype',
	components: {
		ErrorMessage,
		Field,
		Label,
		PersonNameDisplayFormatSelect,
		PersonNameGenerationalSuffix,
		Separator,
		Switch
	},
	mixins: [Fieldtype],
	setup() {
		const publishContext = inject(publishContextKey, null);
		return { publishContext };
	},
	computed: {
		/**
		 * Blueprint `required` or `validate: [required]` (matches PersonName::extraRules).
		 */
		fieldValidateHasRequired() {
			const v = this.config?.validate;
			return Array.isArray(v) && v.includes('required');
		},
		fieldMarkedRequired() {
			return !!(this.config?.required || this.fieldValidateHasRequired);
		},
		/**
		 * Text subfields use required rules when the field is required or
		 * `require_text` is enabled (see PersonName::extraRules `$mustText`).
		 */
		mustRequireText() {
			return this.fieldMarkedRequired || !!this.config?.require_text;
		},
		/**
		 * Phonetic subfields when `require_phonetic` is set in fieldtype config.
		 */
		mustRequirePhonetic() {
			return this.includePhonetic && !!this.config?.require_phonetic;
		},
		/**
		 * Suffix is required when using a suffix and text rules apply
		 * (PersonName::suffixRules).
		 */
		generationalSuffixLabelRequired() {
			return this.mustRequireText && !this.current.no_generational_suffix;
		},
		current() {
			const v = this.value;
			const base = emptyPersonName();
			if (!v || typeof v !== 'object' || Array.isArray(v)) {
				return base;
			}
			return {
				...base,
				...v,
				first_name: { ...base.first_name, ...(v.first_name || {}) },
				middle_name: { ...base.middle_name, ...(v.middle_name || {}) },
				nickname: { ...base.nickname, ...(v.nickname || {}) },
				last_name: { ...base.last_name, ...(v.last_name || {}) }
			};
		},
		suffixOptions() {
			return (
				this.meta?.generational_suffix_options ||
				this.config?.generational_suffix_options ||
				[]
			);
		},
		displayFormatOptions() {
			return buildDisplayFormatOptions(this.current, {
				includeNickname: this.includeNickname
			});
		},
		/**
		 * Blueprint `include_phonetic_instructions` (default true): hide IPA help Alert when false.
		 */
		includePhoneticInstructions() {
			return this.config?.include_phonetic_instructions !== false;
		},
		/**
		 * Blueprint `include_display_format` (default true): hide display format UI when false.
		 */
		includeDisplayFormat() {
			return this.config?.include_display_format !== false;
		},
		/**
		 * Blueprint `include_phonetic` (default true): hide each part's phonetic
		 * text when false, because the pronunciation is kept in its own field (UAMS-Web/uams-statamic-archive#3186).
		 */
		includePhonetic() {
			return this.config?.include_phonetic !== false;
		},
		/**
		 * Blueprint `include_nickname` (default true): hide nickname UI when false.
		 */
		includeNickname() {
			return this.config?.include_nickname !== false;
		}
	},
	methods: {
		/** The id of one control here, carrying the publish container (UAMS-Web/uams-statamic#336). */
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
		patch(key, val) {
			const next = { ...this.current, [key]: val };
			this.update(next);
		},
		onNoGen(v) {
			const next = {
				...this.current,
				no_generational_suffix: v,
				generational_suffix: v ? null : this.current.generational_suffix
			};
			this.update(next);
		},
		onNoMiddleName(v) {
			const next = {
				...this.current,
				no_middle_name: v,
				middle_name: v
					? { text: null, phonetic_text: null }
					: {
							text: this.current.middle_name?.text ?? '',
							phonetic_text: this.current.middle_name?.phonetic_text ?? ''
						}
			};
			this.update(next);
		},
		onNoNickname(v) {
			const next = {
				...this.current,
				no_nickname: v,
				nickname: v
					? { text: null, phonetic_text: null }
					: {
							text: this.current.nickname?.text ?? '',
							phonetic_text: this.current.nickname?.phonetic_text ?? ''
						}
			};
			this.update(next);
		}
	}
};
</script>
