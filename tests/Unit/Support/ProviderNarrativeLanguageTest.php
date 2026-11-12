<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\ProviderNarrativeLanguage;

it('chooses the indefinite article by leading vowel sound', function (string $following, string $expected): void {
    expect(ProviderNarrativeLanguage::indefiniteArticle($following))->toBe($expected);
})->with([
    'consonant title' => ['cardiologist', 'a'],
    'vowel title' => ['oncologist', 'an'],
    'multi-word vowel' => ['orthopaedic surgeon', 'an'],
    'ENT (vowel sound letter E)' => ['ENT specialist', 'an'],
    'consonant-sound u' => ['urologist', 'a'],
    'consonant-sound university' => ['university professor', 'a'],
    'silent-h vowel sound' => ['honorary fellow', 'an'],
    'leading punctuation stripped' => ['(interventional) radiologist', 'an'],
    'empty string' => ['', 'a'],
    'physician' => ['physician', 'a'],
    // Initialisms read letter-by-letter follow the letter-name sound.
    'initialism MRI (em)' => ['MRI technologist', 'an'],
    'initialism RN (ar)' => ['RN', 'an'],
    'initialism FNP (eff)' => ['FNP', 'an'],
    'initialism PA (pee)' => ['PA', 'a'],
    'initialism CRNA (see)' => ['CRNA', 'a'],
]);

it('derives the subject pronoun from the gender value', function (?string $gender, string $expected): void {
    expect(ProviderNarrativeLanguage::subjectPronoun($gender))->toBe($expected);
})->with([
    'female label' => ['Female', 'she'],
    'female key' => ['female', 'she'],
    'male label' => ['Male', 'he'],
    'male key' => ['male', 'he'],
    'other' => ['Other', 'they'],
    'empty' => ['', 'they'],
    'null' => [null, 'they'],
    'unrecognized' => ['nonbinary', 'they'],
]);

it('derives the object pronoun from the gender value', function (?string $gender, string $expected): void {
    expect(ProviderNarrativeLanguage::objectPronoun($gender))->toBe($expected);
})->with([
    'female' => ['Female', 'her'],
    'male' => ['Male', 'him'],
    'other' => ['Other', 'them'],
    'null' => [null, 'them'],
]);

it('derives the possessive pronoun from the gender value', function (?string $gender, string $expected): void {
    expect(ProviderNarrativeLanguage::possessivePronoun($gender))->toBe($expected);
})->with([
    'female' => ['Female', 'her'],
    'male' => ['Male', 'his'],
    'other' => ['Other', 'their'],
    'null' => [null, 'their'],
    'key-cased' => ['female', 'her'],
]);

it('reads the first word after trimming, case-insensitively and by prefix', function (string $following, string $expected): void {
    expect(ProviderNarrativeLanguage::indefiniteArticle($following))->toBe($expected);
})->with([
    'leading space' => [' oncologist', 'an'],
    'capitalized vowel title' => ['Oncologist', 'an'],
    'capitalized silent h' => ['Hour', 'an'],
    'exception as a prefix' => ['hourly rate', 'an'],
    'no letters at all' => ['(123)', 'a'],
]);

it('takes the article from each exception', function (string $following, string $expected): void {
    expect(ProviderNarrativeLanguage::indefiniteArticle($following))->toBe($expected);
})->with([
    'university' => ['university', 'a'],
    'urologic' => ['urologic surgeon', 'a'],
    'unit' => ['unit director', 'a'],
    'one' => ['one-time visit', 'a'],
    'hour' => ['hour', 'an'],
    'honest' => ['honest broker', 'an'],
    'honor' => ['honor society', 'an'],
]);

it('reads a gender value by its leading word, after trimming', function (?string $gender, string $expected): void {
    expect(ProviderNarrativeLanguage::subjectPronoun($gender))->toBe($expected);
})->with([
    'padded female' => [' female ', 'she'],
    'female with more after it' => ['Female (she/her)', 'she'],
    'male with more after it' => ['Male (he/him)', 'he'],
]);
