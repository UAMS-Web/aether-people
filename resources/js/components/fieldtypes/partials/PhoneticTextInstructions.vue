<!--
 * Shared CP alert: IPA / phonetic text guidance for fieldtypes that collect
 * phonetic transcription (PersonName, TextPhoneticPair). Its text is the
 * add-on's `aether-people::fieldtypes.phonetic_instructions.*` keys.
 -->
<template>
	<Alert :heading="__('aether-people::fieldtypes.phonetic_instructions.alert_heading')">
		<Heading>{{ __('aether-people::fieldtypes.phonetic_instructions.alert_heading') }}</Heading>
		<Description>
			<p>{{ __('aether-people::fieldtypes.phonetic_instructions.p1') }}</p>
			<p v-html="__('aether-people::fieldtypes.phonetic_instructions.p2_html')" />
			<p v-html="__('aether-people::fieldtypes.phonetic_instructions.p3_html')" />
			<p v-html="__('aether-people::fieldtypes.phonetic_instructions.p4_html')" />
			<p v-html="__('aether-people::fieldtypes.phonetic_instructions.p5_html')" />
		</Description>
	</Alert>
</template>

<script>
import { Alert, Description, Heading } from '@statamic/cms/ui';

export default {
	name: 'PhoneticTextInstructions',
	components: {
		Alert,
		Description,
		Heading
	}
};
</script>
