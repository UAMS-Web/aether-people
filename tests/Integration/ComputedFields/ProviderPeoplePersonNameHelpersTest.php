<?php

declare(strict_types=1);

use Illuminate\Support\Collection as IlluminateCollection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;

uses(MockeryPHPUnitIntegration::class);

covers(ProviderPeoplePersonNameComputedValues::class);

/**
 * Pins the steps behind the person-name computeds: how a raw name field is read, merged, deduplicated
 * and joined. The steps are private, so each case calls one through reflection with the input that
 * decides its branch, and the public computeds are checked end to end beside them.
 *
 * @command vendor/bin/pest tests/Integration/ComputedFields/ProviderPeoplePersonNameHelpersTest.php
 */

/**
 * Call a private method of the name computeds.
 *
 * @param  string  $method  The method name.
 * @param  mixed  ...$arguments  The arguments to pass.
 */
function ppnCall(string $method, mixed ...$arguments): mixed
{
    $computeds = new ProviderPeoplePersonNameComputedValues;

    return new ReflectionMethod($computeds, $method)->invoke($computeds, ...$arguments);
}

/**
 * An entry whose stored values come from the map, with a default for a field it holds none of.
 *
 * @param  array<string, mixed>  $data  Field handle => stored value
 */
function ppnEntry(array $data): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->andReturnUsing(
        fn (string $key, mixed $default = null): mixed => array_key_exists($key, $data) ? $data[$key] : $default
    );

    return mustBe(Entry::class, $entry, 'the entry double');
}

/**
 * A complete `person_name` value for Jane Doe, with the given overrides.
 *
 * @param  array<array-key, mixed>  $overrides
 * @return array<string, mixed>
 */
function ppnPersonName(array $overrides = []): array
{
    return array_merge([
        'first_name' => ['text' => 'Jane', 'phonetic_text' => 'ʤeɪn'],
        'no_middle_name' => true,
        'middle_name' => ['text' => '', 'phonetic_text' => ''],
        'no_nickname' => true,
        'nickname' => ['text' => '', 'phonetic_text' => ''],
        'last_name' => ['text' => 'Doe', 'phonetic_text' => 'doʊ'],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => 'full_first_only@ns',
    ], $overrides);
}

/**
 * A credentials term the facade serves, with a title and a `get()` over its fields.
 *
 * @param  array<string, mixed>  $fields
 */
function ppnTerm(string $title, array $fields = []): LocalizedTerm
{
    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('offsetExists')->andReturnUsing(fn (mixed $key): bool => $key === 'title');
    $term->shouldReceive('offsetGet')->with('title')->andReturn($title);
    $term->shouldReceive('get')->andReturnUsing(fn (string $key, mixed $default = null): mixed => $fields[$key] ?? $default);

    return mustBe(LocalizedTerm::class, $term, 'the term double');
}

/**
 * Serve the given terms (keyed by slug) from `Term::find('credentials::<slug>')`.
 *
 * @param  array<array-key, LocalizedTerm>  $terms
 */
function ppnServeTerms(array $terms): void
{
    Term::shouldReceive('find')->andReturnUsing(
        fn (string $id): ?LocalizedTerm => $terms[substr($id, strlen('credentials::'))] ?? null
    );
}

it('reads the phonetic subfield of a name component', function (mixed $value, string $expected): void {
    expect(ppnCall('resolveNamePhoneticSubfieldValue', $value))->toBe($expected);
})->with([
    'null' => [null, ''],
    'empty string' => ['', ''],
    'legacy string' => ['ˈlɛsli', ''],
    'integer' => [5, ''],
    'array' => [['phonetic_text' => 'ˈlɛsli'], 'ˈlɛsli'],
    'collection' => [collect(['phonetic_text' => 'ˈlɛsli']), 'ˈlɛsli'],
    'nested collection' => [new IlluminateCollection(['phonetic_text' => 'x']), 'x'],
    'kept untrimmed' => [['phonetic_text' => ' x '], ' x '],
    'array without the key' => [['text' => 'a'], ''],
    'null phonetic' => [['phonetic_text' => null], ''],
    'empty phonetic' => [['phonetic_text' => ''], ''],
    'integer phonetic' => [['phonetic_text' => 5], '5'],
    'float phonetic' => [['phonetic_text' => 1.5], '1.5'],
    'array phonetic' => [['phonetic_text' => ['a']], ''],
]);

it('pairs a text with its trimmed phonetic, or null when it trims empty', function (string $phonetic, ?string $expected): void {
    expect(ppnCall('computeComputedTextPhoneticPair', 'Text', $phonetic))->toBe(['text' => 'Text', 'phonetic_text' => $expected]);
})->with([
    'empty' => ['', null],
    'blank' => ['   ', null],
    'padded' => ['  ipa ', 'ipa'],
    'plain' => ['ipa', 'ipa'],
]);

it('returns the legal name parts as stored pairs, null where a part is not a pair', function (): void {
    $pairs = (new ProviderPeoplePersonNameComputedValues)->getLegalNamePairs(ppnEntry([
        'person_name' => ppnPersonName(['middle_name' => 'not a pair']),
    ]));

    expect($pairs)->toBe([
        'first_name' => ['text' => 'Jane', 'phonetic_text' => 'ʤeɪn'],
        'middle_name' => null,
        'last_name' => ['text' => 'Doe', 'phonetic_text' => 'doʊ'],
    ]);
});

it('returns a null pair for every legal part when the entry has no name', function (): void {
    expect((new ProviderPeoplePersonNameComputedValues)->getLegalNamePairs(ppnEntry([])))
        ->toBe(['first_name' => null, 'middle_name' => null, 'last_name' => null]);
});

it('reads the primary display format key trimmed, and null when none is usable', function (mixed $personName, ?string $expected): void {
    expect(ppnCall('resolvePrimaryPersonNameDisplayFormatKey', ppnEntry(['person_name' => $personName])))->toBe($expected);
})->with([
    'no name' => [null, null],
    'non-string format' => [ppnPersonName(['display_format' => 5]), null],
    'null format' => [ppnPersonName(['display_format' => null]), null],
    'blank format' => [ppnPersonName(['display_format' => '   ']), null],
    'empty format' => [ppnPersonName(['display_format' => '']), null],
    'padded format' => [ppnPersonName(['display_format' => ' full_first_only@ns ']), 'full_first_only@ns'],
]);

it('collects the primary name and each embedded alternate name, tagged by source', function (): void {
    $embedded = ppnPersonName(['first_name' => ['text' => 'Janet', 'phonetic_text' => '']]);

    $entry = ppnEntry([
        'person_name' => ppnPersonName(),
        'alternate_name' => collect([
            ['type' => 'alternate_name_set', 'person_name' => $embedded],
            ['type' => 'nickname_set', 'person_name' => ppnPersonName()],
            'not a set',
            collect(['type' => 'alternate_name_set', 'person_name' => collect($embedded)]),
            ['type' => 'alternate_name_set', 'person_name' => 'not an array'],
            ['type' => 'alternate_name_set'],
            ['person_name' => ppnPersonName()],
        ]),
    ]);

    $collected = ppnCall('collectRawPersonNameFieldValuesFromEntry', $entry);

    expect($collected)->toBe([
        ['raw' => ppnPersonName(), 'from_primary' => true],
        ['raw' => $embedded, 'from_primary' => false],
        ['raw' => $embedded, 'from_primary' => false],
    ]);
});

it('collects only the primary name when the alternate names are not a list', function (mixed $alternate): void {
    expect(ppnCall('collectRawPersonNameFieldValuesFromEntry', ppnEntry(['person_name' => ppnPersonName(), 'alternate_name' => $alternate])))
        ->toBe([['raw' => ppnPersonName(), 'from_primary' => true]]);
})->with([
    'string' => ['text'],
    'null' => [null],
    'empty list' => [[]],
]);

it('collects no raw names for an entry with neither a name nor alternates', function (): void {
    expect(ppnCall('collectRawPersonNameFieldValuesFromEntry', ppnEntry([])))->toBe([]);
});

it('collects trimmed free-text alternate names and skips every other set', function (): void {
    $entry = ppnEntry(['alternate_name' => collect([
        ['type' => 'free_text', 'text' => '  Dr. J. Doe  '],
        ['type' => 'free_text', 'text' => '   '],
        ['type' => 'free_text', 'text' => ''],
        ['type' => 'free_text', 'text' => 5],
        ['type' => 'free_text'],
        ['type' => 'nickname_set', 'text' => 'Wrong type'],
        ['text' => 'No type'],
        'not a set',
        collect(['type' => 'free_text', 'text' => 'From a collection']),
    ])]);

    expect(ppnCall('collectFreeTextAlternateNamesFromEntry', $entry))->toBe(['Dr. J. Doe', 'From a collection']);
});

it('collects no free-text names when the alternate names are not a list', function (mixed $alternate): void {
    expect(ppnCall('collectFreeTextAlternateNamesFromEntry', ppnEntry(['alternate_name' => $alternate])))->toBe([]);
})->with([
    'string' => ['text'],
    'empty list' => [[]],
]);

it('merges identical name shells and omits the stored format only for a primary-only shell', function (): void {
    $same = ['first_name' => ['text' => 'A']];
    $primaryOnly = ['first_name' => ['text' => 'B']];
    $alternateOnly = ['first_name' => ['text' => 'C']];
    $both = ['first_name' => ['text' => 'D']];
    $primaryTwice = ['first_name' => ['text' => 'E']];

    $rows = ppnCall('dedupePersonNameShellsWithSourceFlags', [
        ['shell' => $primaryOnly, 'from_primary' => true],
        ['shell' => $alternateOnly, 'from_primary' => false],
        ['shell' => $both, 'from_primary' => true],
        ['shell' => $both, 'from_primary' => false],
        ['shell' => $primaryTwice, 'from_primary' => true],
        ['shell' => $primaryTwice, 'from_primary' => true],
        ['shell' => $same, 'from_primary' => false],
        ['shell' => $same, 'from_primary' => false],
    ]);

    expect($rows)->toBe([
        ['shell' => $primaryOnly, 'omit_stored_display_format_variation' => true],
        ['shell' => $alternateOnly, 'omit_stored_display_format_variation' => false],
        ['shell' => $both, 'omit_stored_display_format_variation' => false],
        ['shell' => $primaryTwice, 'omit_stored_display_format_variation' => true],
        ['shell' => $same, 'omit_stored_display_format_variation' => false],
    ]);
});

it('skips no display format unless the shell is primary-only and the nickname variant is the primary one', function (): void {
    $entry = ppnEntry(['person_name' => ppnPersonName([
        'no_nickname' => false,
        'nickname' => ['text' => 'Janie', 'phonetic_text' => ''],
        'display_format' => 'nickname_only@ns',
    ])]);

    $matching = ['text' => 'Janie', 'phonetic_text' => null];
    $other = ['text' => 'Jay', 'phonetic_text' => null];

    expect(ppnCall('resolveSkipDisplayFormatKeyForAlternateExpansion', $entry, true, $matching))->toBe('nickname_only@ns')
        ->and(ppnCall('resolveSkipDisplayFormatKeyForAlternateExpansion', $entry, false, $matching))->toBeNull()
        ->and(ppnCall('resolveSkipDisplayFormatKeyForAlternateExpansion', $entry, true, $other))->toBeNull();
});

it('matches a nickname variant against the primary nickname', function (array $primary, mixed $variantText, bool $expected): void {
    $entry = ppnEntry(['person_name' => ppnPersonName($primary)]);

    expect(ppnCall('nicknameVariantMatchesPrimaryPersonNameNickname', $entry, ['text' => $variantText, 'phonetic_text' => null]))->toBe($expected);
})->with([
    'no primary nickname, no variant' => [['no_nickname' => true], null, true],
    'no primary nickname, empty variant' => [['no_nickname' => true], '', true],
    'no primary nickname, blank variant' => [['no_nickname' => true], '  ', false],
    'no primary nickname, a variant' => [['no_nickname' => true], 'Bob', false],
    'flagged off but a stored text' => [['no_nickname' => true, 'nickname' => ['text' => 'Janie', 'phonetic_text' => '']], null, true],
    'empty primary text, no variant' => [['no_nickname' => false], null, true],
    'empty primary text, a blank variant' => [['no_nickname' => false], '  ', false],
    'empty primary text, a variant' => [['no_nickname' => false], 'Bob', false],
    'blank primary text, no variant' => [['no_nickname' => false, 'nickname' => ['text' => '  ', 'phonetic_text' => '']], null, true],
    'primary nickname, no variant' => [['no_nickname' => false, 'nickname' => ['text' => 'Janie', 'phonetic_text' => '']], null, false],
    'primary nickname, empty variant' => [['no_nickname' => false, 'nickname' => ['text' => 'Janie', 'phonetic_text' => '']], '', false],
    'primary nickname, same variant' => [['no_nickname' => false, 'nickname' => ['text' => 'Janie', 'phonetic_text' => '']], 'Janie', true],
    'primary nickname, padded variant' => [['no_nickname' => false, 'nickname' => ['text' => ' Janie ', 'phonetic_text' => '']], ' Janie', true],
    'primary nickname, other variant' => [['no_nickname' => false, 'nickname' => ['text' => 'Janie', 'phonetic_text' => '']], 'Jay', false],
]);

it('strips the fields an alternate name does not carry', function (): void {
    $raw = ppnPersonName([
        'nickname' => ['text' => 'J', 'phonetic_text' => ''],
        'generational_suffix' => 'Jr.',
        'extra' => 'kept',
    ]);

    expect(ppnCall('stripPersonNameFieldForAlternateExpansion', $raw))->toBe([
        'first_name' => ['text' => 'Jane', 'phonetic_text' => 'ʤeɪn'],
        'middle_name' => ['text' => '', 'phonetic_text' => ''],
        'last_name' => ['text' => 'Doe', 'phonetic_text' => 'doʊ'],
        'generational_suffix' => 'Jr.',
        'extra' => 'kept',
    ]);
});

it('applies the merged phonetics to the first and middle names by trimmed text', function (array $shell, array $expected): void {
    $merged = ppnCall('applyMergedNamePartPhonetics', $shell, ['Jane' => 'MERGED-FIRST', '5' => 'FIVE', 'Rae' => 'FIRST-RAE'], ['Rae' => 'MERGED-MIDDLE', 'Jane' => 'MIDDLE-JANE', '7' => 'SEVEN']);

    expect($merged)->toBe($expected);
})->with([
    'both merged by trimmed text' => [
        ['first_name' => ['text' => ' Jane ', 'phonetic_text' => 'old'], 'middle_name' => ['text' => 'Rae '], 'last_name' => ['text' => 'Doe']],
        ['first_name' => ['text' => ' Jane ', 'phonetic_text' => 'MERGED-FIRST'], 'middle_name' => ['text' => 'Rae ', 'phonetic_text' => 'MERGED-MIDDLE'], 'last_name' => ['text' => 'Doe']],
    ],
    'no match leaves the pair alone' => [
        ['first_name' => ['text' => 'Other', 'phonetic_text' => 'old'], 'middle_name' => ['text' => 'Other'], 'last_name' => ['text' => 'Doe']],
        ['first_name' => ['text' => 'Other', 'phonetic_text' => 'old'], 'middle_name' => ['text' => 'Other'], 'last_name' => ['text' => 'Doe']],
    ],
    'first name is read from its own map, middle from its own' => [
        ['first_name' => ['text' => 'Rae'], 'middle_name' => ['text' => 'Jane'], 'last_name' => ['text' => 'Doe']],
        ['first_name' => ['text' => 'Rae', 'phonetic_text' => 'FIRST-RAE'], 'middle_name' => ['text' => 'Jane', 'phonetic_text' => 'MIDDLE-JANE'], 'last_name' => ['text' => 'Doe']],
    ],
    'numeric text is read as a string' => [
        ['first_name' => ['text' => 5], 'middle_name' => ['text' => 7], 'last_name' => ['text' => 'Doe']],
        ['first_name' => ['text' => 5, 'phonetic_text' => 'FIVE'], 'middle_name' => ['text' => 7, 'phonetic_text' => 'SEVEN'], 'last_name' => ['text' => 'Doe']],
    ],
    'non-scalar text matches nothing' => [
        ['first_name' => ['text' => ['x']], 'middle_name' => ['text' => null], 'last_name' => ['text' => 'Doe']],
        ['first_name' => ['text' => ['x']], 'middle_name' => ['text' => null], 'last_name' => ['text' => 'Doe']],
    ],
    'missing and non-array parts become empty' => [
        ['first_name' => 'text', 'last_name' => 5],
        ['first_name' => [], 'last_name' => [], 'middle_name' => []],
    ],
]);

it('requires a first and a last name with text', function (array $shell, bool $expected): void {
    expect(ppnCall('personNameShellHasRequiredLegalNames', $shell))->toBe($expected);
})->with([
    'both' => [['first_name' => ['text' => 'A'], 'last_name' => ['text' => 'B']], true],
    'padded' => [['first_name' => ['text' => ' A '], 'last_name' => ['text' => ' B ']], true],
    'numeric' => [['first_name' => ['text' => 1], 'last_name' => ['text' => 2]], true],
    'no first' => [['last_name' => ['text' => 'B']], false],
    'no last' => [['first_name' => ['text' => 'A']], false],
    'blank first' => [['first_name' => ['text' => '  '], 'last_name' => ['text' => 'B']], false],
    'blank last' => [['first_name' => ['text' => 'A'], 'last_name' => ['text' => '  ']], false],
    'null first text' => [['first_name' => ['text' => null], 'last_name' => ['text' => 'B']], false],
    'array last text' => [['first_name' => ['text' => 'A'], 'last_name' => ['text' => ['x']]], false],
    'first not a pair' => [['first_name' => 'A', 'last_name' => ['text' => 'B']], false],
    'last not a pair' => [['first_name' => ['text' => 'A'], 'last_name' => 'B'], false],
    'neither' => [[], false],
]);

it('builds the combined alternate name from a shell and a nickname variant', function (): void {
    $shell = [
        'first_name' => ['text' => 'A'],
        'middle_name' => ['text' => 'M'],
        'last_name' => ['text' => 'Z'],
        'generational_suffix' => 'Jr.',
    ];

    expect(ppnCall('buildCombinedAlternateNameValueObject', $shell, ['text' => 'Nick', 'phonetic_text' => 'nɪk']))->toBe([
        'first_name' => ['text' => 'A'],
        'middle_name' => ['text' => 'M'],
        'nickname' => ['text' => 'Nick', 'phonetic_text' => 'nɪk'],
        'last_name' => ['text' => 'Z'],
        'generational_suffix' => 'Jr.',
    ])->and(ppnCall('buildCombinedAlternateNameValueObject', ['first_name' => 'x', 'middle_name' => 'x', 'last_name' => 'x'], ['text' => null, 'phonetic_text' => null]))->toBe([
        'first_name' => [],
        'middle_name' => [],
        'nickname' => ['text' => null, 'phonetic_text' => null],
        'last_name' => [],
        'generational_suffix' => null,
    ]);
});

it('drops every phonetic of an alternate name when any named part lacks one', function (array $combined, array $expected): void {
    expect(ppnCall('applyPhoneticCoherenceToCombinedAlternateName', $combined))->toBe($expected);
})->with([
    'no named part' => [
        ['first_name' => ['text' => '', 'phonetic_text' => 'x'], 'last_name' => ['text' => '  ', 'phonetic_text' => 'y']],
        ['first_name' => ['text' => '', 'phonetic_text' => 'x'], 'last_name' => ['text' => '  ', 'phonetic_text' => 'y']],
    ],
    'every named part pronounced' => [
        ['first_name' => ['text' => 'A', 'phonetic_text' => 'a'], 'last_name' => ['text' => 'B', 'phonetic_text' => 'b'], 'middle_name' => ['text' => '', 'phonetic_text' => null]],
        ['first_name' => ['text' => 'A', 'phonetic_text' => 'a'], 'last_name' => ['text' => 'B', 'phonetic_text' => 'b'], 'middle_name' => ['text' => '', 'phonetic_text' => null]],
    ],
    'the last part lacks one' => [
        ['first_name' => ['text' => 'A', 'phonetic_text' => 'a'], 'middle_name' => ['text' => '', 'phonetic_text' => 'm'], 'last_name' => ['text' => 'B', 'phonetic_text' => null]],
        ['first_name' => ['text' => 'A', 'phonetic_text' => null], 'middle_name' => ['text' => '', 'phonetic_text' => 'm'], 'last_name' => ['text' => 'B', 'phonetic_text' => null]],
    ],
    'the first part lacks one' => [
        ['first_name' => ['text' => 'A'], 'nickname' => ['text' => 'N', 'phonetic_text' => 'n'], 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']],
        ['first_name' => ['text' => 'A', 'phonetic_text' => null], 'nickname' => ['text' => 'N', 'phonetic_text' => null], 'last_name' => ['text' => 'B', 'phonetic_text' => null]],
    ],
    'a blank phonetic counts as none' => [
        ['first_name' => ['text' => 'A', 'phonetic_text' => '  '], 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']],
        ['first_name' => ['text' => 'A', 'phonetic_text' => null], 'last_name' => ['text' => 'B', 'phonetic_text' => null]],
    ],
    'an empty phonetic counts as none' => [
        ['first_name' => ['text' => 'A', 'phonetic_text' => ''], 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']],
        ['first_name' => ['text' => 'A', 'phonetic_text' => null], 'last_name' => ['text' => 'B', 'phonetic_text' => null]],
    ],
    'numeric phonetics count' => [
        ['first_name' => ['text' => 'A', 'phonetic_text' => 1], 'last_name' => ['text' => 'B', 'phonetic_text' => 2]],
        ['first_name' => ['text' => 'A', 'phonetic_text' => 1], 'last_name' => ['text' => 'B', 'phonetic_text' => 2]],
    ],
    'an array phonetic counts as none' => [
        ['first_name' => ['text' => 'A', 'phonetic_text' => ['x']], 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']],
        ['first_name' => ['text' => 'A', 'phonetic_text' => null], 'last_name' => ['text' => 'B', 'phonetic_text' => null]],
    ],
    'a part that is not a pair is ignored' => [
        ['first_name' => ['text' => 'A', 'phonetic_text' => 'a'], 'middle_name' => 'text', 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']],
        ['first_name' => ['text' => 'A', 'phonetic_text' => 'a'], 'middle_name' => 'text', 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']],
    ],
    'numeric text counts as named' => [
        ['first_name' => ['text' => 5, 'phonetic_text' => null], 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']],
        ['first_name' => ['text' => 5, 'phonetic_text' => null], 'last_name' => ['text' => 'B', 'phonetic_text' => null]],
    ],
]);

it('supports phonetic variations only when every named part is pronounced', function (array $combined, bool $expected): void {
    expect(ppnCall('combinedAlternateNameSupportsPhoneticVariations', $combined))->toBe($expected);
})->with([
    'every named part' => [['first_name' => ['text' => 'A', 'phonetic_text' => 'a'], 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']], true],
    'unnamed part without a phonetic' => [['first_name' => ['text' => 'A', 'phonetic_text' => 'a'], 'middle_name' => ['text' => '', 'phonetic_text' => null], 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']], true],
    'blank text part without a phonetic' => [['first_name' => ['text' => 'A', 'phonetic_text' => 'a'], 'middle_name' => ['text' => '  '], 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']], true],
    'not a pair part' => [['first_name' => ['text' => 'A', 'phonetic_text' => 'a'], 'middle_name' => 'x', 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']], true],
    'missing phonetic' => [['first_name' => ['text' => 'A'], 'last_name' => ['text' => 'B', 'phonetic_text' => 'b']], false],
    'null phonetic' => [['first_name' => ['text' => 'A', 'phonetic_text' => 'a'], 'last_name' => ['text' => 'B', 'phonetic_text' => null]], false],
    'empty phonetic' => [['first_name' => ['text' => 'A', 'phonetic_text' => 'a'], 'nickname' => ['text' => 'N', 'phonetic_text' => '']], false],
    'blank phonetic' => [['first_name' => ['text' => 'A', 'phonetic_text' => '   ']], false],
    'numeric phonetic' => [['first_name' => ['text' => 'A', 'phonetic_text' => 3]], true],
    'array phonetic' => [['first_name' => ['text' => 'A', 'phonetic_text' => ['x']]], false],
    'numeric text' => [['first_name' => ['text' => 4, 'phonetic_text' => null]], false],
    'nothing named' => [[], true],
]);

it('drops a repeated alternate name row, comparing text and phonetic together', function (): void {
    $rows = [
        ['text' => 'A B', 'phonetic_text' => 'ab'],
        ['text' => 'A B', 'phonetic_text' => 'ab'],
        ['text' => 'A B', 'phonetic_text' => 'other'],
        ['text' => 'C', 'phonetic_text' => null],
        ['text' => 'C', 'phonetic_text' => null],
        ['text' => 'D', 'phonetic_text' => ''],
        ['text' => 'D', 'phonetic_text' => null],
    ];

    expect(ppnCall('dedupeAlternateNameDisplayRows', $rows))->toBe([
        ['text' => 'A B', 'phonetic_text' => 'ab'],
        ['text' => 'A B', 'phonetic_text' => 'other'],
        ['text' => 'C', 'phonetic_text' => null],
        ['text' => 'D', 'phonetic_text' => ''],
    ]);
});

it('collects the raw nicknames of the name and of each nickname set', function (): void {
    $entry = ppnEntry([
        'person_name' => ppnPersonName(['nickname' => 'Janie']),
        'alternate_name' => collect([
            ['type' => 'nickname_set', 'nickname' => ['text' => 'Jay', 'phonetic_text' => 'ʤeɪ']],
            ['type' => 'nickname_set'],
            ['type' => 'alternate_name_set', 'nickname' => 'Wrong type'],
            ['nickname' => 'No type'],
            'not a set',
            collect(['type' => 'nickname_set', 'nickname' => 'From a collection']),
        ]),
    ]);

    expect(ppnCall('collectRawNicknameValuesFromEntry', $entry))->toBe([
        'Janie',
        ['text' => 'Jay', 'phonetic_text' => 'ʤeɪ'],
        'From a collection',
    ]);
});

it('collects no raw nickname from a name without one or from alternates that are not a list', function (): void {
    expect(ppnCall('collectRawNicknameValuesFromEntry', ppnEntry(['person_name' => ppnPersonName(), 'alternate_name' => 'text'])))->toBe([['text' => '', 'phonetic_text' => '']])
        ->and(ppnCall('collectRawNicknameValuesFromEntry', ppnEntry([])))->toBe([]);
});

it('normalizes one raw nickname', function (mixed $raw, ?array $expected): void {
    expect(ppnCall('normalizeOneNicknameRawValue', $raw))->toBe($expected);
})->with([
    'string' => [' Bob ', ['text' => 'Bob', 'phonetic_text' => null]],
    'empty string' => ['', null],
    'blank string' => ['   ', null],
    'integer' => [5, null],
    'null' => [null, null],
    'array with text' => [['text' => 'Bob'], ['text' => 'Bob', 'phonetic_text' => null]],
    'array with text and phonetic' => [['text' => ' Bob ', 'phonetic_text' => 'bɑb'], ['text' => 'Bob', 'phonetic_text' => 'bɑb']],
    'collection' => [collect(['text' => 'Bob', 'phonetic_text' => 'bɑb']), ['text' => 'Bob', 'phonetic_text' => 'bɑb']],
    'phonetic only' => [['phonetic_text' => 'bɑb'], null],
    'neither key' => [['other' => 'x'], null],
    'empty array' => [[], null],
    'empty text' => [['text' => '', 'phonetic_text' => 'bɑb'], null],
    'blank text' => [['text' => '  ', 'phonetic_text' => 'bɑb'], null],
    'null text' => [['text' => null], null],
    'numeric text' => [['text' => 5], ['text' => '5', 'phonetic_text' => null]],
    'array text' => [['text' => ['x']], null],
    'null phonetic' => [['text' => 'Bob', 'phonetic_text' => null], ['text' => 'Bob', 'phonetic_text' => null]],
    'empty phonetic' => [['text' => 'Bob', 'phonetic_text' => ''], ['text' => 'Bob', 'phonetic_text' => null]],
    'blank phonetic' => [['text' => 'Bob', 'phonetic_text' => '   '], ['text' => 'Bob', 'phonetic_text' => null]],
    'padded phonetic kept as stored' => [['text' => 'Bob', 'phonetic_text' => ' bɑb '], ['text' => 'Bob', 'phonetic_text' => ' bɑb ']],
    'numeric phonetic' => [['text' => 'Bob', 'phonetic_text' => 5], ['text' => 'Bob', 'phonetic_text' => '5']],
    'array phonetic' => [['text' => 'Bob', 'phonetic_text' => ['x']], ['text' => 'Bob', 'phonetic_text' => null]],
]);

it('keeps one nickname per text, in first-seen order, with its first non-empty phonetic', function (): void {
    $items = [
        ['text' => 'Bob', 'phonetic_text' => null],
        ['text' => 'Al', 'phonetic_text' => '   '],
        ['text' => 'Bob', 'phonetic_text' => ' bɑb '],
        ['text' => 'Bob', 'phonetic_text' => 'other'],
        ['text' => 'Cy', 'phonetic_text' => ''],
        ['text' => 'Al', 'phonetic_text' => null],
    ];

    expect(ppnCall('dedupeNicknamesByTextPreservingOrder', $items))->toBe([
        ['text' => 'Bob', 'phonetic_text' => 'bɑb'],
        ['text' => 'Al', 'phonetic_text' => null],
        ['text' => 'Cy', 'phonetic_text' => null],
    ]);
});

it('lists nicknames from the name and the nickname sets, deduplicated', function (): void {
    $entry = ppnEntry([
        'person_name' => ppnPersonName(),
        'nickname' => ['text' => 'Janie', 'phonetic_text' => 'ʤeɪni'],
        'alternate_name' => [
            ['type' => 'nickname_set', 'nickname' => ['text' => 'Janie', 'phonetic_text' => 'other']],
            ['type' => 'nickname_set', 'nickname' => ['text' => 'Jay']],
            ['type' => 'nickname_set', 'nickname' => ['text' => '', 'phonetic_text' => 'x']],
        ],
    ]);

    expect((new ProviderPeoplePersonNameComputedValues)->getNicknameList($entry))->toBe([
        ['text' => 'Janie', 'phonetic_text' => 'ʤeɪni'],
        ['text' => 'Jay', 'phonetic_text' => null],
    ]);
});

it('reads the doctor prefix from an md or do credential only', function (mixed $credentials, string $expected): void {
    expect(ppnCall('getPrefix', ppnEntry(['credentials' => $credentials])))->toBe($expected);
})->with([
    'md' => [['md'], 'Dr.'],
    'do' => [['rn', 'do'], 'Dr.'],
    'a single slug' => ['md', 'Dr.'],
    'a collection' => [collect(['do']), 'Dr.'],
    'neither' => [['rn', 'np'], ''],
    'longer slug' => [['mdx', 'xdo'], ''],
    'none' => [[], ''],
]);

it('builds the prefix pair with the doctor honorific only when there is a prefix', function (): void {
    $doctor = (new ProviderPeoplePersonNameComputedValues)->getPrefixValue(ppnEntry(['credentials' => ['md']]));
    $none = (new ProviderPeoplePersonNameComputedValues)->getPrefixValue(ppnEntry(['credentials' => []]));

    expect($doctor)->toBe(['text' => 'Dr.', 'phonetic_text' => 'ˈdɑktər'])
        ->and($none)->toBe(['text' => '', 'phonetic_text' => null]);
});

it('adds the doctor prefix to the display and full names, with and without a phonetic', function (): void {
    $names = new ProviderPeoplePersonNameComputedValues;
    $doctor = ppnEntry(['person_name' => ppnPersonName(), 'credentials' => ['md']]);
    $plain = ppnEntry(['person_name' => ppnPersonName(), 'credentials' => ['rn']]);
    $unpronounced = ppnEntry(['person_name' => ppnPersonName([
        'first_name' => ['text' => 'Jane', 'phonetic_text' => ''],
        'last_name' => ['text' => 'Doe', 'phonetic_text' => ''],
    ]), 'credentials' => ['md']]);
    $noName = ppnEntry(['credentials' => ['md']]);

    expect($names->getDisplayNameFullWithPrefix($doctor))->toBe(['text' => 'Dr. Jane Doe', 'phonetic_text' => 'ˈdɑktər ʤeɪn doʊ'])
        ->and($names->getDisplayNameFullWithPrefix($plain))->toBe(['text' => 'Jane Doe', 'phonetic_text' => 'ʤeɪn doʊ'])
        ->and($names->getDisplayNameFullWithPrefix($unpronounced))->toBe(['text' => 'Dr. Jane Doe', 'phonetic_text' => null])
        ->and($names->getDisplayNameFullWithPrefix($noName))->toBe(['text' => '', 'phonetic_text' => null])
        ->and($names->getFullNameWithPrefix($doctor))->toBe(['text' => 'Dr. Jane Doe', 'phonetic_text' => 'ˈdɑktər ʤeɪn doʊ'])
        ->and($names->getFullNameWithPrefix($plain))->toBe(['text' => 'Jane Doe', 'phonetic_text' => 'ʤeɪn doʊ'])
        ->and($names->getFullNameWithPrefix($unpronounced))->toBe(['text' => 'Dr. Jane Doe', 'phonetic_text' => null])
        ->and($names->getFullNameWithPrefix($noName))->toBe(['text' => '', 'phonetic_text' => null]);
});

it('builds the short prefixed name from the prefix and the last name', function (): void {
    $names = new ProviderPeoplePersonNameComputedValues;
    $doctor = ppnEntry(['person_name' => ppnPersonName(), 'credentials' => ['md']]);
    $unpronounced = ppnEntry(['person_name' => ppnPersonName(['last_name' => ['text' => ' Doe ', 'phonetic_text' => '  ']]), 'credentials' => ['do']]);
    $noLast = ppnEntry(['person_name' => ppnPersonName(['last_name' => ['text' => '', 'phonetic_text' => 'doʊ']]), 'credentials' => ['md']]);
    $incomplete = ppnEntry(['person_name' => ['first_name' => ['text' => 'Jane']], 'credentials' => ['md']]);
    $plain = ppnEntry(['person_name' => ppnPersonName(), 'credentials' => ['rn']]);

    expect($names->getDisplayNameShortWithPrefix($doctor))->toBe(['text' => 'Dr. Doe', 'phonetic_text' => 'ˈdɑktər doʊ'])
        ->and($names->getDisplayNameShortWithPrefix($unpronounced))->toBe(['text' => 'Dr. Doe', 'phonetic_text' => null])
        ->and($names->getDisplayNameShortWithPrefix($noLast))->toBe(['text' => '', 'phonetic_text' => null])
        ->and($names->getDisplayNameShortWithPrefix($incomplete))->toBe(['text' => '', 'phonetic_text' => null])
        ->and($names->getDisplayNameShortWithPrefix($plain))->toBe(['text' => 'Jane Doe', 'phonetic_text' => 'ʤeɪn doʊ']);
});

it('appends the credential titles and abbreviation phonetics to the names', function (): void {
    ppnServeTerms([
        'md' => ppnTerm('M.D.', ['abbreviation_phonetic_text' => ' ɛm diː ']),
        'rn' => ppnTerm('RN', ['abbreviation_phonetic_text' => ['phonetic_text' => 'ɑr ɛn']]),
        'np' => ppnTerm('NP'),
        'bare' => ppnTerm('Bare', ['abbreviation_phonetic_text' => 'ˈbɛr']),
    ]);

    $names = new ProviderPeoplePersonNameComputedValues;
    $entry = ppnEntry(['person_name' => ppnPersonName(), 'credentials' => ['md', 'missing', 'rn', 'np']]);

    expect($names->getFullNameWithCredentials($entry))->toBe(['text' => 'Jane Doe, M.D., RN, NP', 'phonetic_text' => 'ʤeɪn doʊ.ɛm diː.ɑr ɛn'])
        ->and($names->getDisplayNameFullWithCredentials($entry))->toBe(['text' => 'Jane Doe, M.D., RN, NP', 'phonetic_text' => 'ʤeɪn doʊ.ɛm diː.ɑr ɛn'])
        ->and($names->getFullNameWithCredentials(ppnEntry(['person_name' => ppnPersonName(), 'credentials' => ['missing']])))->toBe(['text' => 'Jane Doe', 'phonetic_text' => 'ʤeɪn doʊ'])
        ->and($names->getFullNameWithCredentials(ppnEntry(['person_name' => ppnPersonName(), 'credentials' => []])))->toBe(['text' => 'Jane Doe', 'phonetic_text' => 'ʤeɪn doʊ'])
        ->and($names->getFullNameWithCredentials(ppnEntry(['person_name' => ppnPersonName()])))->toBe(['text' => 'Jane Doe', 'phonetic_text' => 'ʤeɪn doʊ'])
        ->and($names->getFullNameWithCredentials(ppnEntry(['credentials' => ['md']])))->toBe(['text' => '', 'phonetic_text' => null])
        ->and($names->getDisplayNameFullWithCredentials(ppnEntry(['credentials' => ['md']])))->toBe(['text' => '', 'phonetic_text' => null])
        ->and($names->getFullNameWithCredentials(ppnEntry(['person_name' => ppnPersonName(['first_name' => ['text' => 'Jane', 'phonetic_text' => ''], 'last_name' => ['text' => 'Doe', 'phonetic_text' => '']]), 'credentials' => ['md']])))
        ->toBe(['text' => 'Jane Doe, M.D.', 'phonetic_text' => null]);
});

it('reads the abbreviation phonetic segments of the credentials', function (mixed $credentials, array $expected): void {
    ppnServeTerms([
        'md' => ppnTerm('M.D.', ['abbreviation_phonetic_text' => ' ɛm diː ']),
        'rn' => ppnTerm('RN', ['abbreviation_phonetic_text' => ['phonetic_text' => 'ɑr ɛn']]),
        'np' => ppnTerm('NP'),
        '5' => ppnTerm('Five', ['abbreviation_phonetic_text' => 'faɪv']),
    ]);

    expect(ppnCall('credentialAbbreviationPhoneticSegments', $credentials))->toBe($expected);
})->with([
    'null' => [null, []],
    'empty string' => ['', []],
    'empty list' => [[], []],
    'a slug' => ['md', ['ɛm diː']],
    'a list' => [['md', 'rn'], ['ɛm diː', 'ɑr ɛn']],
    'a term without a phonetic' => [['np'], []],
    'an unknown term' => [['missing', 'md'], ['ɛm diː']],
    'an integer slug' => [[5], ['faɪv']],
    'an unusable slug' => [[1.5, null, ['md'], true, 'md'], ['ɛm diː']],
]);

it('reads a credential term abbreviation phonetic from any object that can get a field', function (string $kind, string $expected): void {
    $term = match ($kind) {
        'string' => ppnTerm('X', ['abbreviation_phonetic_text' => ' ipa ']),
        'group' => ppnTerm('X', ['abbreviation_phonetic_text' => ['phonetic_text' => 'grp']]),
        'nothing' => ppnTerm('X'),
        'no get' => new stdClass,
        'string value' => 'text',
        default => null,
    };

    expect(ppnCall('resolveCredentialTermAbbreviationPhonetic', $term))->toBe($expected);
})->with([
    'a term with a string' => ['string', 'ipa'],
    'a term with a group' => ['group', 'grp'],
    'a term with nothing' => ['nothing', ''],
    'an object without get' => ['no get', ''],
    'a string' => ['string value', ''],
    'null' => ['null', ''],
]);

it('joins the base phonetic and the credential phonetics with periods', function (): void {
    expect(ppnCall('joinPhoneticWithCredentialAbbreviations', 'base', ['a', 'b']))->toBe('base.a.b')
        ->and(ppnCall('phoneticWithCredentialAbbreviationSuffix', '', ['md']))->toBe('')
        ->and(ppnCall('phoneticWithCredentialAbbreviationSuffix', 'base', null))->toBe('base');
});

it('titles a name with the credentials that resolve, and leaves it bare otherwise', function (mixed $credentials, string $expected): void {
    ppnServeTerms(['md' => ppnTerm('M.D.'), 'rn' => ppnTerm('RN')]);

    expect(ppnCall('displayNameWithCredentialTitles', 'Jane Doe', $credentials))->toBe($expected);
})->with([
    'none' => [null, 'Jane Doe'],
    'empty list' => [[], 'Jane Doe'],
    'empty string' => ['', 'Jane Doe'],
    'unknown' => [['missing'], 'Jane Doe'],
    'one' => [['md'], 'Jane Doe, M.D.'],
    'two with an unknown between' => [['md', 'missing', 'rn'], 'Jane Doe, M.D., RN'],
]);

it('reads a scalar as trimmed text and anything else as empty', function (mixed $value, string $expected): void {
    expect(ppnCall('trimmedScalarText', $value))->toBe($expected);
})->with([
    'padded string' => ['  x  ', 'x'],
    'blank string' => ['   ', ''],
    'integer' => [5, '5'],
    'float' => [1.5, '1.5'],
    'true' => [true, '1'],
    'false' => [false, ''],
    'null' => [null, ''],
    'array' => [['x'], ''],
    'object' => [new stdClass, ''],
]);

it('builds the display and full names from an incomplete name as empty even when phonetics are stored', function (): void {
    $names = new ProviderPeoplePersonNameComputedValues;
    $incomplete = ppnEntry(['person_name' => ppnPersonName(['last_name' => ['text' => '', 'phonetic_text' => 'doʊ']])]);

    expect($names->getDisplayNameFull($incomplete))->toBe(['text' => '', 'phonetic_text' => null])
        ->and($names->getFullName($incomplete))->toBe(['text' => '', 'phonetic_text' => null]);
});

it('formats the display name by the stored display format', function (mixed $format, string $expectedText, ?string $expectedPhonetic): void {
    $entry = ppnEntry(['person_name' => ppnPersonName([
        'first_name' => ['text' => 'John Paul', 'phonetic_text' => ''],
        'no_middle_name' => false,
        'middle_name' => ['text' => 'Robert', 'phonetic_text' => ''],
        'display_format' => $format,
    ])]);

    expect((new ProviderPeoplePersonNameComputedValues)->getDisplayNameFull($entry))->toBe(['text' => $expectedText, 'phonetic_text' => $expectedPhonetic]);
})->with([
    'an initial format' => ['first_initial_full_middle@ns', 'J. P. Robert Doe', 'ʤeɪ pi doʊ'],
    'a full format' => ['full_first_only@ns', 'John Paul Doe', 'doʊ'],
]);

it('merges the phonetics of the same first and middle text across the primary and alternate names', function (): void {
    $entry = ppnEntry([
        'person_name' => ppnPersonName([
            'first_name' => ['text' => 'Jane', 'phonetic_text' => ''],
            'no_middle_name' => false,
            'middle_name' => ['text' => 'Q', 'phonetic_text' => ''],
            'display_format' => null,
        ]),
        'alternate_name' => [
            ['type' => 'alternate_name_set', 'person_name' => [
                'first_name' => ['text' => 'Jane', 'phonetic_text' => 'ʤeɪn'],
                'middle_name' => ['text' => 'Q', 'phonetic_text' => 'kju'],
                'last_name' => ['text' => 'Roe', 'phonetic_text' => 'roʊ'],
            ]],
        ],
    ]);

    $rows = (new ProviderPeoplePersonNameComputedValues)->getAlternateNameList($entry);

    expect($rows)->toContain(['text' => 'Jane Q Doe', 'phonetic_text' => 'ʤeɪn kju doʊ'])
        ->and($rows)->toContain(['text' => 'Jane Q Roe', 'phonetic_text' => 'ʤeɪn kju roʊ']);
});

it('expands an alternate name that follows an incomplete one, with its middle name and suffix', function (): void {
    $entry = ppnEntry([
        'person_name' => ppnPersonName(['display_format' => null]),
        'alternate_name' => [
            ['type' => 'alternate_name_set', 'person_name' => [
                'first_name' => ['text' => 'Jane', 'phonetic_text' => 'ʤeɪn'],
                'last_name' => ['text' => '', 'phonetic_text' => ''],
            ]],
            ['type' => 'alternate_name_set', 'person_name' => [
                'first_name' => ['text' => 'Jane', 'phonetic_text' => 'ʤeɪn'],
                'middle_name' => ['text' => 'Q', 'phonetic_text' => 'kju'],
                'last_name' => ['text' => 'Smith', 'phonetic_text' => 'smɪθ'],
                'generational_suffix' => 'III',
            ]],
        ],
    ]);

    $rows = (new ProviderPeoplePersonNameComputedValues)->getAlternateNameList($entry);

    expect($rows)->toContain(['text' => 'Jane Q Smith III', 'phonetic_text' => 'ʤeɪn kju smɪθ ðə θɜrd'])
        ->and($rows)->toContain(['text' => 'Jane Smith', 'phonetic_text' => 'ʤeɪn smɪθ']);
});

it('keeps a shell from the primary name distinct from one the alternates repeat, whichever comes first', function (): void {
    $shell = ['first_name' => ['text' => 'A']];

    expect(ppnCall('dedupePersonNameShellsWithSourceFlags', [
        ['shell' => $shell, 'from_primary' => false],
        ['shell' => $shell, 'from_primary' => true],
    ]))->toBe([['shell' => $shell, 'omit_stored_display_format_variation' => false]])
        ->and(ppnCall('dedupePersonNameShellsWithSourceFlags', [
            ['shell' => $shell, 'from_primary' => true],
            ['shell' => $shell, 'from_primary' => true],
            ['shell' => $shell, 'from_primary' => false],
        ]))->toBe([['shell' => $shell, 'omit_stored_display_format_variation' => false]]);
});

it('does not merge a phonetic for empty name text, even when the map holds an empty key', function (): void {
    $merged = ppnCall(
        'applyMergedNamePartPhonetics',
        ['first_name' => ['text' => ''], 'middle_name' => ['text' => ''], 'last_name' => ['text' => 'Doe']],
        ['' => 'FIRST'],
        ['' => 'MIDDLE'],
    );

    expect($merged)->toBe(['first_name' => ['text' => ''], 'middle_name' => ['text' => ''], 'last_name' => ['text' => 'Doe']]);
});

it('reads the middle name and non-pair parts when judging phonetic coherence', function (): void {
    $pronounced = static fn (string $text, ?string $phonetic): array => ['text' => $text, 'phonetic_text' => $phonetic];

    // An unpronounced middle name drops every phonetic
    expect(ppnCall('applyPhoneticCoherenceToCombinedAlternateName', [
        'first_name' => $pronounced('A', 'a'),
        'middle_name' => $pronounced('M', null),
        'last_name' => $pronounced('B', 'b'),
    ]))->toBe([
        'first_name' => $pronounced('A', null),
        'middle_name' => $pronounced('M', null),
        'last_name' => $pronounced('B', null),
    ]);

    // A part that is not a pair does not stop the parts after it from being read
    expect(ppnCall('applyPhoneticCoherenceToCombinedAlternateName', [
        'first_name' => $pronounced('A', 'a'),
        'middle_name' => 'text',
        'last_name' => $pronounced('B', null),
    ]))->toBe([
        'first_name' => $pronounced('A', null),
        'middle_name' => 'text',
        'last_name' => $pronounced('B', null),
    ]);

    // Padding-only text names no part, so its missing phonetic is not held against the rest
    expect(ppnCall('applyPhoneticCoherenceToCombinedAlternateName', [
        'first_name' => $pronounced('A', 'a'),
        'middle_name' => $pronounced('   ', null),
        'last_name' => $pronounced('B', 'b'),
    ]))->toBe([
        'first_name' => $pronounced('A', 'a'),
        'middle_name' => $pronounced('   ', null),
        'last_name' => $pronounced('B', 'b'),
    ]);
});

it('reads the middle name and non-pair parts when judging phonetic variations', function (): void {
    $pronounced = static fn (string $text, ?string $phonetic): array => ['text' => $text, 'phonetic_text' => $phonetic];

    expect(ppnCall('combinedAlternateNameSupportsPhoneticVariations', ['first_name' => $pronounced('A', 'a'), 'middle_name' => $pronounced('M', null), 'last_name' => $pronounced('B', 'b')]))->toBeFalse()
        ->and(ppnCall('combinedAlternateNameSupportsPhoneticVariations', ['first_name' => $pronounced('A', 'a'), 'middle_name' => 'text', 'last_name' => $pronounced('B', null)]))->toBeFalse()
        ->and(ppnCall('combinedAlternateNameSupportsPhoneticVariations', ['first_name' => $pronounced('A', 'a'), 'middle_name' => $pronounced('  ', null), 'last_name' => $pronounced('B', 'b')]))->toBeTrue();
});

it('tells rows apart by text and phonetic even when their concatenations match', function (): void {
    $rows = [
        ['text' => 'A', 'phonetic_text' => 'B'],
        ['text' => 'AB', 'phonetic_text' => null],
        ['text' => 'B', 'phonetic_text' => 'A'],
        ['text' => 'A', 'phonetic_text' => 'B'],
    ];

    expect(ppnCall('dedupeAlternateNameDisplayRows', $rows))->toBe(array_slice($rows, 0, 3));
});

it('keeps only the regulatory credentials that name a term, skipping unusable slugs without stopping', function (): void {
    // Every id resolves, so a slug is dropped only by the check on the slug itself
    $term = ppnTerm('Any', ['clinical_query' => true, 'clinical_statutory_credential_query' => true]);
    Term::shouldReceive('find')->andReturn($term);

    $names = new ProviderPeoplePersonNameComputedValues;

    expect($names->getDisplayNameFullWithClinicalCredentials(ppnEntry(['person_name' => ppnPersonName(), 'credentials' => ['', 'md', 1.5, 'rn']]))['text'])
        ->toBe('Jane Doe, Any, Any');
});

it('reads no abbreviation phonetics from an empty string, even when every id resolves', function (): void {
    Term::shouldReceive('find')->andReturn(ppnTerm('Any', ['abbreviation_phonetic_text' => 'ipa']));

    expect(ppnCall('credentialAbbreviationPhoneticSegments', ''))->toBe([])
        ->and(ppnCall('credentialAbbreviationPhoneticSegments', ['md']))->toBe(['ipa']);
});

it('builds the short prefixed name from an incomplete or padded last name', function (): void {
    $names = new ProviderPeoplePersonNameComputedValues;
    $noFirst = ppnEntry(['person_name' => ppnPersonName(['first_name' => ['text' => '', 'phonetic_text' => '']]), 'credentials' => ['md']]);
    $paddedLast = ppnEntry(['person_name' => ppnPersonName(['last_name' => ['text' => '   ', 'phonetic_text' => 'doʊ']]), 'credentials' => ['md']]);

    expect($names->getDisplayNameShortWithPrefix($noFirst))->toBe(['text' => '', 'phonetic_text' => null])
        ->and($names->getDisplayNameShortWithPrefix($paddedLast))->toBe(['text' => '', 'phonetic_text' => null]);
});
