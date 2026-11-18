<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Translation\PotentiallyTranslatedString;
use UAMSWeb\Aether\People\Rules\Isco08FieldtypeStoredRule;

covers(Isco08FieldtypeStoredRule::class);

/**
 * Behavior of the stored `isco_08` rule (UAMSWeb\Aether\People\Rules\Isco08FieldtypeStoredRule): a
 * value that is not an array is refused, and a field whose chain has no valid
 * major is refused only when the field is required. A valid major with invalid
 * tiers beneath it passes, because the chain is normalized first and drops them.
 *
 * @command  php artisan test --compact tests/Integration/Rules/Isco08FieldtypeStoredRuleTest.php
 */

/**
 * Run the rule and return every message it failed with.
 *
 * @return list<string>
 */
function isco08StoredMessages(bool $required, mixed $value): array
{
    $messages = [];

    new Isco08FieldtypeStoredRule($required)->validate('isco', $value, function (string $message) use (&$messages): PotentiallyTranslatedString {
        $messages[] = $message;

        return new PotentiallyTranslatedString($message, mustBe(Translator::class, app('translator'), 'translator'));
    });

    return $messages;
}

it('refuses a value that is not an array, required or not', function (bool $required, mixed $value): void {
    expect(isco08StoredMessages($required, $value))->toBe([__('aether-people::fieldtypes.isco_08.validation.not_array')]);
})->with([
    'required string' => [true, '2'],
    'optional string' => [false, '2'],
    'optional null' => [false, null],
]);

it('requires a valid major only when the field is required', function (array $value): void {
    expect(isco08StoredMessages(true, $value))->toBe([__('aether-people::fieldtypes.isco_08.validation.required_major')])
        ->and(isco08StoredMessages(false, $value))->toBe([]);
})->with([
    'no tiers' => [[]],
    'blank major' => [['major_group' => '  ']],
    'sentinel major' => [['major_group' => false]],
    'unknown major' => [['major_group' => '99']],
    'a sub-major code as the major' => [['major_group' => '22']],
]);

it('accepts a valid chain, ignoring a stale value and invalid lower tiers', function (array $value): void {
    expect(isco08StoredMessages(true, $value))->toBe([])
        ->and(isco08StoredMessages(false, $value))->toBe([]);
})->with([
    'major only' => [['major_group' => '2']],
    'integer major' => [['major_group' => 2]],
    'full chain' => [['major_group' => '2', 'sub_major_group' => '22', 'minor_group' => '221', 'unit_group' => '2211']],
    'sub-major from another major' => [['major_group' => '3', 'sub_major_group' => '22']],
    'stale value' => [['major_group' => '2', 'value' => 'not a code']],
]);
