<?php

declare(strict_types=1);

use Statamic\Fields\Field;
use UAMSWeb\Aether\People\Fieldtypes\PersonName;

/**
 * Feature tests for the PersonName fieldtype (process/extraRules/preload/
 * preProcessValidatable/extraValidationAttributes behaviors).
 *
 * Mutation: 100% on UAMSWeb\Aether\People\Fieldtypes\PersonName.
 *
 * @command  vendor/bin/pest tests/Integration/Fieldtypes/PersonNameFieldtypeTest.php
 */
it('strips the middle name on process when no middle name is set', function (): void {
    $field = new Field(
        'legal_name',
        [
            'type' => 'person_name',
        ]
    );

    // Assert on the call, not the annotated variable: the `@var` below is what makes
    // an instanceof over `$fieldtype` statically redundant.
    expect($field->fieldtype())->toBeInstanceOf(PersonName::class);

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'no_middle_name' => true,
        'middle_name' => [
            'text' => 'Should Clear',
            'phonetic_text' => 'x',
        ],
        'first_name' => ['text' => 'A', 'phonetic_text' => ''],
        'last_name' => ['text' => 'B', 'phonetic_text' => ''],
    ]);

    expect(data_get($out, 'middle_name.text'))->toBeNull();
    expect(data_get($out, 'middle_name.phonetic_text'))->toBeNull();
});

it('strips the nickname on process when no nickname is set', function (): void {
    $field = new Field(
        'legal_name',
        [
            'type' => 'person_name',
        ]
    );

    // Assert on the call, not the annotated variable: the `@var` below is what makes
    // an instanceof over `$fieldtype` statically redundant.
    expect($field->fieldtype())->toBeInstanceOf(PersonName::class);

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'no_nickname' => true,
        'nickname' => [
            'text' => 'Should Clear',
            'phonetic_text' => 'x',
        ],
        'first_name' => ['text' => 'A', 'phonetic_text' => ''],
        'last_name' => ['text' => 'B', 'phonetic_text' => ''],
    ]);

    expect(data_get($out, 'nickname.text'))->toBeNull();
    expect(data_get($out, 'nickname.phonetic_text'))->toBeNull();
});

it('includes the display format rule in extra rules', function (): void {
    $field = new Field(
        'legal_name',
        [
            'type' => 'person_name',
            'required' => true,
        ]
    );

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $rules = $fieldtype->extraRules();

    expect($rules)->toHaveKey('legal_name.display_format');
    expect($rules['legal_name.display_format'])->not->toBeEmpty();
});

it('omits the display format rule when include display format is false', function (): void {
    $field = new Field(
        'legal_name',
        [
            'type' => 'person_name',
            'required' => true,
            'include_display_format' => false,
        ]
    );

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $rules = $fieldtype->extraRules();

    expect($rules)->not->toHaveKey('legal_name.display_format');
});

it('clears the display format on process when include display format is false', function (): void {
    $field = new Field(
        'legal_name',
        [
            'type' => 'person_name',
            'include_display_format' => false,
        ]
    );

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'first_name' => ['text' => 'A', 'phonetic_text' => ''],
        'last_name' => ['text' => 'B', 'phonetic_text' => ''],
        'display_format' => 'first_last',
    ]);

    expect(data_get($out, 'display_format'))->toBeNull();
});

it('clears the nickname on process when include nickname is false', function (): void {
    $field = new Field(
        'legal_name',
        [
            'type' => 'person_name',
            'include_nickname' => false,
        ]
    );

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $out = $fieldtype->process([
        'first_name' => ['text' => 'A', 'phonetic_text' => ''],
        'last_name' => ['text' => 'B', 'phonetic_text' => ''],
        'no_nickname' => false,
        'nickname' => [
            'text' => 'Skipper',
            'phonetic_text' => 'x',
        ],
    ]);

    expect(data_get($out, 'no_nickname'))->toBeTrue();
    expect(data_get($out, 'nickname.text'))->toBeNull();
    expect(data_get($out, 'nickname.phonetic_text'))->toBeNull();
});

it('omits the nickname rules when include nickname is false', function (): void {
    $field = new Field(
        'legal_name',
        [
            'type' => 'person_name',
            'include_nickname' => false,
        ]
    );

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $rules = $fieldtype->extraRules();

    expect($rules)->not->toHaveKey('legal_name.no_nickname');
    expect($rules)->not->toHaveKey('legal_name.nickname');
    expect($rules)->not->toHaveKey('legal_name.nickname.text');
});

it('omits the nickname validation attributes when include nickname is false', function (): void {
    $field = new Field(
        'person_name',
        [
            'type' => 'person_name',
            'include_nickname' => false,
        ]
    );

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $attrs = $fieldtype->extraValidationAttributes();

    expect($attrs)->not->toHaveKey('person_name.no_nickname');
    expect($attrs)->not->toHaveKey('person_name.nickname.text');
});

it('merges defaults in pre process validatable', function (): void {
    $field = new Field(
        'legal_name',
        [
            'type' => 'person_name',
        ]
    );

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $v = $fieldtype->preProcessValidatable([]);

    expect($v)->toHaveKey('first_name');
    expect($v)->toHaveKey('display_format');
    expect($v['first_name'])->toBeArray();
});

it('includes include phonetic instructions from blueprint config in preload', function (): void {
    $field = new Field(
        'legal_name',
        [
            'type' => 'person_name',
            'include_phonetic_instructions' => false,
        ]
    );

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $pre = $fieldtype->preload();

    expect($pre)->toHaveKey('include_phonetic_instructions');
    expect(data_get($pre, 'include_phonetic_instructions'))->toBeFalse();
});

it('includes include nickname from blueprint config in preload', function (): void {
    $field = new Field(
        'legal_name',
        [
            'type' => 'person_name',
            'include_nickname' => false,
        ]
    );

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $pre = $fieldtype->preload();

    expect($pre)->toHaveKey('include_nickname');
    expect(data_get($pre, 'include_nickname'))->toBeFalse();
});

it('uses human labels for nested keys in extra validation attributes', function (): void {
    $field = new Field(
        'person_name',
        [
            'type' => 'person_name',
        ]
    );

    /** @var PersonName $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $attrs = $fieldtype->extraValidationAttributes();

    // extraValidationAttributes() keys contain literal dots; index directly rather
    // than via data_get(), which would treat the dot as nested traversal.
    // Compare with the text itself: a missing key would come back as the key on both sides
    expect($attrs['person_name.no_middle_name'] ?? null)->toBe('No Middle Name');
    expect($attrs)->toHaveKey('person_name.middle_name.text');
    expect($attrs)->toHaveKey('person_name.nickname.text');
});
