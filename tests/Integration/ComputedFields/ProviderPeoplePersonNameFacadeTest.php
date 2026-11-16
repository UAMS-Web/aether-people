<?php

declare(strict_types=1);

use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;

covers(ProviderPeoplePersonNameComputedValues::class);

/**
 * The facade keeps the class name and its 12 public methods after the split into the `PersonName`
 * classes. Each case calls one public method on a fully populated entry and pins the exact value, so a
 * method that delegates to the wrong class, drops its argument or loses its return is caught here.
 *
 * @command vendor/bin/pest tests/Integration/ComputedFields/ProviderPeoplePersonNameFacadeTest.php
 */

/**
 * A mocked credentials term: `title` for the name text, `get()` for its fields.
 *
 * @param  array<string, mixed>  $data
 */
function facadeTerm(string $title, array $data): TermContract
{
    // `pluck('title')` reads the title through ArrayAccess, as on a real term
    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('offsetExists')->andReturnUsing(fn (mixed $key): bool => $key === 'title');
    $term->shouldReceive('offsetGet')->with('title')->andReturn($title);
    $term->shouldReceive('get')->andReturnUsing(fn (string $key, mixed $default = null): mixed => $data[$key] ?? $default);

    return mustBe(TermContract::class, $term, 'the term double');
}

/**
 * A person entry that every public method has something to say about: a full name with a nickname,
 * a doctor credential and a nursing credential.
 */
function facadePerson(): Entry
{
    $terms = [
        'md' => facadeTerm('M.D.', ['clinical_query' => true, 'clinical_statutory_credential_query' => true, 'abbreviation_phonetic_text' => 'ɛm diː']),
        'rn' => facadeTerm('RN', ['clinical_query' => true, 'clinical_statutory_credential_query' => false, 'abbreviation_phonetic_text' => 'ɑr ɛn']),
    ];
    Term::shouldReceive('find')->andReturnUsing(
        fn (string $id): ?TermContract => $terms[substr($id, \strlen('credentials::'))] ?? null
    );

    $map = [
        'person_name' => [
            'first_name' => ['text' => 'Jane', 'phonetic_text' => 'ʤeɪn'],
            'no_middle_name' => true,
            'middle_name' => ['text' => '', 'phonetic_text' => ''],
            'no_nickname' => false,
            'nickname' => ['text' => 'Janie', 'phonetic_text' => 'ʤeɪni'],
            'last_name' => ['text' => 'Doe', 'phonetic_text' => 'doʊ'],
            'no_generational_suffix' => true,
            'generational_suffix' => null,
            'display_format' => 'full_first_only@ns',
        ],
        'credentials' => ['md', 'rn'],
    ];
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->andReturnUsing(
        fn (string $key, mixed $default = null): mixed => \array_key_exists($key, $map) ? $map[$key] : $default
    );

    return mustBe(Entry::class, $entry, 'the entry double');
}

it('keeps its public surface to the 12 computed methods', function (): void {
    $public = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        new ReflectionClass(ProviderPeoplePersonNameComputedValues::class)->getMethods(ReflectionMethod::IS_PUBLIC)
    );
    sort($public);

    expect($public)->toBe([
        '__construct',
        'getAlternateNameList',
        'getDisplayNameFull',
        'getDisplayNameFullWithClinicalCredentials',
        'getDisplayNameFullWithCredentials',
        'getDisplayNameFullWithPrefix',
        'getDisplayNameShortWithPrefix',
        'getFullName',
        'getFullNameWithCredentials',
        'getFullNameWithPrefix',
        'getLegalNamePairs',
        'getNicknameList',
        'getPrefixValue',
    ]);
});

it('answers each public method as the class it delegates to does', function (string $method, mixed $expected): void {
    $names = new ProviderPeoplePersonNameComputedValues;

    expect($names->{$method}(facadePerson()))->toBe($expected);
})->with([
    'getPrefixValue' => ['getPrefixValue', ['text' => 'Dr.', 'phonetic_text' => 'ˈdɑktər']],
    'getLegalNamePairs' => ['getLegalNamePairs', [
        'first_name' => ['text' => 'Jane', 'phonetic_text' => 'ʤeɪn'],
        'middle_name' => ['text' => '', 'phonetic_text' => ''],
        'last_name' => ['text' => 'Doe', 'phonetic_text' => 'doʊ'],
    ]],
    'getDisplayNameFull' => ['getDisplayNameFull', ['text' => 'Jane Doe', 'phonetic_text' => 'ʤeɪn doʊ']],
    'getFullName' => ['getFullName', ['text' => 'Jane "Janie" Doe', 'phonetic_text' => 'ʤeɪn | ʤeɪni | doʊ']],
    'getNicknameList' => ['getNicknameList', [['text' => 'Janie', 'phonetic_text' => 'ʤeɪni']]],
    'getAlternateNameList' => ['getAlternateNameList', [
        ['text' => 'Jane "Janie" Doe', 'phonetic_text' => 'ʤeɪn | ʤeɪni | doʊ'],
        ['text' => 'Janie Doe', 'phonetic_text' => 'ʤeɪni doʊ'],
        ['text' => 'Jane Doe', 'phonetic_text' => 'ʤeɪn doʊ'],
    ]],
    'getDisplayNameFullWithPrefix' => ['getDisplayNameFullWithPrefix', ['text' => 'Dr. Jane Doe', 'phonetic_text' => 'ˈdɑktər ʤeɪn doʊ']],
    'getDisplayNameFullWithCredentials' => ['getDisplayNameFullWithCredentials', ['text' => 'Jane Doe, M.D., RN', 'phonetic_text' => 'ʤeɪn doʊ.ɛm diː.ɑr ɛn']],
    'getDisplayNameFullWithClinicalCredentials' => ['getDisplayNameFullWithClinicalCredentials', ['text' => 'Jane Doe, M.D.', 'phonetic_text' => 'ʤeɪn doʊ.ɛm diː']],
    'getDisplayNameShortWithPrefix' => ['getDisplayNameShortWithPrefix', ['text' => 'Dr. Doe', 'phonetic_text' => 'ˈdɑktər doʊ']],
    'getFullNameWithPrefix' => ['getFullNameWithPrefix', ['text' => 'Dr. Jane "Janie" Doe', 'phonetic_text' => 'ˈdɑktər ʤeɪn | ʤeɪni | doʊ']],
    'getFullNameWithCredentials' => ['getFullNameWithCredentials', ['text' => 'Jane "Janie" Doe, M.D., RN', 'phonetic_text' => 'ʤeɪn | ʤeɪni | doʊ.ɛm diː.ɑr ɛn']],
]);
