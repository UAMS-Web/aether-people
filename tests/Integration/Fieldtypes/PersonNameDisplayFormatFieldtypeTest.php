<?php

declare(strict_types=1);

use Statamic\Fields\Field;
use UAMSWeb\Aether\People\Fieldtypes\PersonNameDisplayFormat;
use UAMSWeb\Aether\People\Rules\PersonNameDisplayFormatRule;

/**
 * The `person_name_display_format` fieldtype, which keeps a person's preferred display format
 * beside `person_name` (UAMS-Web/uams-statamic#281).
 *
 * @command  vendor/bin/pest tests/Integration/Fieldtypes/PersonNameDisplayFormatFieldtypeTest.php
 */

/**
 * A `person_name_display_format` fieldtype bound to a field with the given settings.
 *
 * @param  array<string, mixed>  $config  The field settings, beside `type`
 */
function displayFormatFieldtype(array $config = []): PersonNameDisplayFormat
{
    $field = new Field('name_display_format', ['type' => 'person_name_display_format', ...$config]);
    $fieldtype = mustBe(PersonNameDisplayFormat::class, $field->fieldtype(), 'person_name_display_format fieldtype');
    $fieldtype->setField($field);

    return $fieldtype;
}

it('is offered in no blueprint, with its component and icon', function (): void {
    $fieldtype = new PersonNameDisplayFormat;

    expect($fieldtype->selectable())->toBeFalse()
        ->and($fieldtype->selectableInForms())->toBeFalse()
        ->and($fieldtype->component())->toBe('person_name_display_format')
        ->and($fieldtype->icon())->toBe('fieldtype-users');
});

it('offers whether a format is required in one section', function (): void {
    $fieldtype = new PersonNameDisplayFormat;

    expect(new ReflectionMethod($fieldtype, 'configFieldItems')->invoke($fieldtype))->toBe([
        [
            'display' => 'Display format options',
            'fields' => [
                'require_when_available' => [
                    'type' => 'toggle',
                    'display' => 'Require a format once the name allows one',
                    'instructions' => 'When enabled, a format must be chosen once every name part it depends on is filled.',
                    'default' => true,
                ],
            ],
        ],
    ]);
});

it('checks the format against the name joined from the fields beside it', function (array $config, bool $required): void {
    expect(displayFormatFieldtype(mustBeStringKeyed($config, 'config'))->rules())->toEqual([new PersonNameDisplayFormatRule('person_name', $required, true)]);
})->with([
    'required by default' => [[], true],
    'required' => [['require_when_available' => true], true],
    'optional' => [['require_when_available' => false], false],
    'optional, as a stored zero' => [['require_when_available' => 0], false],
]);

it('requires a format when it has no field', function (): void {
    expect(new PersonNameDisplayFormat()->rules())->toEqual([new PersonNameDisplayFormatRule('person_name', true, true)]);
});

it('stores a chosen key, or nothing', function (mixed $submitted, ?string $stored): void {
    expect(displayFormatFieldtype()->process($submitted))->toBe($stored);
})->with([
    'a key' => ['full_first_only@ns', 'full_first_only@ns'],
    'a key with spaces kept' => [' full_first_only@ns ', ' full_first_only@ns '],
    'an empty string' => ['', null],
    'only spaces' => ['   ', null],
    'nothing' => [null, null],
    'a number' => [5, null],
]);
