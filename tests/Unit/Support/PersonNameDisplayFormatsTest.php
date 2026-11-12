<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\PersonNameDisplayFormats;

// cspell:ignore məˈɹiː Álvaro ˈæni

/**
 * Deep-merge a base name payload with overrides, typed for normalizeValue().
 *
 * @param  array<string, mixed>  $base
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function personNameDisplayVariant(array $base, array $overrides): array
{
    /** @var array<string, mixed> $merged */
    $merged = array_replace_recursive($base, $overrides);

    return $merged;
}

/**
 * Run the callback with PHP warnings thrown, so a warning from the formatter fails the test.
 *
 * The handler is set inside the test body because one set in beforeEach() does not
 * catch warnings raised by the test.
 *
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function nameFormatsWithWarningsThrown(callable $callback): mixed
{
    set_error_handler(static function (int $level, string $message): never {
        throw new ErrorException($message, 0, $level);
    }, E_WARNING);

    try {
        return $callback();
    } finally {
        restore_error_handler();
    }
}

/**
 * Mutation: with PersonNameDisplayFormatsAlternateExpansionTest and PersonNameDisplayFormatsFixturesTest,
 * every tested mutant of UAMSWeb\Aether\People\Support\PersonNameDisplayFormats is killed. The
 * equivalent ones are annotated in the source with `@pest-mutate-ignore` and the reason on the line
 * above. The coverage driver marks no line of the `PATTERN_DEDUPE_RANK`, `ENGLISH_LETTER_IPA` and
 * `PEDIGREE_LABEL_IPA` class constants as executed, so their mutants are annotated too, each with
 * the test that pins it: a dataset test reads every letter and suffix entry, and the colliding-label
 * test pins the rank order; rank changes that test misses were checked over 504 generated names and
 * change no output. Before the move the site reported 79 mutants of this class uncovered.
 *
 * @command  vendor/bin/pest tests/Unit/Support/PersonNameDisplayFormatsTest.php
 */
it('builds initials splitting on spaces and hyphens', function (): void {
    expect(PersonNameDisplayFormats::initials('James Earl'))->toBe('J. E.');
    expect(PersonNameDisplayFormats::initials('Mary-Jane'))->toBe('M. J.');
});

it('keeps build options empty until prerequisites are met', function (): void {
    /** @var array<string, mixed> $base */
    $base = [
        'first_name' => ['text' => 'James', 'phonetic_text' => ''],
        'no_middle_name' => true,
        'middle_name' => ['text' => '', 'phonetic_text' => ''],
        'no_nickname' => true,
        'nickname' => ['text' => '', 'phonetic_text' => ''],
        'last_name' => ['text' => 'Carter', 'phonetic_text' => ''],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => null,
    ];

    $n = PersonNameDisplayFormats::normalizeValue($base);
    expect(PersonNameDisplayFormats::isReadyForDisplayFormatOptions($n))->toBeTrue();
    expect(PersonNameDisplayFormats::buildOptions($n))->not->toBe([]);

    $missingFirst = PersonNameDisplayFormats::normalizeValue(personNameDisplayVariant($base, [
        'first_name' => ['text' => ''],
    ]));
    expect(PersonNameDisplayFormats::isReadyForDisplayFormatOptions($missingFirst))->toBeFalse();
    expect(PersonNameDisplayFormats::buildOptions($missingFirst))->toBe([]);

    $missingLast = PersonNameDisplayFormats::normalizeValue(personNameDisplayVariant($base, [
        'last_name' => ['text' => ''],
    ]));
    expect(PersonNameDisplayFormats::isReadyForDisplayFormatOptions($missingLast))->toBeFalse();
    expect(PersonNameDisplayFormats::buildOptions($missingLast))->toBe([]);

    $middleUnset = PersonNameDisplayFormats::normalizeValue(personNameDisplayVariant($base, [
        'no_middle_name' => false,
        'middle_name' => ['text' => '', 'phonetic_text' => ''],
    ]));
    expect(PersonNameDisplayFormats::isReadyForDisplayFormatOptions($middleUnset))->toBeFalse();
    expect(PersonNameDisplayFormats::buildOptions($middleUnset))->toBe([]);

    $nickUnset = PersonNameDisplayFormats::normalizeValue(personNameDisplayVariant($base, [
        'no_nickname' => false,
        'nickname' => ['text' => '', 'phonetic_text' => ''],
    ]));
    expect(PersonNameDisplayFormats::isReadyForDisplayFormatOptions($nickUnset))->toBeFalse();
    expect(PersonNameDisplayFormats::buildOptions($nickUnset))->toBe([]);

    $suffixUnset = PersonNameDisplayFormats::normalizeValue(personNameDisplayVariant($base, [
        'no_generational_suffix' => false,
        'generational_suffix' => null,
    ]));
    expect(PersonNameDisplayFormats::isReadyForDisplayFormatOptions($suffixUnset))->toBeFalse();
    expect(PersonNameDisplayFormats::buildOptions($suffixUnset))->toBe([]);
});

it('includes with- and without-suffix groups when a suffix is set', function (): void {
    $n = PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'James', 'phonetic_text' => ''],
        'no_middle_name' => true,
        'middle_name' => ['text' => '', 'phonetic_text' => ''],
        'no_nickname' => true,
        'nickname' => ['text' => '', 'phonetic_text' => ''],
        'last_name' => ['text' => 'Carter', 'phonetic_text' => ''],
        'no_generational_suffix' => false,
        'generational_suffix' => 'Jr.',
        'display_format' => null,
    ]);

    $opts = PersonNameDisplayFormats::buildOptions($n);
    $groups = array_column($opts, 'group');

    expect($groups)->toContain('with_suffix');
    expect($groups)->toContain('without_suffix');
});

it('keeps allowed keys aligned with the option keys', function (): void {
    $n = PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'A', 'phonetic_text' => ''],
        'no_middle_name' => true,
        'middle_name' => ['text' => '', 'phonetic_text' => ''],
        'no_nickname' => true,
        'nickname' => ['text' => '', 'phonetic_text' => ''],
        'last_name' => ['text' => 'B', 'phonetic_text' => ''],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => null,
    ]);

    $opts = PersonNameDisplayFormats::buildOptions($n);
    $keys = PersonNameDisplayFormats::allowedKeys($n);

    expect($keys)->toBe(array_column($opts, 'key'));
});

it('delimits the nickname with pipe-spaces in legal-order phonetic text', function (): void {
    $n = PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'Joshua', 'phonetic_text' => 'ˈdʒɑ.ʃu.waː'],
        'no_middle_name' => false,
        'middle_name' => ['text' => 'Ryan', 'phonetic_text' => 'ˈɹaɪ.jən'],
        'no_nickname' => false,
        'nickname' => ['text' => 'Josh', 'phonetic_text' => 'ˈdʒɑʃ'],
        'last_name' => ['text' => 'Dardi', 'phonetic_text' => 'ˈdʰɑːɹ.di'],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => 'full_first_full_middle_nickname@ns',
    ]);

    expect(PersonNameDisplayFormats::legalOrderPhoneticTextForComputedField($n))
        ->toBe('ˈdʒɑ.ʃu.waː ˈɹaɪ.jən | ˈdʒɑʃ | ˈdʰɑːɹ.di');

    expect(PersonNameDisplayFormats::resolvedPhoneticText($n, 'full_first_full_middle_nickname@ns'))
        ->toBe('ˈdʒɑ.ʃu.waː ˈɹaɪ.jən | ˈdʒɑʃ | ˈdʰɑːɹ.di');
});

it('joins a nickname-only phonetic to the last name with a space, not a pipe', function (): void {
    $n = PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'Joshua', 'phonetic_text' => 'ˈdʒɑ.ʃu.waː'],
        'no_middle_name' => false,
        'middle_name' => ['text' => 'Ryan', 'phonetic_text' => 'ˈɹaɪ.jən'],
        'no_nickname' => false,
        'nickname' => ['text' => 'Tallest', 'phonetic_text' => 'ˈtɔlɪst'],
        'last_name' => ['text' => 'Daugherty', 'phonetic_text' => 'ˈdʰɑːɹ.di'],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => 'nickname_only@ns',
    ]);

    expect(PersonNameDisplayFormats::resolvedPhoneticText($n, 'nickname_only@ns'))
        ->toBe('ˈtɔlɪst ˈdʰɑːɹ.di');
});

/**
 * A full name: first, middle, nickname, last and a Jr. suffix, each with a phonetic.
 *
 * @return array<string, mixed>
 */
function personNameFull(): array
{
    return PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'Ann', 'phonetic_text' => 'æn'],
        'middle_name' => ['text' => 'Marie', 'phonetic_text' => 'məˈɹiː'],
        'nickname' => ['text' => 'Annie', 'phonetic_text' => 'ˈæni'],
        'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː'],
        'generational_suffix' => 'Jr.',
    ]);
}

/**
 * A first, nickname and last name, with no middle name and no suffix.
 *
 * @return array<string, mixed>
 */
function personNameNicknameOnly(): array
{
    return PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'Ann', 'phonetic_text' => 'æn'],
        'no_middle_name' => true,
        'nickname' => ['text' => 'Annie', 'phonetic_text' => 'ˈæni'],
        'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː'],
        'no_generational_suffix' => true,
    ]);
}

it('offers the generational suffixes as matching value and label pairs', function (): void {
    expect(PersonNameDisplayFormats::generationalSuffixSelectOptions())->toBe(array_map(
        fn (string $label): array => ['value' => $label, 'label' => $label],
        ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'X']
    ));
});

it('normalizes a stored value into text pairs, flags, a suffix and a display format', function (): void {
    expect(PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'Ann', 'phonetic_text' => 5],
        'middle_name' => 'not a pair',
        'nickname' => ['phonetic_text' => 'ˈæni'],
        'no_middle_name' => ' Yes ',
        'no_nickname' => 'off',
        'no_generational_suffix' => 2,
        'generational_suffix' => '  Sr. ',
        'display_format' => 5,
    ]))->toBe([
        'first_name' => ['text' => 'Ann', 'phonetic_text' => ''],
        'no_middle_name' => true,
        'middle_name' => ['text' => '', 'phonetic_text' => ''],
        'no_nickname' => false,
        'nickname' => ['text' => '', 'phonetic_text' => 'ˈæni'],
        'last_name' => ['text' => '', 'phonetic_text' => ''],
        'no_generational_suffix' => false,
        'generational_suffix' => 'Sr.',
        'display_format' => null,
    ])->and(PersonNameDisplayFormats::normalizeValue(null)['generational_suffix'])->toBeNull();
});

it('reads a flag from booleans, words, integers and other values', function (mixed $raw, bool $flag): void {
    expect(PersonNameDisplayFormats::normalizeValue(['no_middle_name' => $raw])['no_middle_name'])->toBe($flag);
})->with([
    'true' => [true, true],
    'false' => [false, false],
    'the word on' => ['ON', true],
    'the word true' => [' true', true],
    'the digit 1' => ['1', true],
    'a numeric string that only loosely equals 1' => ['1.0', false],
    'the word no' => ['no', false],
    'the integer 1' => [1, true],
    'the integer 0' => [0, false],
    'a float' => [1.5, true],
    'an empty list' => [[], false],
    'a blank suffix stays null' => [null, false],
]);

it('keeps a blank suffix and a string display format as given', function (): void {
    $normalized = PersonNameDisplayFormats::normalizeValue(['generational_suffix' => '   ', 'display_format' => 'middle_only@ns']);

    expect($normalized['generational_suffix'])->toBeNull()
        ->and($normalized['display_format'])->toBe('middle_only@ns');
});

it('builds every display option for a full name, with and without the suffix', function (): void {
    expect(PersonNameDisplayFormats::buildOptions(personNameFull()))->toBe([
        [
            'key' => 'full_first_full_middle@ws',
            'label' => 'Ann Marie Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'full_first_full_middle_nickname@ws',
            'label' => 'Ann Marie "Annie" Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'full_first_middle_initial@ws',
            'label' => 'Ann M. Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'full_first_middle_initial_nickname@ws',
            'label' => 'Ann M. "Annie" Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'first_initial_full_middle@ws',
            'label' => 'A. Marie Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'first_initial_full_middle_nickname@ws',
            'label' => 'A. Marie "Annie" Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'middle_only@ws',
            'label' => 'Marie Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'middle_only_nickname@ws',
            'label' => 'Marie "Annie" Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'first_initial_middle_initial@ws',
            'label' => 'A. M. Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'first_initial_middle_initial_nickname@ws',
            'label' => 'A. M. "Annie" Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'full_first_only@ws',
            'label' => 'Ann Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'full_first_only_nickname@ws',
            'label' => 'Ann "Annie" Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'nickname_only@ws',
            'label' => 'Annie Lee Jr.',
            'group' => 'with_suffix',
        ],
        [
            'key' => 'full_first_full_middle@ns',
            'label' => 'Ann Marie Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'full_first_full_middle_nickname@ns',
            'label' => 'Ann Marie "Annie" Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'full_first_middle_initial@ns',
            'label' => 'Ann M. Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'full_first_middle_initial_nickname@ns',
            'label' => 'Ann M. "Annie" Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'first_initial_full_middle@ns',
            'label' => 'A. Marie Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'first_initial_full_middle_nickname@ns',
            'label' => 'A. Marie "Annie" Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'middle_only@ns',
            'label' => 'Marie Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'middle_only_nickname@ns',
            'label' => 'Marie "Annie" Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'first_initial_middle_initial@ns',
            'label' => 'A. M. Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'first_initial_middle_initial_nickname@ns',
            'label' => 'A. M. "Annie" Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'full_first_only@ns',
            'label' => 'Ann Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'full_first_only_nickname@ns',
            'label' => 'Ann "Annie" Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'nickname_only@ns',
            'label' => 'Annie Lee',
            'group' => 'without_suffix',
        ],
    ]);
});

it('keeps the higher-ranked pattern when two patterns produce the same label', function (): void {
    // A middle name that is already an initial makes the full and initial forms collide
    $normalized = PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'Ann', 'phonetic_text' => ''],
        'middle_name' => ['text' => 'B.', 'phonetic_text' => ''],
        'no_nickname' => true,
        'last_name' => ['text' => 'Lee', 'phonetic_text' => ''],
        'no_generational_suffix' => true,
    ]);

    expect(PersonNameDisplayFormats::buildOptions($normalized))->toBe([
        [
            'key' => 'full_first_middle_initial@ns',
            'label' => 'Ann B. Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'middle_only@ns',
            'label' => 'B. Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'first_initial_middle_initial@ns',
            'label' => 'A. B. Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'full_first_only@ns',
            'label' => 'Ann Lee',
            'group' => 'without_suffix',
        ],
    ]);
});

it('builds the nickname options for a name with no middle name', function (): void {
    expect(PersonNameDisplayFormats::buildOptions(personNameNicknameOnly()))->toBe([
        [
            'key' => 'full_first_only@ns',
            'label' => 'Ann Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'full_first_only_nickname@ns',
            'label' => 'Ann "Annie" Lee',
            'group' => 'without_suffix',
        ],
        [
            'key' => 'nickname_only@ns',
            'label' => 'Annie Lee',
            'group' => 'without_suffix',
        ],
    ]);
});

it('resolves the phonetic text for every display option', function (): void {
    $full = personNameFull();
    $nickname = personNameNicknameOnly();

    expect(array_combine(PersonNameDisplayFormats::allowedKeys($full), array_map(
        fn (string $key): string => PersonNameDisplayFormats::resolvedPhoneticText($full, $key),
        PersonNameDisplayFormats::allowedKeys($full)
    )))->toBe([
        'full_first_full_middle@ws' => 'æn məˈɹiː liː ˈʤunjər',
        'full_first_full_middle_nickname@ws' => 'æn məˈɹiː | ˈæni | liː ˈʤunjər',
        'full_first_middle_initial@ws' => 'æn ɛm liː ˈʤunjər',
        'full_first_middle_initial_nickname@ws' => 'æn ɛm | ˈæni | liː ˈʤunjər',
        'first_initial_full_middle@ws' => 'eɪ məˈɹiː liː ˈʤunjər',
        'first_initial_full_middle_nickname@ws' => 'eɪ məˈɹiː | ˈæni | liː ˈʤunjər',
        'middle_only@ws' => 'məˈɹiː liː ˈʤunjər',
        'middle_only_nickname@ws' => 'məˈɹiː | ˈæni | liː ˈʤunjər',
        'first_initial_middle_initial@ws' => 'eɪ ɛm liː ˈʤunjər',
        'first_initial_middle_initial_nickname@ws' => 'eɪ ɛm | ˈæni | liː ˈʤunjər',
        'full_first_only@ws' => 'æn liː ˈʤunjər',
        'full_first_only_nickname@ws' => 'æn | ˈæni | liː ˈʤunjər',
        'nickname_only@ws' => 'ˈæni liː ˈʤunjər',
        'full_first_full_middle@ns' => 'æn məˈɹiː liː',
        'full_first_full_middle_nickname@ns' => 'æn məˈɹiː | ˈæni | liː',
        'full_first_middle_initial@ns' => 'æn ɛm liː',
        'full_first_middle_initial_nickname@ns' => 'æn ɛm | ˈæni | liː',
        'first_initial_full_middle@ns' => 'eɪ məˈɹiː liː',
        'first_initial_full_middle_nickname@ns' => 'eɪ məˈɹiː | ˈæni | liː',
        'middle_only@ns' => 'məˈɹiː liː',
        'middle_only_nickname@ns' => 'məˈɹiː | ˈæni | liː',
        'first_initial_middle_initial@ns' => 'eɪ ɛm liː',
        'first_initial_middle_initial_nickname@ns' => 'eɪ ɛm | ˈæni | liː',
        'full_first_only@ns' => 'æn liː',
        'full_first_only_nickname@ns' => 'æn | ˈæni | liː',
        'nickname_only@ns' => 'ˈæni liː',
    ])->and(array_combine(PersonNameDisplayFormats::allowedKeys($nickname), array_map(
        fn (string $key): string => PersonNameDisplayFormats::resolvedPhoneticText($nickname, $key),
        PersonNameDisplayFormats::allowedKeys($nickname)
    )))->toBe([
        'full_first_only@ns' => 'æn liː',
        'full_first_only_nickname@ns' => 'æn | ˈæni | liː',
        'nickname_only@ns' => 'ˈæni liː',
    ]);
});

it('expands every display option but the skipped one into alternate name rows', function (): void {
    $full = personNameFull();

    expect(PersonNameDisplayFormats::alternateNameDisplayExpansionRows($full, ' full_first_full_middle@ws ', true))->toBe([
        [
            'text' => 'Ann Marie "Annie" Lee Jr.',
            'phonetic_text' => 'æn məˈɹiː | ˈæni | liː ˈʤunjər',
        ],
        [
            'text' => 'Ann M. Lee Jr.',
            'phonetic_text' => 'æn ɛm liː ˈʤunjər',
        ],
        [
            'text' => 'Ann M. "Annie" Lee Jr.',
            'phonetic_text' => 'æn ɛm | ˈæni | liː ˈʤunjər',
        ],
        [
            'text' => 'A. Marie Lee Jr.',
            'phonetic_text' => 'eɪ məˈɹiː liː ˈʤunjər',
        ],
        [
            'text' => 'A. Marie "Annie" Lee Jr.',
            'phonetic_text' => 'eɪ məˈɹiː | ˈæni | liː ˈʤunjər',
        ],
        [
            'text' => 'Marie Lee Jr.',
            'phonetic_text' => 'məˈɹiː liː ˈʤunjər',
        ],
        [
            'text' => 'Marie "Annie" Lee Jr.',
            'phonetic_text' => 'məˈɹiː | ˈæni | liː ˈʤunjər',
        ],
        [
            'text' => 'A. M. Lee Jr.',
            'phonetic_text' => 'eɪ ɛm liː ˈʤunjər',
        ],
        [
            'text' => 'A. M. "Annie" Lee Jr.',
            'phonetic_text' => 'eɪ ɛm | ˈæni | liː ˈʤunjər',
        ],
        [
            'text' => 'Ann Lee Jr.',
            'phonetic_text' => 'æn liː ˈʤunjər',
        ],
        [
            'text' => 'Ann "Annie" Lee Jr.',
            'phonetic_text' => 'æn | ˈæni | liː ˈʤunjər',
        ],
        [
            'text' => 'Annie Lee Jr.',
            'phonetic_text' => 'ˈæni liː ˈʤunjər',
        ],
        [
            'text' => 'Ann Marie Lee',
            'phonetic_text' => 'æn məˈɹiː liː',
        ],
        [
            'text' => 'Ann Marie "Annie" Lee',
            'phonetic_text' => 'æn məˈɹiː | ˈæni | liː',
        ],
        [
            'text' => 'Ann M. Lee',
            'phonetic_text' => 'æn ɛm liː',
        ],
        [
            'text' => 'Ann M. "Annie" Lee',
            'phonetic_text' => 'æn ɛm | ˈæni | liː',
        ],
        [
            'text' => 'A. Marie Lee',
            'phonetic_text' => 'eɪ məˈɹiː liː',
        ],
        [
            'text' => 'A. Marie "Annie" Lee',
            'phonetic_text' => 'eɪ məˈɹiː | ˈæni | liː',
        ],
        [
            'text' => 'Marie Lee',
            'phonetic_text' => 'məˈɹiː liː',
        ],
        [
            'text' => 'Marie "Annie" Lee',
            'phonetic_text' => 'məˈɹiː | ˈæni | liː',
        ],
        [
            'text' => 'A. M. Lee',
            'phonetic_text' => 'eɪ ɛm liː',
        ],
        [
            'text' => 'A. M. "Annie" Lee',
            'phonetic_text' => 'eɪ ɛm | ˈæni | liː',
        ],
        [
            'text' => 'Ann Lee',
            'phonetic_text' => 'æn liː',
        ],
        [
            'text' => 'Ann "Annie" Lee',
            'phonetic_text' => 'æn | ˈæni | liː',
        ],
        [
            'text' => 'Annie Lee',
            'phonetic_text' => 'ˈæni liː',
        ],
    ])
        ->and(array_column(PersonNameDisplayFormats::alternateNameDisplayExpansionRows($full, null, false), 'phonetic_text'))
        ->toBe(array_fill(0, 26, null))
        ->and(PersonNameDisplayFormats::alternateNameDisplayExpansionRows($full, null, false))->toHaveCount(26);
});

it('expands a name that is not ready for display options into one partial row', function (): void {
    $partial = PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'Ann', 'phonetic_text' => 'æn'],
        'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː'],
    ]);

    expect(PersonNameDisplayFormats::alternateNameDisplayExpansionRows($partial, null, true))->toBe([['text' => 'Ann Lee', 'phonetic_text' => 'æn liː']])
        ->and(PersonNameDisplayFormats::alternateNameDisplayExpansionRows($partial, null, false))->toBe([['text' => 'Ann Lee', 'phonetic_text' => null]])
        ->and(PersonNameDisplayFormats::alternateNameDisplayExpansionRows(PersonNameDisplayFormats::normalizeValue([
            'first_name' => ['text' => 'Ann'],
        ]), null, true))->toBe([['text' => 'Ann', 'phonetic_text' => null]])
        ->and(PersonNameDisplayFormats::alternateNameDisplayExpansionRows(PersonNameDisplayFormats::normalizeValue([]), null, true))->toBe([]);
});

it('resolves the display format key, falling back to the first option', function (): void {
    $full = personNameFull();

    expect(PersonNameDisplayFormats::resolveEffectiveDisplayFormatKey($full, ' middle_only@ns '))->toBe('middle_only@ns')
        ->and(PersonNameDisplayFormats::resolveEffectiveDisplayFormatKey($full, 'nonsense@ns'))->toBe('full_first_full_middle@ws')
        ->and(PersonNameDisplayFormats::resolveEffectiveDisplayFormatKey($full, null))->toBe('full_first_full_middle@ws')
        ->and(nameFormatsWithWarningsThrown(fn (): ?string => PersonNameDisplayFormats::resolveEffectiveDisplayFormatKey(PersonNameDisplayFormats::normalizeValue([]), 'middle_only@ns')))->toBeNull();
});

it('resolves the display text from a chosen option, the first option, or the partial name', function (): void {
    $full = personNameFull();

    expect(PersonNameDisplayFormats::resolvedDisplayText($full, 'middle_only@ns'))->toBe('Marie Lee')
        ->and(PersonNameDisplayFormats::resolvedDisplayText($full, 'nonsense'))->toBe('Ann Marie Lee Jr.')
        ->and(PersonNameDisplayFormats::resolvedDisplayText($full, null))->toBe('Ann Marie Lee Jr.')
        ->and(PersonNameDisplayFormats::resolvedDisplayText(PersonNameDisplayFormats::normalizeValue([
            'first_name' => ['text' => 'Ann'],
            'nickname' => ['text' => 'Annie'],
        ]), 'middle_only@ns'))->toBe('Ann "Annie"');
});

it('builds a partial display text from whichever parts are present and not switched off', function (array $value, string $text): void {
    /** @var array<string, mixed> $typedValue */
    $typedValue = $value;

    expect(PersonNameDisplayFormats::buildPartialDisplayText(PersonNameDisplayFormats::normalizeValue($typedValue)))->toBe($text);
})->with([
    'every part' => [['first_name' => ['text' => ' Ann '], 'middle_name' => ['text' => 'Marie'], 'nickname' => ['text' => 'Annie'], 'last_name' => ['text' => 'Lee'], 'generational_suffix' => 'Jr.'], 'Ann Marie "Annie" Lee Jr.'],
    'parts switched off' => [['first_name' => ['text' => 'Ann'], 'no_middle_name' => true, 'middle_name' => ['text' => 'Marie'], 'no_nickname' => true, 'nickname' => ['text' => 'Annie'], 'last_name' => ['text' => 'Lee'], 'no_generational_suffix' => true, 'generational_suffix' => 'Jr.'], 'Ann Lee'],
    'only a last name' => [['last_name' => ['text' => 'Lee']], 'Lee'],
    'nothing' => [[], ''],
]);

it('reads a missing or malformed name part as empty text', function (): void {
    expect(PersonNameDisplayFormats::buildPartialDisplayText(['first_name' => 'Ann', 'last_name' => ['text' => ['Lee']], 'middle_name' => ['text' => 5]]))->toBe('5');
});

it('builds a partial phonetic text from parts that have both text and phonetic', function (array $value, string $phonetic): void {
    /** @var array<string, mixed> $typedValue */
    $typedValue = $value;

    expect(PersonNameDisplayFormats::buildPartialPhoneticText(PersonNameDisplayFormats::normalizeValue($typedValue)))->toBe($phonetic);
})->with([
    'a suffix with a known phonetic' => [['first_name' => ['text' => 'Ann', 'phonetic_text' => 'æn'], 'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː'], 'generational_suffix' => 'III'], 'æn liː ðə θɜrd'],
    'a suffix with no known phonetic' => [['first_name' => ['text' => 'Ann', 'phonetic_text' => 'æn'], 'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː'], 'generational_suffix' => 'Esq.'], 'æn liː'],
    'a suffix switched off' => [['first_name' => ['text' => 'Ann', 'phonetic_text' => 'æn'], 'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː'], 'no_generational_suffix' => true, 'generational_suffix' => 'III'], 'æn liː'],
    'a phonetic without its text' => [['first_name' => ['text' => '', 'phonetic_text' => 'æn'], 'middle_name' => ['text' => '', 'phonetic_text' => 'məˈɹiː'], 'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː']], 'liː'],
    'a middle name switched off' => [['first_name' => ['text' => 'Ann', 'phonetic_text' => 'æn'], 'no_middle_name' => true, 'middle_name' => ['text' => 'Marie', 'phonetic_text' => 'məˈɹiː'], 'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː']], 'æn liː'],
    'a nickname switched off' => [['first_name' => ['text' => 'Ann', 'phonetic_text' => 'æn'], 'no_nickname' => true, 'nickname' => ['text' => 'Annie', 'phonetic_text' => 'ˈæni'], 'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː']], 'æn liː'],
    'a nickname only, no last phonetic' => [['nickname' => ['text' => 'Annie', 'phonetic_text' => 'ˈæni']], 'ˈæni'],
    'a first name and nickname, no last phonetic' => [['first_name' => ['text' => 'Ann', 'phonetic_text' => 'æn'], 'nickname' => ['text' => 'Annie', 'phonetic_text' => 'ˈæni']], 'æn | ˈæni'],
    'a nickname and last name only' => [['nickname' => ['text' => 'Annie', 'phonetic_text' => 'ˈæni'], 'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː']], 'ˈæni liː'],
    'a blank text with a phonetic' => [['first_name' => ['text' => '  ', 'phonetic_text' => 'æn'], 'middle_name' => ['text' => ' ', 'phonetic_text' => 'məˈɹiː'], 'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː']], 'liː'],
]);

it('falls back to the partial phonetic when the chosen option has none', function (): void {
    // A ready name with no phonetics at all resolves every pattern to empty text
    $silent = PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'Ann'],
        'no_middle_name' => true,
        'no_nickname' => true,
        'last_name' => ['text' => 'Lee', 'phonetic_text' => ''],
        'no_generational_suffix' => true,
    ]);

    expect(PersonNameDisplayFormats::resolvedPhoneticText($silent, 'full_first_only@ns'))->toBe('')
        ->and(PersonNameDisplayFormats::resolvedPhoneticText(PersonNameDisplayFormats::normalizeValue([
            'first_name' => ['text' => 'Ann', 'phonetic_text' => '"æn"'],
        ]), null))->toBe('æn');
});

it('drops straight quotes from a phonetic for computed output', function (): void {
    expect(PersonNameDisplayFormats::normalizeNamePhoneticForComputedOutput('"æn" liː'))->toBe('æn liː');
});

it('composes and parses display format keys', function (?string $key, ?array $parsed): void {
    expect(nameFormatsWithWarningsThrown(fn (): ?array => PersonNameDisplayFormats::parseDisplayFormatKey($key)))->toBe($parsed);
})->with([
    'with suffix' => ['middle_only@ws', ['pattern' => 'middle_only', 'with_suffix' => true]],
    'without suffix' => ['middle_only@ns', ['pattern' => 'middle_only', 'with_suffix' => false]],
    'null' => [null, null],
    'empty' => ['', null],
    'no separator' => ['middle_only', null],
    'no pattern' => ['@ws', null],
    'an unknown suffix flag' => ['middle_only@xs', null],
    'a second separator' => ['middle_only@ws@x', null],
]);

it('takes initials from each word and drops blank input', function (): void {
    expect(PersonNameDisplayFormats::initials('  '))->toBe('')
        ->and(PersonNameDisplayFormats::initials(' Álvaro  de-la '))->toBe('Á. d. l.')
        // Bytes that are not valid UTF-8 make the unicode split fail
        ->and(nameFormatsWithWarningsThrown(fn (): string => PersonNameDisplayFormats::initials("\xff\xfe")))->toBe('');
});

it('appends a suffix only when there is one', function (string $base, ?string $suffix, string $joined): void {
    expect(PersonNameDisplayFormats::appendSuffix($base, $suffix))->toBe($joined);
})->with([
    'both' => [' Ann Lee ', ' Jr. ', 'Ann Lee Jr.'],
    'no suffix' => ['Ann Lee', null, 'Ann Lee'],
    'a blank suffix' => ['Ann Lee', '  ', 'Ann Lee'],
    'no base' => ['  ', 'Jr.', 'Jr.'],
    'no base and a padded suffix' => ['', ' Jr. ', 'Jr.'],
]);

it('spells the initials of display text in IPA letter names', function (): void {
    expect(PersonNameDisplayFormats::initialsIpaFromDisplayText(' ann marie 9lives '))->toBe('eɪ ɛm')
        ->and(PersonNameDisplayFormats::initialsIpaFromDisplayText('   '))->toBe('');
});

it('reads a generational suffix label as its spoken IPA', function (string $label, string $ipa): void {
    expect(PersonNameDisplayFormats::generationalSuffixPhoneticFromLabel($label))->toBe($ipa);
})->with([
    'jr with a period' => [' JR. ', 'ˈʤunjər'],
    'a roman numeral' => ['III', 'ðə θɜrd'],
    'blank' => [' . ', ''],
    'unknown' => ['Esq.', ''],
    'a leading tab' => ["\tJr.", 'ˈʤunjər'],
    'a space inside' => ['J r.', 'ˈʤunjər'],
]);

it('keeps the first non-empty phonetic for each distinct text, in first-seen order', function (): void {
    expect(PersonNameDisplayFormats::mergedPhoneticTextByTrimmedText([
        ['text' => ' Ann ', 'phonetic_text' => '  '],
        ['text' => 'Lee', 'phonetic_text' => 'liː'],
        ['text' => 'Ann', 'phonetic_text' => ' æn '],
        ['text' => 'Ann', 'phonetic_text' => 'later'],
        ['text' => 5, 'phonetic_text' => 7],
        ['text' => '', 'phonetic_text' => 'dropped'],
        ['text' => ['x'], 'phonetic_text' => 'dropped'],
        ['text' => 'Bo', 'phonetic_text' => ['x']],
        ['text' => 'Cy'],
        ['text' => 1.5, 'phonetic_text' => 'wʌn'],
    ]))->toBe(['Ann' => 'æn', 'Lee' => 'liː', '5' => '7', 'Bo' => null, 'Cy' => null, '1.5' => 'wʌn']);
});

it('builds a normalized name from alternate-name parts', function (): void {
    expect(PersonNameDisplayFormats::normalizedFromAlternateNameShell(
        ['text' => ' Ann ', 'phonetic_text' => ' æn '],
        ['text' => '', 'phonetic_text' => 'məˈɹiː'],
        ['text' => 5, 'phonetic_text' => 6],
        ['text' => ['x'], 'phonetic_text' => ['y']],
        '  III '
    ))->toBe([
        'first_name' => ['text' => 'Ann', 'phonetic_text' => 'æn'],
        'no_middle_name' => true,
        'middle_name' => ['text' => '', 'phonetic_text' => 'məˈɹiː'],
        'no_nickname' => false,
        'nickname' => ['text' => '5', 'phonetic_text' => '6'],
        'last_name' => ['text' => '', 'phonetic_text' => ''],
        'no_generational_suffix' => false,
        'generational_suffix' => 'III',
        'display_format' => null,
    ])->and(PersonNameDisplayFormats::normalizedFromAlternateNameShell([], [], [], [], 5))->toMatchArray([
        'no_middle_name' => true,
        'no_nickname' => true,
        'no_generational_suffix' => true,
        'generational_suffix' => null,
    ]);
});

/**
 * personNameFull() as stored, before normalizeValue(): no flag keys at all.
 *
 * @return array<string, mixed>
 */
function personNameFullWithoutFlags(): array
{
    return [
        'first_name' => ['text' => 'Ann', 'phonetic_text' => 'æn'],
        'middle_name' => ['text' => 'Marie', 'phonetic_text' => 'məˈɹiː'],
        'nickname' => ['text' => 'Annie', 'phonetic_text' => 'ˈæni'],
        'last_name' => ['text' => 'Lee', 'phonetic_text' => 'liː'],
        'generational_suffix' => 'Jr.',
    ];
}

it('does not read an array-like object as a name part', function (): void {
    expect(PersonNameDisplayFormats::normalizeValue([
        'first_name' => new ArrayObject(['text' => 'Ann', 'phonetic_text' => 'æn']),
    ])['first_name'])->toBe(['text' => '', 'phonetic_text' => '']);
});

it('reads a part with no text key as empty text', function (): void {
    expect(PersonNameDisplayFormats::buildPartialDisplayText([
        'first_name' => ['phonetic_text' => 'æn'],
        'last_name' => ['text' => 'Lee'],
    ]))->toBe('Lee');
});

it('reads a part that is an array-like object as empty text', function (): void {
    expect(PersonNameDisplayFormats::buildPartialDisplayText([
        'first_name' => new ArrayObject(['text' => 'Ann']),
        'last_name' => ['text' => 'Lee'],
    ]))->toBe('Lee');
});

it('is not ready while a part that is not switched off is blank', function (array $overrides): void {
    /** @var array<string, mixed> $typedOverrides */
    $typedOverrides = $overrides;

    expect(PersonNameDisplayFormats::isReadyForDisplayFormatOptions(personNameFull()))->toBeTrue()
        ->and(PersonNameDisplayFormats::isReadyForDisplayFormatOptions(personNameDisplayVariant(personNameFull(), $typedOverrides)))->toBeFalse();
})->with([
    'a blank first name' => [['first_name' => ['text' => '  ']]],
    'a blank middle name' => [['middle_name' => ['text' => '  ']]],
    'a blank last name' => [['last_name' => ['text' => '  ']]],
    'a blank nickname' => [['nickname' => ['text' => '  ']]],
    'a blank suffix' => [['generational_suffix' => '  ']],
]);

it('reads a missing flag as switched on for nothing', function (): void {
    $stored = personNameFullWithoutFlags();
    $withoutMiddle = $stored;
    $withoutMiddle['middle_name'] = ['text' => '', 'phonetic_text' => ''];
    $withoutNickname = $stored;
    $withoutNickname['nickname'] = ['text' => '', 'phonetic_text' => ''];
    $withoutSuffix = $stored;
    unset($withoutSuffix['generational_suffix']);

    expect(PersonNameDisplayFormats::isReadyForDisplayFormatOptions($stored))->toBeTrue()
        ->and(PersonNameDisplayFormats::isReadyForDisplayFormatOptions($withoutMiddle))->toBeFalse()
        ->and(PersonNameDisplayFormats::isReadyForDisplayFormatOptions($withoutNickname))->toBeFalse()
        ->and(PersonNameDisplayFormats::isReadyForDisplayFormatOptions($withoutSuffix))->toBeFalse();
});

it('builds the same options, rows and partial text for a stored name with no flag keys', function (): void {
    $stored = personNameFullWithoutFlags();
    $full = personNameFull();

    expect(nameFormatsWithWarningsThrown(fn (): array => PersonNameDisplayFormats::buildOptions($stored)))->toBe(PersonNameDisplayFormats::buildOptions($full))
        ->and(nameFormatsWithWarningsThrown(fn (): array => PersonNameDisplayFormats::alternateNameDisplayExpansionRows($stored, null, true)))->toBe(PersonNameDisplayFormats::alternateNameDisplayExpansionRows($full, null, true))
        ->and(nameFormatsWithWarningsThrown(fn (): string => PersonNameDisplayFormats::buildPartialDisplayText($stored)))->toBe('Ann Marie "Annie" Lee Jr.')
        ->and(nameFormatsWithWarningsThrown(fn (): string => PersonNameDisplayFormats::buildPartialPhoneticText($stored)))->toBe('æn məˈɹiː | ˈæni | liː ˈʤunjər');
});

it('leaves out a switched-off part even when its text is still stored', function (array $switchedOff, array $removed): void {
    /** @var array<string, mixed> $typedSwitchedOff */
    $typedSwitchedOff = $switchedOff;
    /** @var array<string, mixed> $typedRemoved */
    $typedRemoved = $removed;

    expect(PersonNameDisplayFormats::buildOptions(PersonNameDisplayFormats::normalizeValue(array_replace(personNameFullWithoutFlags(), $typedSwitchedOff))))
        ->toBe(PersonNameDisplayFormats::buildOptions(PersonNameDisplayFormats::normalizeValue(array_replace(personNameFullWithoutFlags(), $typedRemoved))));
})->with([
    'the middle name' => [['no_middle_name' => true], ['no_middle_name' => true, 'middle_name' => []]],
    'the nickname' => [['no_nickname' => true], ['no_nickname' => true, 'nickname' => []]],
    'the suffix' => [['no_generational_suffix' => true], ['no_generational_suffix' => true, 'generational_suffix' => null]],
]);

it('trims padded name parts before building labels and partial text', function (): void {
    $padded = PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => ' Ann ', 'phonetic_text' => 'æn'],
        'middle_name' => ['text' => ' Marie ', 'phonetic_text' => 'məˈɹiː'],
        'nickname' => ['text' => ' Annie ', 'phonetic_text' => 'ˈæni'],
        'last_name' => ['text' => ' Lee ', 'phonetic_text' => 'liː'],
        'generational_suffix' => 'Jr.',
    ]);
    $paddedSuffix = $padded;
    $paddedSuffix['generational_suffix'] = ' Jr. ';

    expect(PersonNameDisplayFormats::buildOptions($padded))->toBe(PersonNameDisplayFormats::buildOptions(personNameFull()))
        ->and(PersonNameDisplayFormats::buildPartialDisplayText($paddedSuffix))->toBe('Ann Marie "Annie" Lee Jr.')
        ->and(PersonNameDisplayFormats::resolvedDisplayText(personNameFull(), ' middle_only@ns '))->toBe('Marie Lee');
});

it('keeps the earlier of two equally ranked patterns that produce the same label', function (): void {
    // A first name that matches the middle name makes middle-only and first-only collide
    $normalized = PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'Lee'],
        'middle_name' => ['text' => 'Lee'],
        'no_nickname' => true,
        'last_name' => ['text' => 'Ann'],
        'no_generational_suffix' => true,
    ]);

    expect(PersonNameDisplayFormats::allowedKeys($normalized))->toBe([
        'full_first_full_middle@ns',
        'full_first_middle_initial@ns',
        'first_initial_full_middle@ns',
        'middle_only@ns',
        'first_initial_middle_initial@ns',
    ]);
});

it('trims every character PHP trims, not only the ones the splitters drop', function (): void {
    expect(PersonNameDisplayFormats::initials("\0Ann"))->toBe('A.')
        ->and(PersonNameDisplayFormats::initialsIpaFromDisplayText("\0ann"))->toBe('eɪ')
        ->and(nameFormatsWithWarningsThrown(fn (): string => PersonNameDisplayFormats::initialsIpaFromDisplayText("\xff")))->toBe('');
});

it('joins only the non-blank parts, trimmed', function (): void {
    expect(PersonNameDisplayFormats::joinParts('Ann', '  ', null, ' Lee '))->toBe('Ann Lee');
});

it('falls back to the partial phonetic when the chosen pattern leaves out the only phonetic', function (): void {
    $normalized = PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'Ann'],
        'middle_name' => ['text' => 'Marie', 'phonetic_text' => 'məˈɹiː'],
        'no_nickname' => true,
        'last_name' => ['text' => 'Lee'],
        'no_generational_suffix' => true,
    ]);

    expect(PersonNameDisplayFormats::resolvedPhoneticText($normalized, 'full_first_only@ns'))->toBe('məˈɹiː');
});

it('gives a with-suffix row no phonetic when the name itself has none', function (): void {
    $normalized = PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => 'Ann'],
        'no_middle_name' => true,
        'no_nickname' => true,
        'last_name' => ['text' => 'Lee'],
        'generational_suffix' => 'Jr.',
    ]);

    expect(PersonNameDisplayFormats::alternateNameDisplayExpansionRows($normalized, null, true))->toBe([
        ['text' => 'Ann Lee Jr.', 'phonetic_text' => null],
        ['text' => 'Ann Lee', 'phonetic_text' => null],
    ]);
});

it('builds an empty phonetic for an alternate-name part with no phonetic', function (): void {
    expect(PersonNameDisplayFormats::normalizedFromAlternateNameShell([], [], [], [], null)['first_name'])
        ->toBe(['text' => '', 'phonetic_text' => '']);
});

/**
 * The options buildOptions() keeps for a name, as display-format key to label.
 *
 * @param  string  $first  The first name.
 * @param  string  $middle  The middle name.
 * @param  string  $nickname  The nickname.
 * @return array<string, string> Each kept option's label, keyed by its display-format key.
 */
function displayFormatLabelsByKey(string $first, string $middle, string $nickname): array
{
    $options = PersonNameDisplayFormats::buildOptions(PersonNameDisplayFormats::normalizeValue([
        'first_name' => ['text' => $first],
        'middle_name' => ['text' => $middle],
        'nickname' => ['text' => $nickname],
        'last_name' => ['text' => 'Hoover'],
        'no_middle_name' => false,
        'no_nickname' => false,
        'no_generational_suffix' => true,
    ]));

    return array_column($options, 'label', 'key');
}

it('keeps the higher-ranked pattern when two patterns render the same label', function (): void {
    // A first name that is already an initial makes the full-first patterns render the same
    // labels as the first-initial ones, so the pattern rank decides which key is kept
    expect(displayFormatLabelsByKey('J.', 'E', 'J'))->toBe([
        'first_initial_full_middle@ns' => 'J. E Hoover',
        'first_initial_full_middle_nickname@ns' => 'J. E "J" Hoover',
        'middle_only@ns' => 'E Hoover',
        'middle_only_nickname@ns' => 'E "J" Hoover',
        'first_initial_middle_initial@ns' => 'J. E. Hoover',
        'first_initial_middle_initial_nickname@ns' => 'J. E. "J" Hoover',
        'full_first_only@ns' => 'J. Hoover',
        'full_first_only_nickname@ns' => 'J. "J" Hoover',
        'nickname_only@ns' => 'J Hoover',
    ]);

    // A middle name equal to the first name makes the middle-only patterns collide with the
    // full-first-only ones, and the middle-only patterns are ranked higher
    expect(displayFormatLabelsByKey('J.', 'J.', 'J'))->toBe([
        'middle_only@ns' => 'J. Hoover',
        'middle_only_nickname@ns' => 'J. "J" Hoover',
        'first_initial_middle_initial@ns' => 'J. J. Hoover',
        'first_initial_middle_initial_nickname@ns' => 'J. J. "J" Hoover',
        'nickname_only@ns' => 'J Hoover',
    ]);
});

it('gives each English letter its letter-name pronunciation', function (string $letter, string $ipa): void {
    expect(PersonNameDisplayFormats::initialsIpaFromDisplayText($letter))->toBe($ipa);
})->with([
    ['A', 'eɪ'], ['B', 'bi'], ['C', 'si'], ['D', 'di'], ['E', 'i'], ['F', 'ɛf'], ['G', 'ʤi'],
    ['H', 'eɪʧ'], ['I', 'aɪ'], ['J', 'ʤeɪ'], ['K', 'keɪ'], ['L', 'ɛl'], ['M', 'ɛm'], ['N', 'ɛn'],
    ['O', 'oʊ'], ['P', 'pi'], ['Q', 'kju'], ['R', 'ɑr'], ['S', 'ɛs'], ['T', 'ti'], ['U', 'ju'],
    ['V', 'vi'], ['W', 'ˈdʌbəlju'], ['X', 'ɛks'], ['Y', 'waɪ'], ['Z', 'zi'],
]);

it('gives each generational suffix label its pronunciation', function (string $label, string $ipa): void {
    expect(PersonNameDisplayFormats::generationalSuffixPhoneticFromLabel($label))->toBe($ipa);
})->with([
    ['Jr.', 'ˈʤunjər'], ['Sr.', 'ˈsinjər'], ['II', 'ðə ˈsɛkənd'], ['III', 'ðə θɜrd'],
    ['IV', 'ðə fɔrθ'], ['V', 'ðə fɪfθ'], ['VI', 'ðə sɪksθ'], ['VII', 'ðə ˈsɛvənθ'],
    ['VIII', 'ðə eɪtθ'], ['IX', 'ðə naɪnθ'], ['X', 'ðə tɛnθ'],
]);
