<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Entries\Entry;

/**
 * Joins a person's legal name and the refinements an editor keeps beside it
 * back into the single `person_name` value the name helpers read (UAMS-Web/uams-statamic-archive#3186,
 * decided on UAMS-Web/uams-statamic-archive#3130).
 *
 * Workday owns the legal name parts, which stay in `person_name`. The nickname,
 * the pronunciation of each legal part and the preferred display format live in
 * their own fields, which the Workday sync never writes. Every consumer that
 * reads a person's name reads it through `fromData()`, so it sees the same shape
 * it read before the split: a sibling field that holds a value wins, and one
 * that holds none leaves the `person_name` value as stored. An empty nickname reads as
 * no nickname. `split()` is the reverse, used once to move stored names apart.
 */
final class PersonNameComposite
{
    /**
     * The field holding the legal name parts.
     */
    public const string PERSON_NAME = 'person_name';

    /**
     * The field holding the nickname and its pronunciation.
     */
    public const string NICKNAME = 'nickname';

    /**
     * The field holding the pronunciation of each legal name part.
     */
    public const string PRONUNCIATION = 'name_pronunciation';

    /**
     * The field holding the preferred display format key.
     */
    public const string DISPLAY_FORMAT = 'name_display_format';

    /**
     * The legal name parts a pronunciation is kept for.
     *
     * @var list<string>
     */
    // PCOV attributes no coverage to a constant's initializer, so Pest selects no test for its mutants and
    // reports them uncovered. A removed part is observable, and the split test in `PersonNameCompositeTest`
    // that clears every legal part's pronunciation pins each one.
    // @pest-mutate-ignore: RemoveArrayItem
    public const array PRONOUNCED_PARTS = [
        'first_name',
        'middle_name',
        'last_name',
    ];

    /**
     * The combined name of an entry, or null when it has no `person_name`.
     *
     * @param  Entry  $entry  The `people` or `clinical_providers` entry
     * @return array<string, mixed>|null
     */
    public static function fromEntry(Entry $entry): ?array
    {
        // Read the four fields through the entry, so origin values are honored
        $data = [];

        foreach ([self::PERSON_NAME, self::NICKNAME, self::PRONUNCIATION, self::DISPLAY_FORMAT] as $handle) {
            $value = $entry->get($handle);

            // Skip a field the entry holds no value for, so the stored name is kept
            if ($value === null) {
                continue;
            }

            $data[$handle] = $value;
        }

        return self::fromData($data);
    }

    /**
     * The combined name held by field values keyed by handle, or null when they
     * hold no `person_name`.
     *
     * @param  array<array-key, mixed>  $data  Field values, as stored or as submitted
     * @return array<string, mixed>|null
     */
    public static function fromData(array $data): ?array
    {
        $personName = self::toArray($data[self::PERSON_NAME] ?? null);

        if ($personName === null) {
            return null;
        }

        // Give each legal part the pronunciation kept beside it
        $pronunciation = self::toArray($data[self::PRONUNCIATION] ?? null);

        if ($pronunciation !== null) {
            foreach (self::PRONOUNCED_PARTS as $part) {
                if (! \array_key_exists($part, $pronunciation)) {
                    continue;
                }

                $pair = self::toArray($personName[$part] ?? null) ?? [];
                $phonetic = $pronunciation[$part];
                $pair['phonetic_text'] = \is_string($phonetic) ? $phonetic : null;
                $personName[$part] = $pair;
            }
        }

        // Take the nickname from its own field, reading an empty one as none
        if (\array_key_exists(self::NICKNAME, $data)) {
            $nickname = self::toArray($data[self::NICKNAME]) ?? [];
            $text = $nickname['text'] ?? null;
            $phonetic = $nickname['phonetic_text'] ?? null;
            $hasNickname = \is_string($text) && \trim($text) !== '';

            $personName['no_nickname'] = ! $hasNickname;
            $personName['nickname'] = [
                'text' => $hasNickname ? $text : null,
                'phonetic_text' => $hasNickname && \is_string($phonetic) ? $phonetic : null,
            ];
        }

        // Take the display format from its own field
        if (\array_key_exists(self::DISPLAY_FORMAT, $data)) {
            $format = $data[self::DISPLAY_FORMAT];
            $personName['display_format'] = \is_string($format) && \trim($format) !== '' ? $format : null;
        }

        // Key the joined name by its sub-field handles
        $joined = [];

        foreach ($personName as $key => $value) {
            // PHP stores a numeric string key as an integer either way, so the cast changes no key; it
            // is kept to tell PHPStan the result is keyed by strings
            // @pest-mutate-ignore: RemoveStringCast
            $joined[(string) $key] = $value;
        }

        return $joined;
    }

    /**
     * Move a stored combined name apart into the four fields, keeping every
     * value: the legal parts with no pronunciation, the nickname, the
     * pronunciation of each legal part, and the display format.
     *
     * @param  array<array-key, mixed>  $personName  A `person_name` value as stored before the split
     * @return array{person_name: array<array-key, mixed>, nickname: array{text: string|null, phonetic_text: string|null}|null, name_pronunciation: array<string, string>|null, name_display_format: string|null}
     */
    public static function split(array $personName): array
    {
        // Collect each legal part's pronunciation, then clear it from the part
        $pronunciation = [];

        foreach (self::PRONOUNCED_PARTS as $part) {
            $pair = self::toArray($personName[$part] ?? null);

            if ($pair === null) {
                continue;
            }

            $phonetic = $pair['phonetic_text'] ?? null;

            if (\is_string($phonetic) && \trim($phonetic) !== '') {
                $pronunciation[$part] = $phonetic;
            }

            $pair['phonetic_text'] = null;
            $personName[$part] = $pair;
        }

        // Take the nickname out, keeping it only when it has text
        $nicknamePair = self::toArray($personName['nickname'] ?? null) ?? [];
        $nicknameText = $nicknamePair['text'] ?? null;
        $nicknamePhonetic = $nicknamePair['phonetic_text'] ?? null;
        $hasNickname = empty($personName['no_nickname'])
            && \is_string($nicknameText)
            && \trim($nicknameText) !== '';

        $nickname = $hasNickname ? [
            'text' => $nicknameText,
            'phonetic_text' => \is_string($nicknamePhonetic) && \trim($nicknamePhonetic) !== '' ? $nicknamePhonetic : null,
        ] : null;

        $personName['no_nickname'] = true;
        $personName['nickname'] = [
            'text' => null,
            'phonetic_text' => null,
        ];

        // Take the display format out
        $format = $personName['display_format'] ?? null;
        $personName['display_format'] = null;

        return [
            self::PERSON_NAME => $personName,
            self::NICKNAME => $nickname,
            self::PRONUNCIATION => $pronunciation === [] ? null : $pronunciation,
            self::DISPLAY_FORMAT => \is_string($format) && \trim($format) !== '' ? $format : null,
        ];
    }

    /**
     * A field value as an array, or null when it is neither an array nor a
     * collection.
     *
     * @param  mixed  $value  A stored or submitted field value
     * @return array<array-key, mixed>|null
     */
    private static function toArray(mixed $value): ?array
    {
        if ($value instanceof IlluminateCollection) {
            return $value->all();
        }

        return \is_array($value) ? $value : null;
    }
}
