<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

/**
 * Small language helpers for provider-derived narrative prose (the Provider
 * Spotlight auto-intro / excerpt and CTA copy, UAMS-Web/uams-statamic-archive#1425): the indefinite article
 * that precedes a clinical title, and the subject / object / possessive pronouns
 * derived from a person's `gender` field.
 *
 * The rules are intentionally kept simple and centralized here.
 */
final class ProviderNarrativeLanguage
{
    /**
     * Clinical/occupational titles whose leading letter mispredicts the article
     * under the plain vowel-letter rule (silent `h`, or a `u`/`o` that starts
     * with a consonant "y"/"w" sound).
     *
     * @var array<string, string>
     */
    // PCOV attributes no coverage to a constant's initializer, so Pest selects no test for its mutants and
    // reports them uncovered. Each removed entry is observable, and the exception tests in
    // `ProviderNarrativeLanguageTest` pin it, except `honorary`: `honor` is also a prefix of it, so
    // removing that entry changes no article.
    // @pest-mutate-ignore: RemoveArrayItem
    private const array ARTICLE_EXCEPTIONS = [
        // Vowel letter, consonant sound → "a".
        'university' => 'a',
        'urologist' => 'a',
        'urologic' => 'a',
        'unit' => 'a',
        'one' => 'a',
        // Consonant letter, vowel sound → "an".
        'hour' => 'an',
        'honest' => 'an',
        'honorary' => 'an',
        'honor' => 'an',
    ];

    /**
     * The indefinite article ("a" or "an") for a following phrase, chosen from
     * its first word. Uses a leading-vowel heuristic with a short exception list
     * for the common silent-`h` / consonant-`u` cases. Returns "a" for empty
     * input so callers can safely interpolate.
     */
    public static function indefiniteArticle(string $following): string
    {
        $trimmed = trim($following);

        // An empty string also reaches the first-word guard below, which returns the same "a"
        // @pest-mutate-ignore: EmptyStringToNotEmpty, RemoveEarlyReturn
        if ($trimmed === '') {
            return 'a';
        }

        // Reduce to the first whitespace-delimited word, stripping leading
        // punctuation (e.g. a parenthesized or quoted title). Keep the original
        // case so an initialism can be detected.
        // Splitting a non-empty string always yields a first element, and this fixed pattern cannot fail,
        // so neither fallback is reached
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $firstWordRaw = preg_replace('/^[^A-Za-z]+/', '', preg_split('/\s+/', $trimmed)[0] ?? '') ?? '';

        if ($firstWordRaw === '') {
            return 'a';
        }

        $firstWord = strtolower($firstWordRaw);

        foreach (self::ARTICLE_EXCEPTIONS as $prefix => $article) {
            if (str_starts_with($firstWord, $prefix)) {
                return $article;
            }
        }

        // A short all-caps token is read letter-by-letter (an initialism like
        // "MRI", "RN", "FNP"), so the article follows the LETTER name's sound:
        // "an MRI" (em), "a CRNA" (see), not the plain vowel-letter rule.
        if (preg_match('/^[A-Z]{2,4}$/', $firstWordRaw) === 1) {
            return str_contains('AEFHILMNORSX', $firstWordRaw[0]) ? 'an' : 'a';
        }

        return str_contains('aeiou', $firstWord[0]) ? 'an' : 'a';
    }

    /**
     * The subject pronoun ("she" / "he" / "they") derived from a person's stored
     * `gender` value. Matches case-insensitively on the leading letter so both
     * label ("Female") and key ("female") storage resolve; anything else (Other,
     * empty, or unrecognized) resolves to the singular "they".
     */
    public static function subjectPronoun(?string $gender): string
    {
        return match (self::genderKey($gender)) {
            'female' => 'she',
            'male' => 'he',
            default => 'they',
        };
    }

    /**
     * The object pronoun ("her" / "him" / "them") derived from a person's stored
     * `gender` value, with the same matching rules as {@see subjectPronoun()}.
     * Used in narrative prose ("Get to know them…") where the object form avoids
     * the singular-"they" verb-agreement pitfalls of the subject form.
     */
    public static function objectPronoun(?string $gender): string
    {
        return match (self::genderKey($gender)) {
            'female' => 'her',
            'male' => 'him',
            default => 'them',
        };
    }

    /**
     * The possessive pronoun ("her" / "his" / "their") derived from a person's
     * stored `gender` value, with the same matching rules as {@see subjectPronoun()}.
     * Used in the spotlight intro prose ("…what inspired their work…").
     */
    public static function possessivePronoun(?string $gender): string
    {
        return match (self::genderKey($gender)) {
            'female' => 'her',
            'male' => 'his',
            default => 'their',
        };
    }

    /**
     * Normalize a stored gender value to `female` / `male` / `other`, matching
     * both label ("Female") and key ("female") storage.
     */
    private static function genderKey(?string $gender): string
    {
        $normalized = strtolower(trim((string) $gender));

        // PCOV marks no coverage on this line, so Pest selects no test for its mutant. Matching on `false`
        // instead is observable: applied by hand, it fails 20 of the pronoun tests.
        // @pest-mutate-ignore: TrueToFalse
        return match (true) {
            str_starts_with($normalized, 'female') => 'female',
            str_starts_with($normalized, 'male') => 'male',
            default => 'other',
        };
    }
}
