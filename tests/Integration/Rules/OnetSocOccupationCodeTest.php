<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Translation\PotentiallyTranslatedString;
use UAMSWeb\Aether\People\Rules\OnetSocOccupationCode;

covers(OnetSocOccupationCode::class);

/**
 * Behavior of the O*Net-SOC code rule (UAMSWeb\Aether\People\Rules\OnetSocOccupationCode):
 * an empty value passes, an array fails with its own message, and anything else
 * must match `NN-NNNN.NN` exactly, with no trailing newline.
 *
 * @command  php artisan test --compact tests/Integration/Rules/OnetSocOccupationCodeTest.php
 */

/**
 * Run the rule and return every message it failed with.
 *
 * @return list<string>
 */
function onetSocMessages(mixed $value): array
{
    $messages = [];

    new OnetSocOccupationCode()->validate('code', $value, function (string $message) use (&$messages): PotentiallyTranslatedString {
        $messages[] = $message;

        return new PotentiallyTranslatedString($message, mustBe(Translator::class, app('translator'), 'translator'));
    });

    return $messages;
}

it('passes a well-formed code', function (string $value): void {
    expect(onetSocMessages($value))->toBe([])
        ->and(new OnetSocOccupationCode()->isValid($value))->toBeTrue();
})->with([
    'first code' => ['11-1011.00'],
    'second code' => ['13-2011.01'],
]);

it('passes an empty value, leaving it to rules such as required', function (mixed $value): void {
    expect(onetSocMessages($value))->toBe([])
        ->and(new OnetSocOccupationCode()->isValid($value))->toBeTrue();
})->with([
    'null' => [null],
    'empty string' => [''],
    'zero string' => ['0'],
    'false' => [false],
    'empty array' => [[]],
]);

it('refuses a non-empty array with the array message', function (): void {
    expect(onetSocMessages(['11-1011.00']))->toBe(['The value must be a string, not an array.'])
        ->and(new OnetSocOccupationCode()->isValid(['11-1011.00']))->toBeFalse();
});

it('refuses a malformed code with the format message', function (mixed $value): void {
    expect(onetSocMessages($value))->toBe([__('aether-people::validation.onet_soc_occupation_code')])
        ->and(new OnetSocOccupationCode()->isValid($value))->toBeFalse();
})->with([
    'no period' => ['11-101100'],
    'no hyphen' => ['111011.00'],
    'three leading digits' => ['111-1011.00'],
    'one trailing digit' => ['11-1011.0'],
    'leading text' => ['x11-1011.00'],
    'trailing text' => ['11-1011.00x'],
    'trailing newline' => ["11-1011.00\n"],
    'integer' => [11],
    'true' => [true],
    'object' => [new stdClass],
]);
