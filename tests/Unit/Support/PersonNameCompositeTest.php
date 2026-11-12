<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use UAMSWeb\Aether\People\Support\PersonNameComposite;
use UAMSWeb\Aether\People\Support\PersonNameDisplayFormats;

/**
 * Joining a person's legal name with the refinement fields kept beside it, and
 * moving a stored name apart into those fields (UAMS-Web/uams-statamic-archive#3186). The JavaScript twin is
 * `resources/js/components/fieldtypes/person-name/personNameComposite.test.js`.
 *
 * @command  vendor/bin/pest tests/Unit/Support/PersonNameCompositeTest.php
 */

/**
 * A `person_name` value as stored before the split, with every refinement set.
 *
 * @return array<string, mixed>
 */
function compositeWholeName(): array
{
    return [
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => 'fu'],
        'no_middle_name' => false,
        'middle_name' => ['text' => 'Horatio', 'phonetic_text' => 'bɑr'],
        'no_nickname' => false,
        'nickname' => ['text' => 'Bones', 'phonetic_text' => 'kʌks'],
        'last_name' => ['text' => 'McCoy', 'phonetic_text' => 'bæz'],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => 'full_first_only_nickname@ns',
    ];
}

it('returns the stored name unchanged when no refinement field holds a value', function (): void {
    expect(PersonNameComposite::fromData(['person_name' => compositeWholeName()]))->toBe(compositeWholeName());
});

it('returns null when there is no person name', function (): void {
    expect(PersonNameComposite::fromData([]))->toBeNull()
        ->and(PersonNameComposite::fromData(['person_name' => 'Leonard McCoy', 'nickname' => ['text' => 'Bones']]))->toBeNull();
});

it('reads a person name held as a collection', function (): void {
    expect(PersonNameComposite::fromData(['person_name' => new Collection(compositeWholeName())]))->toBe(compositeWholeName());
});

it('rebuilds the whole name it was split from', function (): void {
    $parts = PersonNameComposite::split(compositeWholeName());

    expect(PersonNameDisplayFormats::normalizeValue(PersonNameComposite::fromData($parts)))
        ->toBe(PersonNameDisplayFormats::normalizeValue(compositeWholeName()));
});

it('leaves only the legal parts in person_name after the split', function (): void {
    $parts = PersonNameComposite::split(compositeWholeName());

    expect($parts['person_name'])->toBe([
        'first_name' => ['text' => 'Leonard', 'phonetic_text' => null],
        'no_middle_name' => false,
        'middle_name' => ['text' => 'Horatio', 'phonetic_text' => null],
        'no_nickname' => true,
        'nickname' => ['text' => null, 'phonetic_text' => null],
        'last_name' => ['text' => 'McCoy', 'phonetic_text' => null],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => null,
    ])
        ->and($parts['nickname'])->toBe(['text' => 'Bones', 'phonetic_text' => 'kʌks'])
        ->and($parts['name_pronunciation'])->toBe([
            'first_name' => 'fu',
            'middle_name' => 'bɑr',
            'last_name' => 'bæz',
        ])
        ->and($parts['name_display_format'])->toBe('full_first_only_nickname@ns');
});

it('splits a name with no refinements into empty refinement fields', function (): void {
    $parts = PersonNameComposite::split([
        'first_name' => ['text' => 'Nyota', 'phonetic_text' => ''],
        'no_middle_name' => true,
        'middle_name' => ['text' => null, 'phonetic_text' => null],
        'no_nickname' => false,
        'nickname' => ['text' => '', 'phonetic_text' => ''],
        'last_name' => ['text' => 'Uhura', 'phonetic_text' => '  '],
        'display_format' => '',
    ]);

    expect($parts['nickname'])->toBeNull()
        ->and($parts['name_pronunciation'])->toBeNull()
        ->and($parts['name_display_format'])->toBeNull()
        ->and($parts['person_name']['no_nickname'])->toBeTrue();
});

it('drops a nickname the stored name marked as none', function (): void {
    $name = compositeWholeName();
    $name['no_nickname'] = true;

    expect(PersonNameComposite::split($name)['nickname'])->toBeNull();
});

it('takes the nickname from its own field', function (): void {
    $joined = mustBeStringKeyed(PersonNameComposite::fromData([
        'person_name' => PersonNameComposite::split(compositeWholeName())['person_name'],
        'nickname' => ['text' => 'Len', 'phonetic_text' => 'lɛn'],
    ]), 'the joined name');

    expect($joined['no_nickname'])->toBeFalse()
        ->and($joined['nickname'])->toBe(['text' => 'Len', 'phonetic_text' => 'lɛn']);
});

it('reads an empty nickname field as no nickname', function (mixed $nickname): void {
    $joined = mustBeStringKeyed(PersonNameComposite::fromData([
        'person_name' => compositeWholeName(),
        'nickname' => $nickname,
    ]), 'the joined name');

    expect($joined['no_nickname'])->toBeTrue()
        ->and($joined['nickname'])->toBe(['text' => null, 'phonetic_text' => null]);
})->with([
    'empty text' => [['text' => '', 'phonetic_text' => 'kʌks']],
    'blank text' => [['text' => '   ', 'phonetic_text' => null]],
    'null' => [null],
]);

it('makes an otherwise complete name ready for display formats when the nickname is empty', function (): void {
    $name = compositeWholeName();
    $name['nickname'] = ['text' => '', 'phonetic_text' => ''];

    expect(PersonNameDisplayFormats::isReadyForDisplayFormatOptions(PersonNameDisplayFormats::normalizeValue($name)))->toBeFalse()
        ->and(PersonNameDisplayFormats::isReadyForDisplayFormatOptions(PersonNameDisplayFormats::normalizeValue(
            PersonNameComposite::fromData(['person_name' => $name, 'nickname' => null])
        )))->toBeTrue();
});

it('gives each legal part the pronunciation kept beside it', function (): void {
    $joined = mustBeStringKeyed(PersonNameComposite::fromData([
        'person_name' => PersonNameComposite::split(compositeWholeName())['person_name'],
        'name_pronunciation' => ['first_name' => 'fu', 'last_name' => null],
    ]), 'the joined name');

    expect($joined['first_name'])->toBe(['text' => 'Leonard', 'phonetic_text' => 'fu'])
        ->and($joined['middle_name'])->toBe(['text' => 'Horatio', 'phonetic_text' => null])
        ->and($joined['last_name'])->toBe(['text' => 'McCoy', 'phonetic_text' => null]);
});

it('takes the display format from its own field', function (mixed $format, ?string $expected): void {
    $joined = mustBeStringKeyed(PersonNameComposite::fromData([
        'person_name' => compositeWholeName(),
        'name_display_format' => $format,
    ]), 'the joined name');

    expect($joined['display_format'])->toBe($expected);
})->with([
    'a key' => ['full_first_only@ns', 'full_first_only@ns'],
    'an empty string' => ['', null],
    'null' => [null, null],
]);

it('gives a later legal part its pronunciation when an earlier part has none kept', function (): void {
    $joined = mustBeStringKeyed(PersonNameComposite::fromData([
        'person_name' => PersonNameComposite::split(compositeWholeName())['person_name'],
        'name_pronunciation' => ['middle_name' => 'bɑr', 'last_name' => 'bæz'],
    ]), 'the joined name');

    expect($joined['first_name'])->toBe(['text' => 'Leonard', 'phonetic_text' => null])
        ->and($joined['middle_name'])->toBe(['text' => 'Horatio', 'phonetic_text' => 'bɑr'])
        ->and($joined['last_name'])->toBe(['text' => 'McCoy', 'phonetic_text' => 'bæz']);
});

it('reads a blank display format field as no display format', function (): void {
    $joined = mustBeStringKeyed(PersonNameComposite::fromData([
        'person_name' => PersonNameComposite::split(compositeWholeName())['person_name'],
        'name_display_format' => '   ',
    ]), 'the joined name');

    expect($joined['display_format'])->toBeNull();
});

it('keeps the pronunciation of a later legal part when an earlier part is missing', function (): void {
    $name = compositeWholeName();
    unset($name['first_name']);

    expect(PersonNameComposite::split($name)['name_pronunciation'])->toBe([
        'middle_name' => 'bɑr',
        'last_name' => 'bæz',
    ]);
});

it('drops a nickname whose text is blank when splitting', function (): void {
    $name = compositeWholeName();
    $name['nickname'] = ['text' => '   ', 'phonetic_text' => 'kʌks'];

    expect(PersonNameComposite::split($name)['nickname'])->toBeNull();
});

it('drops a nickname pronunciation that is blank or not text when splitting', function (mixed $phonetic): void {
    $name = compositeWholeName();
    $name['nickname'] = ['text' => 'Bones', 'phonetic_text' => $phonetic];

    expect(PersonNameComposite::split($name)['nickname'])->toBe(['text' => 'Bones', 'phonetic_text' => null]);
})->with([
    'blank' => ['   '],
    'a number' => [7],
]);

it('drops a blank display format when splitting', function (): void {
    $name = compositeWholeName();
    $name['display_format'] = '   ';

    expect(PersonNameComposite::split($name)['name_display_format'])->toBeNull();
});
