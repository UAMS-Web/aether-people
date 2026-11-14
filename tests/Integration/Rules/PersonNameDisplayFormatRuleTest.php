<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;
use UAMSWeb\Aether\People\Rules\PersonNameDisplayFormatRule;

/**
 * The `name_display_format` field's rule (UAMS-Web/uams-statamic-archive#3186): the format kept beside
 * `person_name` must be one the name allows once it is joined with the
 * nickname and pronunciation fields beside it.
 *
 * @command  vendor/bin/pest tests/Integration/Rules/PersonNameDisplayFormatRuleTest.php
 */

/**
 * The legal name as `person_name` holds it after UAMS-Web/uams-statamic-archive#3186.
 *
 * @return array<string, mixed>
 */
function displayFormatRuleLegalName(): array
{
    return [
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => null],
        'no_middle_name' => true,
        'middle_name' => ['text' => null, 'phonetic_text' => null],
        'no_nickname' => true,
        'nickname' => ['text' => null, 'phonetic_text' => null],
        'last_name' => ['text' => 'McCoy', 'phonetic_text' => null],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => null,
    ];
}

/**
 * Whether the form data passes the `name_display_format` rule.
 *
 * @param  array<string, mixed>  $data
 */
function displayFormatRulePasses(array $data, bool $required = true): bool
{
    return Validator::make($data, [
        'name_display_format' => [new PersonNameDisplayFormatRule('person_name', $required, true)],
    ])->passes();
}

it('accepts a nickname format when the nickname field holds a nickname', function (): void {
    expect(displayFormatRulePasses([
        'person_name' => displayFormatRuleLegalName(),
        'nickname' => ['text' => 'Bones', 'phonetic_text' => null],
        'name_display_format' => 'nickname_only@ns',
    ]))->toBeTrue();
});

it('refuses a nickname format when the nickname field is empty', function (): void {
    expect(displayFormatRulePasses([
        'person_name' => displayFormatRuleLegalName(),
        'nickname' => ['text' => '', 'phonetic_text' => null],
        'name_display_format' => 'nickname_only@ns',
    ]))->toBeFalse();
});

it('accepts a format the legal name alone allows', function (): void {
    expect(displayFormatRulePasses([
        'person_name' => displayFormatRuleLegalName(),
        'name_display_format' => 'full_first_only@ns',
    ]))->toBeTrue();
});

it('requires a format once the name allows one, unless the field is optional', function (): void {
    $data = ['person_name' => displayFormatRuleLegalName(), 'name_display_format' => null];

    expect(displayFormatRulePasses($data))->toBeFalse()
        ->and(displayFormatRulePasses($data, required: false))->toBeTrue();
});

it('refuses a key no name allows', function (): void {
    expect(displayFormatRulePasses([
        'person_name' => displayFormatRuleLegalName(),
        'name_display_format' => 'not_a_format@ns',
    ]))->toBeFalse();
});

// The validator skips an empty string for a rule that is not implicit, so this
// reaches the rule's own empty-string branch by calling it directly.
it('treats an empty string as no format chosen', function (bool $required, ?string $expectedKey): void {
    $messages = [];

    new PersonNameDisplayFormatRule('person_name', $required, true)
        ->setData(['person_name' => displayFormatRuleLegalName()])
        ->validate('name_display_format', '', function (string $message) use (&$messages): PotentiallyTranslatedString {
            $messages[] = $message;

            return new PotentiallyTranslatedString($message, mustBe(Translator::class, app('translator'), 'translator'));
        });

    expect($messages)->toBe($expectedKey === null ? [] : [__($expectedKey)]);
})->with([
    'required' => [true, 'validation.required'],
    'optional' => [false, null],
]);

/**
 * The messages the rule fails with for a value, called directly so an empty value reaches it.
 *
 * @param  array<string, mixed>  $data
 * @return list<string>
 */
function displayFormatRuleMessages(PersonNameDisplayFormatRule $rule, array $data, mixed $value): array
{
    $messages = [];

    $rule->setData($data)->validate('display_format', $value, function (string $message) use (&$messages): PotentiallyTranslatedString {
        $messages[] = $message;

        return new PotentiallyTranslatedString($message, mustBe(Translator::class, app('translator'), 'translator'));
    });

    return $messages;
}

it('reads the name from its own field when it does not join the fields beside it', function (): void {
    $rule = new PersonNameDisplayFormatRule('legal_name', true);

    expect(displayFormatRuleMessages($rule, ['legal_name' => displayFormatRuleLegalName()], 'full_first_only@ns'))->toBe([])
        ->and(displayFormatRuleMessages($rule, ['legal_name' => displayFormatRuleLegalName()], 'not_a_format@ns'))->toBe([__('validation.in')]);
});

it('requires a name to fit only when the field is required', function (bool $required, array $expected): void {
    expect(displayFormatRuleMessages(new PersonNameDisplayFormatRule('legal_name', $required), ['legal_name' => 'Leonard McCoy'], 'full_first_only@ns'))
        ->toBe(array_map(fn (mixed $key): mixed => __(mustBeString($key, 'message key')), $expected));
})->with([
    'required' => [true, ['validation.required']],
    'optional' => [false, []],
]);

it('accepts any value while the name allows no format', function (): void {
    expect(displayFormatRuleMessages(new PersonNameDisplayFormatRule('legal_name', true), ['legal_name' => []], 'not_a_format@ns'))->toBe([]);
});

it('refuses a format that is not a string', function (): void {
    expect(displayFormatRuleMessages(new PersonNameDisplayFormatRule('legal_name', true), ['legal_name' => displayFormatRuleLegalName()], 5))
        ->toBe([__('validation.string')]);
});
