<?php

declare(strict_types=1);

/**
 * Labels for the person-name fieldtypes: the Control Panel publish form and the
 * validation attribute names.
 *
 * Read as `aether-people::fieldtypes.person_name.*` by
 * `UAMSWeb\Aether\People\Fieldtypes\PersonName::extraValidationAttributes()` and by
 * the fieldtypes' Vue components. The shared phonetic-text instructions stay in the
 * host's own `statamic::fieldtypes.phonetic_instructions.*`.
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
    'person_name.field.phonetic_alert_heading' => 'Phonetic Text Transcription Instructions',
    'person_name.field.preferred_display_format' => 'Preferred Display Format',
    'person_name.checklist.before_display_format' => 'Before you can select a display format, complete the following fields:',
    'person_name.checklist.first_name' => 'First name',
    'person_name.checklist.generational_suffix' => 'Generational suffix (or mark that there is no generational suffix)',
    'person_name.checklist.last_name' => 'Last name',
    'person_name.checklist.middle' => 'Middle name (or mark that there is no middle name)',
    'person_name.checklist.nickname' => 'Nickname (or mark that there is no nickname)',

];
