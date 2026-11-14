<?php

declare(strict_types=1);

use Illuminate\Validation\Rules\In;
use Statamic\Fields\Field;
use UAMSWeb\Aether\People\Fieldtypes\PersonName;
use UAMSWeb\Aether\People\Rules\PersonNameDisplayFormatRule;
use UAMSWeb\Aether\People\Support\PersonNameDisplayFormats;

/**
 * The `person_name` fieldtype's contract with Statamic, pinned output by output: the blueprint
 * settings it offers, what it preloads and renders, the rules and labels it validates with, and
 * the value it stores (UAMS-Web/uams-statamic#281).
 *
 * The rules `name` and `international_phonetic_alphabet` belong to the host site; these tests read
 * the rule lists and never run them.
 *
 * @command  vendor/bin/pest tests/Integration/Fieldtypes/PersonNameContractTest.php
 */

/**
 * A `person_name` fieldtype bound to a field with the given settings.
 *
 * @param  array<string, mixed>  $config  The field settings, beside `type`
 */
function personNameContractFieldtype(array $config = [], ?string $handle = 'legal_name'): PersonName
{
    $field = new Field($handle, ['type' => 'person_name', ...$config]);
    $fieldtype = mustBe(PersonName::class, $field->fieldtype(), 'person_name fieldtype');
    $fieldtype->setField($field);

    return $fieldtype;
}

/**
 * The stored value of a name with no parts filled.
 *
 * @return array<string, mixed>
 */
function personNameContractEmpty(): array
{
    return [
        'first_name' => ['text' => '', 'phonetic_text' => ''],
        'no_middle_name' => false,
        'middle_name' => ['text' => '', 'phonetic_text' => ''],
        'no_nickname' => false,
        'nickname' => ['text' => '', 'phonetic_text' => ''],
        'last_name' => ['text' => '', 'phonetic_text' => ''],
        'no_generational_suffix' => false,
        'generational_suffix' => null,
        'display_format' => null,
    ];
}

/**
 * The rules with the suffix `in` rule written out, so they compare as plain values.
 *
 * @param  array<string, mixed>  $rules
 * @return array<string, mixed>
 */
function personNameContractRules(array $rules): array
{
    return array_map(
        fn (mixed $list): mixed => is_array($list)
            ? array_map(fn (mixed $rule): mixed => $rule instanceof In ? (string) $rule : $rule, $list)
            : $list,
        $rules,
    );
}

/**
 * The rules for a field that requires nothing.
 *
 * @return array<string, mixed>
 */
function personNameContractOptionalRules(): array
{
    return [
        'legal_name.no_middle_name' => ['boolean'],
        'legal_name.no_generational_suffix' => ['boolean'],
        'legal_name.first_name' => ['array'],
        'legal_name.first_name.text' => ['nullable', 'string', 'name'],
        'legal_name.first_name.phonetic_text' => ['nullable', 'string', 'international_phonetic_alphabet'],
        'legal_name.middle_name' => ['array'],
        'legal_name.middle_name.text' => ['nullable', 'string', 'name'],
        'legal_name.middle_name.phonetic_text' => ['nullable', 'string', 'international_phonetic_alphabet'],
        'legal_name.no_nickname' => ['boolean'],
        'legal_name.nickname' => ['array'],
        'legal_name.nickname.text' => ['nullable', 'string', 'name'],
        'legal_name.nickname.phonetic_text' => ['nullable', 'string', 'international_phonetic_alphabet'],
        'legal_name.last_name' => ['array'],
        'legal_name.last_name.text' => ['nullable', 'string', 'name'],
        'legal_name.last_name.phonetic_text' => ['nullable', 'string', 'international_phonetic_alphabet'],
        'legal_name.generational_suffix' => ['nullable', 'string', 'in:"Jr.","Sr.","II","III","IV","V","VI","VII","VIII","X"'],
        'legal_name.display_format' => [new PersonNameDisplayFormatRule('legal_name', false)],
    ];
}

/**
 * The rules for a field whose text is required.
 *
 * @return array<string, mixed>
 */
function personNameContractTextRequiredRules(bool $displayFormatRequired): array
{
    return [
        ...personNameContractOptionalRules(),
        'legal_name.first_name.text' => ['required', 'nullable', 'string', 'name'],
        'legal_name.middle_name.text' => ['required_if:legal_name.no_middle_name,false', 'nullable', 'string', 'name'],
        'legal_name.nickname.text' => ['required_if:legal_name.no_nickname,false', 'nullable', 'string', 'name'],
        'legal_name.last_name.text' => ['required', 'nullable', 'string', 'name'],
        'legal_name.generational_suffix' => ['nullable', 'string', 'required_if:legal_name.no_generational_suffix,false', 'in:"Jr.","Sr.","II","III","IV","V","VI","VII","VIII","X"'],
        'legal_name.display_format' => [new PersonNameDisplayFormatRule('legal_name', $displayFormatRequired)],
    ];
}

it('is offered in collection and form blueprints, with its component and icon', function (): void {
    $fieldtype = new PersonName;

    expect($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeTrue()
        ->and($fieldtype->component())->toBe('person_name')
        ->and($fieldtype->icon())->toBe('fieldtype-users');
});

it('offers its blueprint settings in one section', function (): void {
    $fieldtype = new PersonName;

    expect(new ReflectionMethod($fieldtype, 'configFieldItems')->invoke($fieldtype))->toBe([
        [
            'display' => 'Person name options',
            'fields' => [
                'require_text' => [
                    'type' => 'toggle',
                    'display' => 'Require text for each visible name part',
                    'instructions' => 'When enabled, each visible text field must be filled.',
                    'default' => true,
                ],
                'require_phonetic' => [
                    'type' => 'toggle',
                    'display' => 'Require phonetic text for each visible name part',
                    'instructions' => 'When enabled, each visible phonetic text field must be filled.',
                    'default' => false,
                ],
                'include_phonetic_instructions' => [
                    'type' => 'toggle',
                    'display' => 'Include phonetic text instructions',
                    'instructions' => 'When enabled, the IPA help alert is shown at the top of the field. When disabled, that alert is hidden.',
                    'default' => true,
                ],
                'include_display_format' => [
                    'type' => 'toggle',
                    'display' => 'Include preferred display format',
                    'instructions' => 'When enabled, editors choose how the name is shown when all parts are available. When disabled, that control is hidden and stored display format is cleared on save.',
                    'default' => true,
                ],
                'include_nickname' => [
                    'type' => 'toggle',
                    'display' => 'Include nickname',
                    'instructions' => 'When enabled, editors can enter a nickname and related phonetic text. When disabled, those controls are hidden and nickname data is cleared on save.',
                    'default' => true,
                ],
                'include_phonetic' => [
                    'type' => 'toggle',
                    'display' => 'Include phonetic text',
                    'instructions' => 'When enabled, each name part has a phonetic text input beside it. When disabled, those inputs are hidden and phonetic text is cleared on save.',
                    'default' => true,
                ],
            ],
        ],
    ]);
});

it('preloads its settings and the generational suffix options', function (): void {
    expect(personNameContractFieldtype(['include_nickname' => false])->preload())->toBe([
        'require_text' => true,
        'require_phonetic' => false,
        'include_phonetic_instructions' => true,
        'include_display_format' => true,
        'include_phonetic' => true,
        'type' => 'person_name',
        'include_nickname' => false,
        'generational_suffix_options' => PersonNameDisplayFormats::generationalSuffixSelectOptions(),
    ]);
});

it('gives a public form the suffix options and the formats the starting value allows', function (): void {
    $value = [
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => ''],
        'no_middle_name' => true,
        'no_nickname' => true,
        'last_name' => ['text' => 'McCoy', 'phonetic_text' => ''],
        'no_generational_suffix' => true,
    ];
    $fieldtype = personNameContractFieldtype();
    $fieldtype->field()?->setValue($value);

    $options = PersonNameDisplayFormats::buildOptions(PersonNameDisplayFormats::normalizeValue($value));

    expect($options)->not->toBe([])
        ->and($fieldtype->extraRenderableFieldData())->toBe([
            'generational_suffix_options' => PersonNameDisplayFormats::generationalSuffixSelectOptions(),
            'display_format_options' => $options,
        ]);
});

it('gives a public form no formats for a value that is not a name, or with no field', function (): void {
    $fieldtype = personNameContractFieldtype();
    $fieldtype->field()?->setValue('Leonard McCoy');

    $expected = [
        'generational_suffix_options' => PersonNameDisplayFormats::generationalSuffixSelectOptions(),
        'display_format_options' => [],
    ];

    expect($fieldtype->extraRenderableFieldData())->toBe($expected)
        ->and(new PersonName()->extraRenderableFieldData())->toBe($expected);
});

it('validates the value as an array', function (): void {
    expect(new PersonName()->rules())->toBe(['array']);
});

it('validates nothing nested and labels nothing without a field', function (): void {
    $fieldtype = new PersonName;

    expect($fieldtype->extraRules())->toBe([])
        ->and($fieldtype->extraValidationAttributes())->toBe([]);
});

it('labels every nested key it validates', function (): void {
    expect(personNameContractFieldtype()->extraValidationAttributes())->toBe([
        'legal_name.no_middle_name' => 'No Middle Name',
        'legal_name.no_nickname' => 'No Nickname',
        'legal_name.no_generational_suffix' => 'No Generational Suffix',
        'legal_name.first_name.text' => 'Full First Name (Text)',
        'legal_name.first_name.phonetic_text' => 'Full First Name (Phonetic Text)',
        'legal_name.middle_name.text' => 'Full Middle Name (Text)',
        'legal_name.middle_name.phonetic_text' => 'Full Middle Name (Phonetic Text)',
        'legal_name.nickname.text' => 'Nickname (Text)',
        'legal_name.nickname.phonetic_text' => 'Nickname (Phonetic Text)',
        'legal_name.last_name.text' => 'Full Last Name (Text)',
        'legal_name.last_name.phonetic_text' => 'Full Last Name (Phonetic Text)',
        'legal_name.generational_suffix' => 'Generational Suffix',
        'legal_name.display_format' => 'Preferred Display Format',
    ]);
});

it('labels no nickname or display format key when the blueprint hides them', function (): void {
    expect(array_keys(personNameContractFieldtype(['include_nickname' => false, 'include_display_format' => false])->extraValidationAttributes()))->toBe([
        'legal_name.no_middle_name',
        'legal_name.no_generational_suffix',
        'legal_name.first_name.text',
        'legal_name.first_name.phonetic_text',
        'legal_name.middle_name.text',
        'legal_name.middle_name.phonetic_text',
        'legal_name.last_name.text',
        'legal_name.last_name.phonetic_text',
        'legal_name.generational_suffix',
    ]);
});

it('roots the keys at nothing for a field with no handle', function (): void {
    expect(array_keys(personNameContractFieldtype(handle: null)->extraValidationAttributes()))->toContain('.no_middle_name')
        ->and(array_keys(personNameContractFieldtype(handle: null)->extraRules()))->toContain('.first_name.text');
});

it('requires nothing nested for a field that requires nothing', function (): void {
    expect(personNameContractRules(personNameContractFieldtype()->extraRules()))->toEqual(personNameContractOptionalRules());
});

it('requires each visible text part for a required field', function (array $config): void {
    expect(personNameContractRules(personNameContractFieldtype(mustBeStringKeyed($config, 'config'))->extraRules()))->toEqual(personNameContractTextRequiredRules(true));
})->with([
    'required' => [['required' => true]],
    'validate: required' => [['validate' => ['max:5', 'required']]],
]);

it('reads only a validate list naming required as required', function (): void {
    expect(personNameContractRules(personNameContractFieldtype(['validate' => 'required'])->extraRules()))->toEqual(personNameContractOptionalRules());
});

it('requires each visible text part, but not a display format, when only the text is required', function (): void {
    expect(personNameContractRules(personNameContractFieldtype(['require_text' => true])->extraRules()))->toEqual(personNameContractTextRequiredRules(false));
});

it('requires each visible phonetic part when the blueprint asks for it', function (): void {
    expect(personNameContractRules(personNameContractFieldtype(['require_phonetic' => true])->extraRules()))->toEqual([
        ...personNameContractOptionalRules(),
        'legal_name.first_name.phonetic_text' => ['required', 'nullable', 'string', 'international_phonetic_alphabet'],
        'legal_name.middle_name.phonetic_text' => ['required_if:legal_name.no_middle_name,false', 'nullable', 'string', 'international_phonetic_alphabet'],
        'legal_name.nickname.phonetic_text' => ['required_if:legal_name.no_nickname,false', 'nullable', 'string', 'international_phonetic_alphabet'],
        'legal_name.last_name.phonetic_text' => ['required', 'nullable', 'string', 'international_phonetic_alphabet'],
    ]);
});

it('requires no phonetic part when the blueprint hides them', function (): void {
    expect(personNameContractRules(personNameContractFieldtype(['require_phonetic' => true, 'include_phonetic' => false])->extraRules()))->toEqual(personNameContractOptionalRules());
});

it('validates no nickname or display format when the blueprint hides them', function (): void {
    $rules = personNameContractOptionalRules();
    unset($rules['legal_name.no_nickname'], $rules['legal_name.nickname'], $rules['legal_name.nickname.text'], $rules['legal_name.nickname.phonetic_text'], $rules['legal_name.display_format']);

    expect(personNameContractRules(personNameContractFieldtype(['include_nickname' => false, 'include_display_format' => false])->extraRules()))->toEqual($rules);
});

it('starts a new entry with an empty name', function (): void {
    expect(personNameContractFieldtype()->defaultValue())->toBe(personNameContractEmpty());
});

it('reads a stored value that is not an array as an empty name', function (string $method): void {
    expect(personNameContractFieldtype()->{$method}('Leonard McCoy'))->toBe(personNameContractEmpty());
})->with(['preProcess', 'process', 'preProcessValidatable']);

it('fills a partial value out to the full shape for validation', function (): void {
    expect(personNameContractFieldtype()->preProcessValidatable(['first_name' => ['text' => 'Leonard'], 'display_format' => 'full_first_only@ns']))->toBe([
        ...personNameContractEmpty(),
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => ''],
        'display_format' => 'full_first_only@ns',
    ]);
});

it('normalizes a stored value for the publish form', function (): void {
    expect(personNameContractFieldtype()->preProcess([
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => 7],
        'middle_name' => ['text' => ['H.'], 'phonetic_text' => 'eɪtʃ'],
        'no_nickname' => 1,
        'nickname' => ['text' => 'Bones', 'phonetic_text' => 'boʊnz'],
        'last_name' => 'McCoy',
        'generational_suffix' => '  Jr.  ',
        'display_format' => 12,
    ]))->toBe([
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => ''],
        'no_middle_name' => false,
        'middle_name' => ['text' => '', 'phonetic_text' => 'eɪtʃ'],
        'no_nickname' => true,
        'nickname' => ['text' => null, 'phonetic_text' => null],
        'last_name' => ['text' => '', 'phonetic_text' => ''],
        'no_generational_suffix' => false,
        'generational_suffix' => 'Jr.',
        'display_format' => null,
    ]);
});

it('stores a blank suffix as none and keeps a chosen display format', function (): void {
    $out = personNameContractFieldtype()->process(['generational_suffix' => '   ', 'display_format' => 'full_first_only@ns']);

    expect($out['generational_suffix'])->toBeNull()
        ->and($out['display_format'])->toBe('full_first_only@ns');
});

it('clears the suffix when the name has none', function (): void {
    expect(personNameContractFieldtype()->process(['no_generational_suffix' => true, 'generational_suffix' => 'Jr.'])['generational_suffix'])->toBeNull();
});

it('clears every phonetic part on save when the blueprint hides them', function (): void {
    $out = personNameContractFieldtype(['include_phonetic' => false])->process([
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => 'ˈlɛnərd'],
        'middle_name' => ['text' => 'H', 'phonetic_text' => 'eɪtʃ'],
        'nickname' => ['text' => 'Bones', 'phonetic_text' => 'boʊnz'],
        'last_name' => ['text' => 'McCoy', 'phonetic_text' => 'məˈkɔɪ'],
    ]);

    expect([$out['first_name'], $out['middle_name'], $out['nickname'], $out['last_name']])->toBe([
        ['text' => 'Leonard', 'phonetic_text' => null],
        ['text' => 'H', 'phonetic_text' => null],
        ['text' => 'Bones', 'phonetic_text' => null],
        ['text' => 'McCoy', 'phonetic_text' => null],
    ]);
});

it('keeps every phonetic part on save by default', function (): void {
    expect(personNameContractFieldtype()->process(['first_name' => ['text' => 'Leonard', 'phonetic_text' => 'ˈlɛnərd']])['first_name'])
        ->toBe(['text' => 'Leonard', 'phonetic_text' => 'ˈlɛnərd']);
});

it('stores a whole name without changing it', function (): void {
    $value = [
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => 'ˈlɛnərd'],
        'no_middle_name' => false,
        'middle_name' => ['text' => 'H', 'phonetic_text' => 'eɪtʃ'],
        'no_nickname' => false,
        'nickname' => ['text' => 'Bones', 'phonetic_text' => 'boʊnz'],
        'last_name' => ['text' => 'McCoy', 'phonetic_text' => 'məˈkɔɪ'],
        'no_generational_suffix' => false,
        'generational_suffix' => 'Jr.',
        'display_format' => 'full_first_only@ns',
    ];

    expect(personNameContractFieldtype()->process($value))->toBe($value)
        ->and(personNameContractFieldtype()->preProcess($value))->toBe($value);
});

it('roots the display format rule at a handle that is not a string', function (): void {
    expect(personNameContractFieldtype(['required' => true], handle: null)->extraRules()['.display_format'] ?? null)
        ->toEqual([new PersonNameDisplayFormatRule('', true)]);

    $field = new Field(5, ['type' => 'person_name', 'required' => true]);
    $fieldtype = mustBe(PersonName::class, $field->fieldtype(), 'person_name fieldtype');
    $fieldtype->setField($field);

    expect($fieldtype->extraRules()['5.display_format'] ?? null)->toEqual([new PersonNameDisplayFormatRule('5', true)]);
});

it('reads a validate list strictly for the word required', function (): void {
    expect(personNameContractRules(personNameContractFieldtype(['validate' => [true]])->extraRules()))->toEqual(personNameContractOptionalRules());
});

it('normalizes and stores a value with no field, keeping every part', function (): void {
    $value = [
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => 'ˈlɛnərd'],
        'nickname' => ['text' => 'Bones', 'phonetic_text' => 'boʊnz'],
        'display_format' => 'full_first_only@ns',
    ];
    $expected = [
        ...personNameContractEmpty(),
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => 'ˈlɛnərd'],
        'nickname' => ['text' => 'Bones', 'phonetic_text' => 'boʊnz'],
        'display_format' => 'full_first_only@ns',
    ];

    expect(new PersonName()->process($value))->toBe($expected)
        ->and(new PersonName()->preProcess($value))->toBe($expected);
});

it('reads a missing or falsy flag as not set', function (mixed $flag): void {
    $out = personNameContractFieldtype()->process([
        'no_middle_name' => $flag,
        'middle_name' => ['text' => 'H', 'phonetic_text' => ''],
        'no_nickname' => $flag,
        'nickname' => ['text' => 'Bones', 'phonetic_text' => ''],
        'no_generational_suffix' => $flag,
        'generational_suffix' => 'Jr.',
    ]);

    expect([$out['no_middle_name'], $out['middle_name'], $out['no_nickname'], $out['nickname'], $out['no_generational_suffix'], $out['generational_suffix']])->toBe([
        false, ['text' => 'H', 'phonetic_text' => ''],
        false, ['text' => 'Bones', 'phonetic_text' => ''],
        false, 'Jr.',
    ]);
})->with([
    'null' => [null],
    'zero' => [0],
]);

it('reads a truthy flag as set and clears what it hides', function (): void {
    $out = personNameContractFieldtype()->process([
        'no_middle_name' => 'yes',
        'middle_name' => ['text' => 'H', 'phonetic_text' => 'eɪtʃ'],
        'no_nickname' => 1,
        'nickname' => ['text' => 'Bones', 'phonetic_text' => 'boʊnz'],
        'no_generational_suffix' => 1,
        'generational_suffix' => 'Jr.',
    ]);

    expect([$out['no_middle_name'], $out['middle_name'], $out['no_nickname'], $out['nickname'], $out['no_generational_suffix'], $out['generational_suffix']])->toBe([
        true, ['text' => null, 'phonetic_text' => null],
        true, ['text' => null, 'phonetic_text' => null],
        true, null,
    ]);
});

it('stores no nickname, exactly, when the blueprint hides it', function (string $method): void {
    $out = mustBeStringKeyed(personNameContractFieldtype(['include_nickname' => false])->{$method}([
        'no_nickname' => false,
        'nickname' => ['text' => 'Bones', 'phonetic_text' => 'boʊnz'],
    ]), 'stored value');

    expect([$out['no_nickname'], $out['nickname']])->toBe([true, ['text' => null, 'phonetic_text' => null]]);
})->with(['preProcess', 'process']);
