<?php

declare(strict_types=1);

use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;

covers(ProviderPeoplePersonNameComputedValues::class);

/**
 * `display_name_full_with_clinical_credentials` keeps only the credentials whose
 * term answers yes to both "Is this a clinical credential?" and "Is this a
 * regulatory designation?" (UAMS-Web/uams-statamic#344), while
 * `display_name_full_with_credentials` keeps every selected credential.
 *
 * Terms are served by a mocked `Term::find`, so no content/ or Stache is read.
 *
 * @command vendor/bin/pest tests/Integration/ComputedFields/DisplayNameWithClinicalCredentialsTest.php
 */

/**
 * A mocked credentials term: `title` for the name text, `get()` for its fields.
 *
 * @param  array<string, mixed>  $data
 */
function clinicalNameTerm(string $title, array $data): TermContract
{
    // `pluck('title')` reads the title through ArrayAccess, as on a real term
    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('offsetExists')
        ->andReturnUsing(fn (mixed $key): bool => $key === 'title');
    $term->shouldReceive('offsetGet')
        ->with('title')
        ->andReturn($title);
    $term->shouldReceive('get')
        ->andReturnUsing(fn (string $key, mixed $default = null): mixed => $data[$key] ?? $default);

    return mustBe(TermContract::class, $term, 'the term double');
}

/**
 * Serve the given terms (keyed by slug) from `Term::find('credentials::<slug>')`.
 *
 * @param  array<array-key, TermContract>  $terms
 */
function clinicalNameServeTerms(array $terms): void
{
    Term::shouldReceive('find')
        ->andReturnUsing(fn (string $id): ?TermContract => $terms[substr($id, \strlen('credentials::'))] ?? null);
}

/**
 * A person entry named Jane Doe holding the given credentials value.
 */
function clinicalNamePerson(mixed $credentials): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(function (string $key, mixed $default = null) use ($credentials): mixed {
            $map = [
                'person_name' => [
                    'first_name' => ['text' => 'Jane', 'phonetic_text' => 'ʤeɪn'],
                    'no_middle_name' => true,
                    'middle_name' => ['text' => '', 'phonetic_text' => ''],
                    'no_nickname' => true,
                    'nickname' => ['text' => '', 'phonetic_text' => ''],
                    'last_name' => ['text' => 'Doe', 'phonetic_text' => 'doʊ'],
                    'no_generational_suffix' => true,
                    'generational_suffix' => null,
                    'display_format' => 'full_first_only@ns',
                ],
                'credentials' => $credentials,
            ];

            return \array_key_exists($key, $map) ? $map[$key] : $default;
        });

    return mustBe(Entry::class, $entry, 'the entry double');
}

/**
 * The credential mix every case draws on: two regulatory designations, a clinical
 * credential that is not one, a non-clinical credential, and a non-clinical term
 * carrying a stale, hidden regulatory answer.
 *
 * @return array<string, TermContract>
 */
function clinicalNameTerms(): array
{
    return [
        'md' => clinicalNameTerm('M.D.', ['clinical_query' => true, 'clinical_statutory_credential_query' => true, 'abbreviation_phonetic_text' => 'ɛm diː']),
        'rn' => clinicalNameTerm('RN', ['clinical_query' => true, 'clinical_statutory_credential_query' => true, 'abbreviation_phonetic_text' => 'ɑr ɛn']),
        'ma' => clinicalNameTerm('MA (medical assistant)', ['clinical_query' => true, 'clinical_statutory_credential_query' => false, 'abbreviation_phonetic_text' => 'ɛm eɪ']),
        'phd_nonclinical' => clinicalNameTerm('Ph.D. (Non-Clinical)', ['clinical_query' => false, 'abbreviation_phonetic_text' => 'piː eɪʧ diː']),
        'stale' => clinicalNameTerm('Stale', ['clinical_query' => false, 'clinical_statutory_credential_query' => true, 'abbreviation_phonetic_text' => 'ɛs tiː']),
    ];
}

it('keeps only the regulatory-designation credentials, in their selected order', function (): void {
    clinicalNameServeTerms(clinicalNameTerms());
    $names = new ProviderPeoplePersonNameComputedValues;
    $person = clinicalNamePerson(['ma', 'md', 'phd_nonclinical', 'stale', 'missing', 'rn']);

    $clinical = $names->getDisplayNameFullWithClinicalCredentials($person);

    expect($clinical['text'])->toBe('Jane Doe, M.D., RN')
        ->and($clinical['phonetic_text'])->toContain('ɛm diː')
        ->and($clinical['phonetic_text'])->toContain('ɑr ɛn')
        ->and($clinical['phonetic_text'])->not->toContain('ɛm eɪ')
        ->and($clinical['phonetic_text'])->not->toContain('piː eɪʧ diː')
        ->and($clinical['phonetic_text'])->not->toContain('ɛs tiː');
});

it('leaves the full credentials name on every selected credential', function (): void {
    clinicalNameServeTerms(clinicalNameTerms());
    $names = new ProviderPeoplePersonNameComputedValues;
    $person = clinicalNamePerson(['ma', 'md', 'phd_nonclinical', 'stale', 'missing', 'rn']);

    expect($names->getDisplayNameFullWithCredentials($person)['text'])
        ->toBe('Jane Doe, MA (medical assistant), M.D., Ph.D. (Non-Clinical), Stale, RN');
});

it('requires a strict true for both answers', function (mixed $clinical, mixed $regulatory): void {
    clinicalNameServeTerms(['x' => clinicalNameTerm('X', ['clinical_query' => $clinical, 'clinical_statutory_credential_query' => $regulatory])]);
    $names = new ProviderPeoplePersonNameComputedValues;

    expect($names->getDisplayNameFullWithClinicalCredentials(clinicalNamePerson(['x']))['text'])->toBe('Jane Doe');
})->with([
    'regulatory as string' => [true, 'true'],
    'regulatory as 1' => [true, 1],
    'regulatory absent' => [true, null],
    'clinical as 1' => [1, true],
    'clinical absent' => [null, true],
]);

it('falls back to the bare name when nothing qualifies or credentials are not a list', function (mixed $credentials): void {
    clinicalNameServeTerms(clinicalNameTerms());
    $names = new ProviderPeoplePersonNameComputedValues;

    $pair = $names->getDisplayNameFullWithClinicalCredentials(clinicalNamePerson($credentials));

    expect($pair['text'])->toBe('Jane Doe')
        ->and($pair['phonetic_text'])->toBe('ʤeɪn doʊ');
})->with([
    'only non-regulatory' => [['ma', 'phd_nonclinical', 'stale']],
    'empty list' => [[]],
    'null' => [null],
    'unusable slugs' => [[null, '', 1.5, ['md']]],
]);

it('reads a bare slug as a one-item list, as the full name does', function (): void {
    clinicalNameServeTerms(clinicalNameTerms());
    $names = new ProviderPeoplePersonNameComputedValues;

    expect($names->getDisplayNameFullWithClinicalCredentials(clinicalNamePerson('md'))['text'])->toBe('Jane Doe, M.D.')
        ->and($names->getDisplayNameFullWithClinicalCredentials(clinicalNamePerson('ma'))['text'])->toBe('Jane Doe');
});

it('reads an integer slug as a string, as the full name does', function (): void {
    clinicalNameServeTerms(['5' => clinicalNameTerm('Five', ['clinical_query' => true, 'clinical_statutory_credential_query' => true])]);
    $names = new ProviderPeoplePersonNameComputedValues;

    expect($names->getDisplayNameFullWithClinicalCredentials(clinicalNamePerson([5]))['text'])->toBe('Jane Doe, Five')
        ->and($names->getDisplayNameFullWithCredentials(clinicalNamePerson([5]))['text'])->toBe('Jane Doe, Five');
});
