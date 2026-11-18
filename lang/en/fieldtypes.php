<?php

declare(strict_types=1);

/**
 * Labels for the add-on's fieldtypes: the Control Panel publish form, the
 * blueprint settings and the validation attribute names.
 *
 * Read as `aether-people::fieldtypes.person_name.*` by
 * `UAMSWeb\Aether\People\Fieldtypes\PersonName::extraValidationAttributes()`, as
 * `aether-people::fieldtypes.text_phonetic_pair.*` by
 * `UAMSWeb\Aether\People\Fieldtypes\TextPhoneticPair`, and by the fieldtypes' Vue
 * components. The IPA help shared by both, `phonetic_instructions.*`, is read by the
 * `PhoneticTextInstructions` Vue partial (UAMS-Web/aether-people#123). A host
 * overrides any of them under `lang/vendor/aether-people/`.
 */
return [

    'person_name.field.display_format_instructions' => 'Choose how the name should be displayed.',
    'person_name.field.first_name_phonetic_text' => 'Full First Name (Phonetic Text)',
    'person_name.field.first_name_text' => 'Full First Name (Text)',
    'person_name.field.last_name_phonetic_text' => 'Full Last Name (Phonetic Text)',
    'person_name.field.last_name_text' => 'Full Last Name (Text)',
    'person_name.field.middle_name_phonetic_text' => 'Full Middle Name (Phonetic Text)',
    'person_name.field.middle_name_text' => 'Full Middle Name (Text)',
    'person_name.field.generational_suffix' => 'Generational Suffix',
    'person_name.field.nickname_phonetic_text' => 'Nickname (Phonetic Text)',
    'person_name.field.nickname_text' => 'Nickname (Text)',
    'person_name.field.no_generational_suffix' => 'No Generational Suffix',
    'person_name.field.no_middle_name' => 'No Middle Name',
    'person_name.field.no_nickname' => 'No Nickname',
    'person_name.field.preferred_display_format' => 'Preferred Display Format',
    'person_name.checklist.before_display_format' => 'Before you can select a display format, complete the following fields:',
    'person_name.checklist.first_name' => 'First name',
    'person_name.checklist.generational_suffix' => 'Generational suffix (or mark that there is no generational suffix)',
    'person_name.checklist.last_name' => 'Last name',
    'person_name.checklist.middle' => 'Middle name (or mark that there is no middle name)',
    'person_name.checklist.nickname' => 'Nickname (or mark that there is no nickname)',

    'phonetic_instructions.alert_heading' => 'Phonetic Text Transcription Instructions',
    'phonetic_instructions.p1' => 'Enter the pronunciation using International Phonetic Alphabet (IPA) symbols.',
    'phonetic_instructions.p2_html' => 'Enter the <strong>symbols</strong> only. Do not include forward slashes (<code>/ /</code>) or brackets (<code>[ ]</code>), as these will be added automatically.',
    'phonetic_instructions.p3_html' => 'Example: <code>təˈmeɪtoʊ</code> (stored without wrapping slashes; presentation may add them).',
    'phonetic_instructions.p4_html' => '<strong>Note:</strong> Do not use "phonetic respelling" (e.g., <code>tuh-MAY-toh</code>).',
    'phonetic_instructions.p5_html' => 'If you are unsure of the correct symbols, use a tool like <a href="https://tophonetics.com/" target="_blank" rel="noopener noreferrer">toPhonetics</a> / <a href="https://ipa-reader.com/" target="_blank" rel="noopener noreferrer">IPA Reader</a> or an IPA keyboard like <a href="https://ipa.typeit.org/full/" target="_blank" rel="noopener noreferrer">https://ipa.typeit.org/full/</a>.',

    'text_phonetic_pair.config.section_heading' => 'Text & phonetic pair',
    'text_phonetic_pair.config.text_label_display' => 'Text label',
    'text_phonetic_pair.config.text_label_instructions' => 'Label shown above the plain text input. Leave empty to use the default.',
    'text_phonetic_pair.config.phonetic_label_display' => 'Phonetic label',
    'text_phonetic_pair.config.phonetic_label_instructions' => 'Label shown for the IPA phonetic input. Leave empty to use the default.',
    'text_phonetic_pair.config.quote_straight_on_text_display' => 'Straight quotes on text',
    'text_phonetic_pair.config.quote_straight_on_text_instructions' => 'Show straight double quotes as decorative prepend/append on the text field (not stored).',
    'text_phonetic_pair.config.split_inputs_display' => 'Split layout (md+)',
    'text_phonetic_pair.config.split_inputs_instructions' => 'When enabled, text and phonetic inputs sit side by side from the medium breakpoint; when disabled, they stack full width.',
    'text_phonetic_pair.config.border_display' => 'Bordered container',
    'text_phonetic_pair.config.border_instructions' => 'Wrap the pair in the same bordered card shell as a Group field.',
    'text_phonetic_pair.config.body_class_display' => 'Body class',
    'text_phonetic_pair.config.body_class_instructions' => 'Optional Tailwind / utility classes for the inner wrapper (default: vertical spacing).',
    'text_phonetic_pair.config.require_text_display' => 'Require text',
    'text_phonetic_pair.config.require_text_instructions' => 'When enabled, the text value must be filled (or the field is marked required in the blueprint).',
    'text_phonetic_pair.config.require_phonetic_display' => 'Require phonetic text',
    'text_phonetic_pair.config.require_phonetic_instructions' => 'When enabled, the phonetic (IPA) value must be filled.',
    'text_phonetic_pair.config.include_phonetic_instructions_display' => 'Include phonetic text instructions',
    'text_phonetic_pair.config.include_phonetic_instructions_instructions' => 'When enabled, the IPA help alert is shown above the pair. When disabled, that alert is hidden.',
    'text_phonetic_pair.field.text' => 'Text',
    'text_phonetic_pair.field.phonetic_text' => 'Phonetic text',
    'text_phonetic_pair.field.default_text_label' => 'Text',
    'text_phonetic_pair.field.default_phonetic_label' => 'Phonetic text (IPA)',

];
