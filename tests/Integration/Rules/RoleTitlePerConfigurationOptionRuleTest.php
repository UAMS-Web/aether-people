<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Translation\PotentiallyTranslatedString;
use UAMSWeb\Aether\People\Rules\RoleTitlePerConfigurationOptionRule;
use UAMSWeb\Aether\People\Support\RoleTitleDefinition;
use UAMSWeb\Aether\People\Support\RoleTitleValue;

covers(RoleTitlePerConfigurationOptionRule::class);

/**
 * Behavior of `RoleTitlePerConfigurationOptionRule`: a role_title radios
 * subfield value must be one of the option keys its parent's `configuration`
 * allows. Covers a missing parent, unstructured mode, the optional "none"
 * sentinel, empty values for a required or optional parent, a non-string value,
 * an unknown configuration, and keys allowed in one configuration but not another.
 *
 * @command  php artisan test --compact tests/Integration/Rules/RoleTitlePerConfigurationOptionRuleTest.php
 */

/**
 * Run the rule against a data bag and return every message it failed with.
 *
 * @param  array<string, mixed>  $data
 * @return list<string>
 */
function roleTitleOptionRuleMessages(
    array $data,
    mixed $value,
    bool $required = false,
    string $subfield = 'role',
    string $parentPath = 'title'
): array {
    $rule = new RoleTitlePerConfigurationOptionRule($parentPath, $required, $subfield)->setData($data);
    $messages = [];

    $rule->validate(
        $parentPath.'.'.$subfield,
        $value,
        function (string $message, ?string $second = null) use (&$messages): PotentiallyTranslatedString {
            $messages[] = $message;

            return new PotentiallyTranslatedString($message, app(Translator::class));
        }
    );

    return $messages;
}

it('returns itself from setData for chaining', function (): void {
    $rule = new RoleTitlePerConfigurationOptionRule('title', false, 'role');

    expect($rule->setData([]))->toBe($rule);
});

it('accepts a key the configuration allows', function (string $configuration, string $subfield, string $value): void {
    $data = ['title' => ['configuration' => $configuration]];

    expect(roleTitleOptionRuleMessages($data, $value, true, $subfield))->toBe([])
        ->and(roleTitleOptionRuleMessages($data, $value, false, $subfield))->toBe([]);
})->with([
    'faculty role' => [RoleTitleValue::CONFIGURATION_FACULTY, 'role', 'professor'],
    'academic leadership role' => [RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP, 'role', 'chair'],
    'institutional leadership role' => [RoleTitleValue::CONFIGURATION_INSTITUTIONAL_LEADERSHIP, 'role', 'dean'],
    'clinical leadership role' => [RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP, 'role', 'medical_director'],
    'academic leadership object' => [RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP, 'object', 'department'],
    'faculty honorific' => [RoleTitleValue::CONFIGURATION_FACULTY, 'honorific', 'emeritus'],
]);

it('rejects a key the configuration does not allow', function (string $configuration, string $subfield, string $value): void {
    $data = ['title' => ['configuration' => $configuration]];

    expect(roleTitleOptionRuleMessages($data, $value, false, $subfield))->toBe([__('validation.in')])
        ->and(roleTitleOptionRuleMessages($data, $value, true, $subfield))->toBe([__('validation.in')]);
})->with([
    'a leadership role under faculty' => [RoleTitleValue::CONFIGURATION_FACULTY, 'role', 'dean'],
    'a faculty role under clinical leadership' => [RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP, 'role', 'professor'],
    'an unknown key' => [RoleTitleValue::CONFIGURATION_FACULTY, 'role', 'wizard'],
    'a key that differs only in case' => [RoleTitleValue::CONFIGURATION_FACULTY, 'role', 'Professor'],
    'an object under a configuration with no objects' => [RoleTitleValue::CONFIGURATION_FACULTY, 'object', 'department'],
    'a key under an unknown configuration' => ['astronaut', 'role', 'professor'],
]);

it('rejects any value when the configuration is missing or not a string', function (mixed $configuration): void {
    $data = ['title' => ['configuration' => $configuration]];

    expect(roleTitleOptionRuleMessages($data, 'professor'))->toBe([__('validation.in')]);
})->with([
    'null' => [null],
    'empty string' => [''],
    'array' => [[RoleTitleValue::CONFIGURATION_FACULTY]],
    'integer' => [1],
]);

it('rejects a value that is not a string', function (mixed $value): void {
    $data = ['title' => ['configuration' => RoleTitleValue::CONFIGURATION_FACULTY]];

    expect(roleTitleOptionRuleMessages($data, $value))->toBe([__('validation.string')])
        ->and(roleTitleOptionRuleMessages($data, $value, true))->toBe([__('validation.string')]);
})->with([
    'integer' => [5],
    'array' => [['professor']],
    'boolean' => [true],
]);

it('requires a value when the parent is required', function (mixed $value): void {
    $data = ['title' => ['configuration' => RoleTitleValue::CONFIGURATION_FACULTY]];

    expect(roleTitleOptionRuleMessages($data, $value, true))->toBe([__('validation.required')]);
})->with([
    'null' => [null],
    'empty string' => [''],
]);

it('allows an empty value when the parent is optional', function (mixed $value): void {
    $data = ['title' => ['configuration' => RoleTitleValue::CONFIGURATION_FACULTY]];

    expect(roleTitleOptionRuleMessages($data, $value))->toBe([]);
})->with([
    'null' => [null],
    'empty string' => [''],
]);

it('treats the optional none sentinel as an empty value', function (): void {
    $data = ['title' => ['configuration' => RoleTitleValue::CONFIGURATION_FACULTY]];
    $none = RoleTitleValue::optionalSelectionNone();

    expect(roleTitleOptionRuleMessages($data, $none, true))->toBe([__('validation.required')])
        ->and(roleTitleOptionRuleMessages($data, $none, false))->toBe([])
        ->and(roleTitleOptionRuleMessages($data, $none.'x', false))->toBe([__('validation.in')]);
});

it('requires the parent payload only when the parent is required', function (bool $present, mixed $parent): void {
    $data = $present ? ['title' => $parent] : [];

    expect(roleTitleOptionRuleMessages($data, 'professor', true))->toBe([__('validation.required')])
        ->and(roleTitleOptionRuleMessages($data, 'professor', false))->toBe([]);
})->with([
    'no parent' => [false, null],
    'null parent' => [true, null],
    'string parent' => [true, 'Professor'],
]);

it('skips the check in unstructured mode', function (bool $required, mixed $value): void {
    $data = ['title' => [
        'mode' => RoleTitleValue::MODE_UNSTRUCTURED,
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
    ]];

    expect(roleTitleOptionRuleMessages($data, $value, $required))->toBe([]);
})->with([
    'required, empty' => [true, null],
    'required, disallowed key' => [true, 'dean'],
    'optional, non-string' => [false, 5],
]);

it('still checks the value in structured mode', function (): void {
    $data = ['title' => [
        'mode' => RoleTitleValue::MODE_STRUCTURED,
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
    ]];

    expect(roleTitleOptionRuleMessages($data, 'dean'))->toBe([__('validation.in')])
        ->and(roleTitleOptionRuleMessages($data, 'professor'))->toBe([]);
});

it('reads the parent through a dotted path', function (): void {
    $data = ['rows' => [['title' => ['configuration' => RoleTitleValue::CONFIGURATION_FACULTY]]]];

    expect(roleTitleOptionRuleMessages($data, 'professor', true, 'role', 'rows.0.title'))->toBe([])
        ->and(roleTitleOptionRuleMessages($data, 'dean', true, 'role', 'rows.0.title'))->toBe([__('validation.in')])
        ->and(roleTitleOptionRuleMessages($data, 'professor', true, 'role', 'rows.1.title'))->toBe([__('validation.required')]);
});

it('matches an option key exactly rather than by numeric value', function (): void {
    // A zero-padded key such as `01` equals `1` and `1.0` under a loose comparison,
    // so only an exact match may select it.
    config()->set('role_title.subfields.role.options.01', [
        'available_in' => [RoleTitleValue::CONFIGURATION_FACULTY],
    ]);
    RoleTitleDefinition::forgetInstance();

    try {
        $data = ['title' => ['configuration' => RoleTitleValue::CONFIGURATION_FACULTY]];

        expect(roleTitleOptionRuleMessages($data, '01'))->toBe([])
            ->and(roleTitleOptionRuleMessages($data, '1'))->toBe([__('validation.in')])
            ->and(roleTitleOptionRuleMessages($data, '1.0'))->toBe([__('validation.in')]);
    } finally {
        RoleTitleDefinition::forgetInstance();
    }
});
