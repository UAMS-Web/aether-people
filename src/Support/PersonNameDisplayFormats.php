<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

/**
 * Builds professional display-name preview options and allowed storage keys for
 * the Person Name fieldtype. Mirrors personNameDisplayFormats.js for CP
 * previews and server-side validation.
 *
 * Suffix is appended with a single ASCII space before the suffix token (e.g.
 * "James Carter Jr."), matching the examples in the product spec.
 */
final class PersonNameDisplayFormats
{
    /**
     * When two patterns render the same string, the higher rank wins (prefer
     * initial-style variants per product spec).
     *
     * @var array<string, int>
     */
    private const array PATTERN_DEDUPE_RANK = [
        'full_first_full_middle' => 1,
        'full_first_full_middle_nickname' => 1,
        'full_first_middle_initial' => 3,
        'full_first_middle_initial_nickname' => 3,
        'first_initial_full_middle' => 2,
        'first_initial_full_middle_nickname' => 2,
        'middle_only' => 1,
        'middle_only_nickname' => 1,
        'first_initial_middle_initial' => 4,
        'first_initial_middle_initial_nickname' => 4,
        'full_first_only' => 1,
        'full_first_only_nickname' => 1,
        'nickname_only' => 1,
    ];

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function generationalSuffixSelectOptions(): array
    {
        $labels = [
            'Jr.',
            'Sr.',
            'II',
            'III',
            'IV',
            'V',
            'VI',
            'VII',
            'VIII',
            'X',
        ];

        $out = [];

        foreach ($labels as $label) {
            $out[] = [
                'value' => $label,
                'label' => $label,
            ];
        }

        unset($labels, $label);

        return $out;
    }

    /**
     * Normalize a raw field value to a consistent shape for validation and
     * display-format generation.
     *
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>
     */
    public static function normalizeValue(?array $value): array
    {
        $v = \is_array($value) ? $value : [];

        $pair = static function (string $key) use ($v): array {
            $p = $v[$key] ?? null;

            return [
                'text' => \is_array($p) && isset($p['text']) && \is_string($p['text'])
                    ? $p['text']
                    : '',
                'phonetic_text' => \is_array($p) && isset($p['phonetic_text']) && \is_string($p['phonetic_text'])
                    ? $p['phonetic_text']
                    : '',
            ];
        };

        $bool = static function (string $key) use ($v): bool {
            if (! \array_key_exists($key, $v)) {
                return false;
            }

            $raw = $v[$key];

            if (\is_bool($raw)) {
                return $raw;
            }

            if (\is_string($raw)) {
                $t = \mb_strtolower(\trim($raw));

                return \in_array($t, ['1', 'true', 'on', 'yes'], true);
            }

            if (\is_int($raw)) {
                return $raw === 1;
            }

            return (bool) $raw;
        };

        $suffix = $v['generational_suffix'] ?? null;
        $suffixStr = \is_string($suffix) ? \trim($suffix) : '';

        $out = [
            'first_name' => $pair('first_name'),
            'no_middle_name' => $bool('no_middle_name'),
            'middle_name' => $pair('middle_name'),
            'no_nickname' => $bool('no_nickname'),
            'nickname' => $pair('nickname'),
            'last_name' => $pair('last_name'),
            'no_generational_suffix' => $bool('no_generational_suffix'),
            'generational_suffix' => $suffixStr === '' ? null : $suffixStr,
            'display_format' => isset($v['display_format']) && \is_string($v['display_format'])
                ? $v['display_format']
                : null,
        ];

        unset($v, $pair, $bool, $suffix, $suffixStr);

        return $out;
    }

    /**
     * Read a `text` / `phonetic_text` sub-value from a normalized name pair, returning an
     * empty string when the part or sub-key is missing or non-scalar.
     *
     * @param  array<string, mixed>  $normalized
     */
    private static function pairSubValue(
        array $normalized,
        string $part,
        string $key
    ): string {
        $pair = $normalized[$part] ?? null;

        if (! \is_array($pair)) {
            return '';
        }

        $value = $pair[$key] ?? '';

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * Whether all name parts are resolved enough to build display format options.
     * Mirrors `isReadyForDisplayFormatOptions()` in personNameDisplayFormats.js.
     *
     * @param  array<string, mixed>  $normalized
     */
    public static function isReadyForDisplayFormatOptions(array $normalized): bool
    {
        $first = \trim(self::pairSubValue($normalized, 'first_name', 'text'));
        $middle = \trim(self::pairSubValue($normalized, 'middle_name', 'text'));
        $last = \trim(self::pairSubValue($normalized, 'last_name', 'text'));
        $nick = \trim(self::pairSubValue($normalized, 'nickname', 'text'));
        // The flag is only read through ! and ||, which cast it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        $noMiddle = (bool) ($normalized['no_middle_name'] ?? false);
        // The flag is only read through ! and ||, which cast it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        $noNick = (bool) ($normalized['no_nickname'] ?? false);
        // The flag is only read through ! and ||, which cast it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        $noGen = (bool) ($normalized['no_generational_suffix'] ?? false);
        $suffixRaw = $normalized['generational_suffix'] ?? null;
        $suffixStr = \is_string($suffixRaw) ? \trim($suffixRaw) : '';
        $suffixSet = $suffixStr !== '';

        if ($first === '') {
            return false;
        }

        if ($last === '') {
            return false;
        }

        if (! $noMiddle && $middle === '') {
            return false;
        }

        if (! $noNick && $nick === '') {
            return false;
        }

        return $noGen || $suffixSet;
    }

    /**
     * Allowed display_format keys for the given normalized value.
     *
     * @param  array<string, mixed>  $normalized
     * @return array<int, string>
     */
    public static function allowedKeys(array $normalized): array
    {
        $opts = self::buildOptions($normalized);

        // array_map() keeps the list keys that buildOptions() returns
        // @pest-mutate-ignore: UnwrapArrayValues
        return \array_values(
            \array_map(
                static fn (array $o): string => $o['key'],
                $opts
            )
        );
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<int, array{key: string, label: string, group: string}>
     */
    public static function buildOptions(array $normalized): array
    {
        if (! self::isReadyForDisplayFormatOptions($normalized)) {
            return [];
        }

        // initials() and joinParts() trim the first name again
        // @pest-mutate-ignore: UnwrapTrim
        $first = \trim(self::pairSubValue($normalized, 'first_name', 'text'));
        // A blank middle name that is not switched off is not ready, and initials() and joinParts() trim it again
        // @pest-mutate-ignore: UnwrapTrim
        $middle = \trim(self::pairSubValue($normalized, 'middle_name', 'text'));
        // joinParts() trims the last name again
        // @pest-mutate-ignore: UnwrapTrim
        $last = \trim(self::pairSubValue($normalized, 'last_name', 'text'));
        $nick = \trim(self::pairSubValue($normalized, 'nickname', 'text'));
        // The flag is only read through !, which casts it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        $noMiddle = (bool) ($normalized['no_middle_name'] ?? false);
        // The flag is only read through !, which casts it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        $noNick = (bool) ($normalized['no_nickname'] ?? false);
        // The flag is only read through !, which casts it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        $noGen = (bool) ($normalized['no_generational_suffix'] ?? false);
        $suffixRaw = $normalized['generational_suffix'] ?? null;
        // appendSuffix() trims the suffix it is given
        $suffix = \is_string($suffixRaw) ? $suffixRaw : null;

        // A blank middle name that is not switched off is not ready, so it never gets here
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $hasMiddle = ! $noMiddle && $middle !== '';
        // A blank nickname that is not switched off is not ready, so it never gets here
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $hasNick = ! $noNick && $nick !== '';
        // The readiness check above passes only with the suffix switched off or set
        $hasSuffix = ! $noGen;

        $fI = self::initials($first);
        $mI = self::initials($middle);
        $qNick = $hasNick ? '"'.$nick.'"' : null;

        $candidates = [];

        // Only the order of the seq values matters
        // @pest-mutate-ignore: DecrementInteger, IncrementInteger
        $seq = 0;

        $addGroup = static function (bool $withSuffix) use (
            &$candidates,
            &$seq,
            $first,
            $middle,
            $last,
            $hasMiddle,
            $hasNick,
            $qNick,
            $fI,
            $mI,
            $suffix,
            $nick
        ): void {
            $suffixPart = $withSuffix ? $suffix : null;
            $group = $withSuffix ? 'with_suffix' : 'without_suffix';

            $push = static function (
                string $patternId,
                ?string $label
            ) use (
                &$candidates,
                &$seq,
                $group,
                $withSuffix
            ): void {
                // Every pattern label includes the last name, so it is never null or empty
                // @pest-mutate-ignore: BooleanOrToBooleanAnd, EmptyStringToNotEmpty
                if ($label === null || $label === '') {
                    return;
                }

                $key = self::composeKey(
                    $patternId,
                    $withSuffix
                );
                $rank = self::PATTERN_DEDUPE_RANK[$patternId];
                $candidates[] = [
                    'key' => $key,
                    'label' => $label,
                    'group' => $group,
                    'rank' => $rank,
                    'seq' => $seq++,
                ];
            };

            if ($hasMiddle) {
                $push(
                    'full_first_full_middle',
                    self::appendSuffix(
                        self::joinParts(
                            $first,
                            $middle,
                            $last
                        ),
                        $suffixPart
                    )
                );
                if ($qNick !== null) {
                    $push(
                        'full_first_full_middle_nickname',
                        self::appendSuffix(
                            self::joinParts(
                                $first,
                                $middle,
                                $qNick,
                                $last
                            ),
                            $suffixPart
                        )
                    );
                }

                $push(
                    'full_first_middle_initial',
                    self::appendSuffix(
                        self::joinParts(
                            $first,
                            $mI,
                            $last
                        ),
                        $suffixPart
                    )
                );
                if ($qNick !== null) {
                    $push(
                        'full_first_middle_initial_nickname',
                        self::appendSuffix(
                            self::joinParts(
                                $first,
                                $mI,
                                $qNick,
                                $last
                            ),
                            $suffixPart
                        )
                    );
                }

                $push(
                    'first_initial_full_middle',
                    self::appendSuffix(
                        self::joinParts(
                            $fI,
                            $middle,
                            $last
                        ),
                        $suffixPart
                    )
                );
                if ($qNick !== null) {
                    $push(
                        'first_initial_full_middle_nickname',
                        self::appendSuffix(
                            self::joinParts(
                                $fI,
                                $middle,
                                $qNick,
                                $last
                            ),
                            $suffixPart
                        )
                    );
                }

                $push(
                    'middle_only',
                    self::appendSuffix(
                        self::joinParts(
                            $middle,
                            $last
                        ),
                        $suffixPart
                    )
                );
                if ($qNick !== null) {
                    $push(
                        'middle_only_nickname',
                        self::appendSuffix(
                            self::joinParts(
                                $middle,
                                $qNick,
                                $last
                            ),
                            $suffixPart
                        )
                    );
                }

                $push(
                    'first_initial_middle_initial',
                    self::appendSuffix(
                        self::joinParts(
                            $fI,
                            $mI,
                            $last
                        ),
                        $suffixPart
                    )
                );
                if ($qNick !== null) {
                    $push(
                        'first_initial_middle_initial_nickname',
                        self::appendSuffix(
                            self::joinParts(
                                $fI,
                                $mI,
                                $qNick,
                                $last
                            ),
                            $suffixPart
                        )
                    );
                }
            }

            $push(
                'full_first_only',
                self::appendSuffix(
                    self::joinParts(
                        $first,
                        $last
                    ),
                    $suffixPart
                )
            );
            if ($qNick !== null) {
                $push(
                    'full_first_only_nickname',
                    self::appendSuffix(
                        self::joinParts(
                            $first,
                            $qNick,
                            $last
                        ),
                        $suffixPart
                    )
                );
            }

            if ($hasNick) {
                $push(
                    'nickname_only',
                    self::appendSuffix(
                        self::joinParts(
                            $nick,
                            $last
                        ),
                        $suffixPart
                    )
                );
            }
        };

        if ($hasSuffix) {
            $addGroup(true);
        }

        $addGroup(false);

        $byLabel = [];

        // Candidates arrive in ascending seq order, so on an equal rank the row
        // already kept has the lower seq and stays: no seq tie-break is needed
        foreach ($candidates as $c) {
            $label = $c['label'];
            $prev = $byLabel[$label] ?? null;

            if (
                $prev === null
                || $c['rank'] > $prev['rank']
            ) {
                $byLabel[$label] = $c;
            }
        }

        unset($candidates, $c, $label, $prev);

        // usort() below renumbers the list anyway
        // @pest-mutate-ignore: UnwrapArrayValues
        $survivors = \array_values($byLabel);

        unset($byLabel);

        \usort(
            $survivors,
            static fn (array $a, array $b): int => $a['seq'] <=> $b['seq']
        );

        $out = [];

        foreach ($survivors as $row) {
            $out[] = [
                'key' => $row['key'],
                'label' => $row['label'],
                'group' => $row['group'],
            ];
        }

        unset($survivors, $row);

        return $out;
    }

    public static function composeKey(
        string $patternId,
        bool $withSuffix
    ): string {
        $flag = $withSuffix ? 'ws' : 'ns';

        return $patternId.'@'.$flag;
    }

    /**
     * Split on ASCII spaces and hyphens; each segment contributes first letter
     * plus a period (e.g. "James Earl" → "J. E.", "Mary-Jane" → "M. J.").
     */
    public static function initials(string $segment): string
    {
        $t = \trim($segment);

        // Splitting '' gives no parts, so without this return the code below returns '' as well
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (
            // A blank segment that falls through splits into no parts and still returns ''
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $t === ''
        ) {
            return '';
        }

        $parts = \preg_split(
            '/[\s\-]+/u',
            $t,
            // A preg_split() limit of 0 also means no limit
            // @pest-mutate-ignore: IncrementInteger
            -1,
            \PREG_SPLIT_NO_EMPTY
        );

        if ($parts === false) {
            return '';
        }

        $initials = [];

        foreach ($parts as $part) {
            $ch = \mb_substr(
                $part,
                0,
                1
            );

            $initials[] = $ch.'.';
        }

        unset($parts, $part, $ch);

        return \implode(
            ' ',
            $initials
        );
    }

    public static function joinParts(?string ...$parts): string
    {
        $filtered = [];

        foreach ($parts as $p) {
            if ($p !== null && \trim($p) !== '') {
                $filtered[] = \trim($p);
            }
        }

        unset($parts, $p);

        return \implode(
            ' ',
            $filtered
        );
    }

    /**
     * Join space-grouped given-name IPA with last-name IPA. When there is IPA for a
     * given-name group before the nickname, the nickname IPA is set off with ` | ` on
     * both sides; when there is no such prefix (nickname-only-before-surname display),
     * nickname and last-name IPA are space-joined without ` | ` between them.
     *
     * @param  string  $beforeLastJoined  Space-joined phonetic segments before the nickname (may be empty)
     * @param  string|null  $nickPhonetic  Non-empty nickname IPA, or null/empty when absent
     * @param  string  $lastPhonetic  Last-name IPA (may be empty)
     */
    private static function joinPhoneticBeforeNicknameLast(
        string $beforeLastJoined,
        ?string $nickPhonetic,
        string $lastPhonetic
    ): string {
        // Every caller passes trimmed text
        // @pest-mutate-ignore: UnwrapTrim
        $before = \trim($beforeLastJoined);
        // Every caller passes trimmed text
        // @pest-mutate-ignore: UnwrapTrim
        $nick = $nickPhonetic !== null ? \trim($nickPhonetic) : '';
        // Every caller passes trimmed text
        // @pest-mutate-ignore: UnwrapTrim
        $last = \trim($lastPhonetic);
        $sep = ' | ';

        if ($nick === '') {
            return self::joinParts(
                $before,
                $last
            );
        }

        if ($before === '') {
            // joinParts($nick, '') is the already trimmed $nick
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            return $last === ''
                ? $nick
                : self::joinParts(
                    $nick,
                    $last
                );
        }

        if ($last === '') {
            return $before.$sep.$nick;
        }

        return $before.$sep.$nick.$sep.$last;
    }

    public static function appendSuffix(
        string $base,
        ?string $suffix
    ): string {
        $b = \trim($base);

        if ($suffix === null || \trim($suffix) === '') {
            return $b;
        }

        if ($b === '') {
            return \trim($suffix);
        }

        return $b.' '.\trim($suffix);
    }

    /**
     * IPA pronunciations for isolated English letter names (initial-style phonetic
     * preview).
     *
     * @var array<string, string>
     */
    private const array ENGLISH_LETTER_IPA = [
        'A' => 'eɪ',
        'B' => 'bi',
        'C' => 'si',
        'D' => 'di',
        'E' => 'i',
        'F' => 'ɛf',
        'G' => 'ʤi',
        'H' => 'eɪʧ',
        'I' => 'aɪ',
        'J' => 'ʤeɪ',
        'K' => 'keɪ',
        'L' => 'ɛl',
        'M' => 'ɛm',
        'N' => 'ɛn',
        'O' => 'oʊ',
        'P' => 'pi',
        'Q' => 'kju',
        'R' => 'ɑr',
        'S' => 'ɛs',
        'T' => 'ti',
        'U' => 'ju',
        'V' => 'vi',
        'W' => 'ˈdʌbəlju',
        'X' => 'ɛks',
        'Y' => 'waɪ',
        'Z' => 'zi',
    ];

    /**
     * IPA for generational suffix labels when no stored phonetic exists.
     *
     * @var array<string, string>
     */
    private const array PEDIGREE_LABEL_IPA = [
        'jr' => 'ˈʤunjər',
        'sr' => 'ˈsinjər',
        'ii' => 'ðə ˈsɛkənd',
        'iii' => 'ðə θɜrd',
        'iv' => 'ðə fɔrθ',
        'v' => 'ðə fɪfθ',
        'vi' => 'ðə sɪksθ',
        'vii' => 'ðə ˈsɛvənθ',
        'viii' => 'ðə eɪtθ',
        'ix' => 'ðə naɪnθ',
        'x' => 'ðə tɛnθ',
    ];

    /**
     * Space-separated letter IPAs for each whitespace-delimited word in display text
     * (initial-style phonetic segments).
     *
     * @param  string  $displayText  Plain-text name segment (e.g. first or middle)
     * @return string Space-separated letter IPAs, or empty when none apply
     */
    public static function initialsIpaFromDisplayText(string $displayText): string
    {
        $words = \preg_split(
            '/\s+/u',
            \trim($displayText),
            // A preg_split() limit of 0 also means no limit
            // @pest-mutate-ignore: IncrementInteger
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if ($words === false || $words === []) {
            return '';
        }

        $parts = [];

        foreach ($words as $word) {
            $first = \mb_substr(
                $word,
                0,
                1,
                'UTF-8'
            );
            $upper = \mb_strtoupper(
                $first,
                'UTF-8'
            );

            if (isset(self::ENGLISH_LETTER_IPA[$upper])) {
                $parts[] = self::ENGLISH_LETTER_IPA[$upper];
            }
        }

        return \implode(
            ' ',
            $parts
        );
    }

    /**
     * Map a generational suffix label to IPA when stored phonetic is absent.
     *
     * @param  string  $text  Resolved suffix label (e.g. "Jr.", "III")
     * @return string IPA string, or empty when unknown
     */
    public static function generationalSuffixPhoneticFromLabel(string $text): string
    {
        $trimmed = \mb_strtolower(\trim($text));

        $key = \str_replace(
            [
                ' ',
                '.',
            ],
            '',
            $trimmed
        );

        // No suffix label maps from '', so without this return the lookup below also returns ''
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (
            // A blank key that falls through misses the lookup below, which returns ''
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $key === ''
        ) {
            return '';
        }

        return self::PEDIGREE_LABEL_IPA[$key] ?? '';
    }

    /**
     * Parse a stored `display_format` key into pattern id and suffix flag.
     *
     * @return array{pattern: string, with_suffix: bool}|null
     */
    public static function parseDisplayFormatKey(?string $key): ?array
    {
        // '' has no @, so the next check returns null too
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if ($key === null || $key === '') {
            return null;
        }

        if (! \str_contains($key, '@')) {
            return null;
        }

        $parts = \explode(
            '@',
            $key,
            2
        );

        // With '@' guaranteed present above, explode(..., 2) always yields two
        // parts, so the pattern segment and the suffix flag are both present.
        if (
            $parts[0] === ''
            || ($parts[1] !== 'ws' && $parts[1] !== 'ns')
        ) {
            return null;
        }

        return [
            'pattern' => $parts[0],
            'with_suffix' => $parts[1] === 'ws',
        ];
    }

    /**
     * Resolve which `display_format` key applies: the stored key when allowed, else
     * the first option from `buildOptions()`.
     *
     * @param  array<string, mixed>  $normalized  Output of `normalizeValue()`
     * @return string|null Effective key, or null when no options exist
     */
    public static function resolveEffectiveDisplayFormatKey(
        array $normalized,
        ?string $displayFormatKey
    ): ?string {
        $opts = self::buildOptions($normalized);

        if ($opts === []) {
            return null;
        }

        // An empty key matches no option, so the first option is used either way
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $key = \is_string($displayFormatKey)
            ? \trim($displayFormatKey)
            : '';

        // An empty key matches no option, so the first option is used either way
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if ($key !== '') {
            foreach ($opts as $o) {
                if ($o['key'] === $key) {
                    return $o['key'];
                }
            }
        }

        return $opts[0]['key'];
    }

    /**
     * Display text for a normalized person name: preferred format when prerequisites
     * are met, otherwise a space-joined fallback from raw parts.
     *
     * @param  array<string, mixed>  $normalized  Output of `normalizeValue()`
     * @param  string|null  $displayFormatKey  Stored `display_format` value
     * @return string Resolved display string
     */
    public static function resolvedDisplayText(
        array $normalized,
        ?string $displayFormatKey
    ): string {
        if (self::isReadyForDisplayFormatOptions($normalized)) {
            $opts = self::buildOptions($normalized);

            if ($opts !== []) {
                // An empty key matches no option, so the first option is used either way
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                $key = \is_string($displayFormatKey)
                    ? \trim($displayFormatKey)
                    : '';

                // An empty key matches no option, so the first option is used either way
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                if ($key !== '') {
                    foreach ($opts as $o) {
                        if ($o['key'] === $key) {
                            return $o['label'];
                        }
                    }
                }

                return $opts[0]['label'];
            }
        }

        return self::buildPartialDisplayText($normalized);
    }

    /**
     * Fallback display text from name parts (no pattern-specific initials), used when
     * `display_format` options are not yet available.
     *
     * @param  array<string, mixed>  $normalized  Output of `normalizeValue()`
     * @return string Space-joined segments
     */
    public static function buildPartialDisplayText(array $normalized): string
    {
        $n = $normalized;
        $segments = [];

        $first = \trim(self::pairSubValue($n, 'first_name', 'text'));
        if ($first !== '') {
            $segments[] = $first;
        }

        // The flag is only read through !, which casts it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        if (! (bool) ($n['no_middle_name'] ?? false)) {
            $middle = \trim(self::pairSubValue($n, 'middle_name', 'text'));
            if ($middle !== '') {
                $segments[] = $middle;
            }
        }

        // The flag is only read through !, which casts it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        if (! (bool) ($n['no_nickname'] ?? false)) {
            $nick = \trim(self::pairSubValue($n, 'nickname', 'text'));
            if ($nick !== '') {
                $segments[] = '"'.$nick.'"';
            }
        }

        $last = \trim(self::pairSubValue($n, 'last_name', 'text'));
        if ($last !== '') {
            $segments[] = $last;
        }

        // The flag is only read through !, which casts it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        if (! (bool) ($n['no_generational_suffix'] ?? false)) {
            $suf = $n['generational_suffix'] ?? null;
            $sufStr = \is_string($suf) ? \trim($suf) : '';
            if ($sufStr !== '') {
                $segments[] = $sufStr;
            }
        }

        return \implode(
            ' ',
            $segments
        );
    }

    /**
     * IPA for computed name fields and `alternate_name_list`: strip ASCII `"` used as
     * nickname delimiters in internal joins. Display text may quote nicknames; phonetic
     * output does not wrap nickname IPA in quotes.
     *
     * @param  string  $phonetic  Raw joined IPA from pattern or partial phonetic builders
     * @return string IPA without straight double-quote characters
     */
    public static function normalizeNamePhoneticForComputedOutput(
        string $phonetic
    ): string {
        return \str_replace(
            '"',
            '',
            $phonetic
        );
    }

    /**
     * Legal-order IPA for `full_name` / `full_name_with_*` computeds (partial segments,
     * no initial-style letter IPA), with nickname segments normalized for output.
     *
     * @param  array<string, mixed>  $normalized  Output of `normalizeValue()`
     * @return string Space-joined IPA segments
     */
    public static function legalOrderPhoneticTextForComputedField(
        array $normalized
    ): string {
        return self::normalizeNamePhoneticForComputedOutput(
            self::buildPartialPhoneticText($normalized)
        );
    }

    /**
     * Phonetic text aligned with `resolvedDisplayText()` when a pattern applies; else
     * full phonetic segments (no initial-style letter IPA), matching legacy
     * `full_name_phonetic_text` behavior.
     *
     * @param  array<string, mixed>  $normalized  Output of `normalizeValue()`
     * @param  string|null  $displayFormatKey  Stored `display_format` value
     * @return string Space-joined IPA segments
     */
    public static function resolvedPhoneticText(
        array $normalized,
        ?string $displayFormatKey
    ): string {
        if (self::isReadyForDisplayFormatOptions($normalized)) {
            $resolvedKey = self::resolveEffectiveDisplayFormatKey(
                $normalized,
                $displayFormatKey
            );

            if ($resolvedKey !== null) {
                $phonetic = self::phoneticTextForPatternKey(
                    $normalized,
                    $resolvedKey
                );

                if ($phonetic !== '') {
                    return self::normalizeNamePhoneticForComputedOutput(
                        $phonetic
                    );
                }
            }
        }

        return self::normalizeNamePhoneticForComputedOutput(
            self::buildPartialPhoneticText($normalized)
        );
    }

    /**
     * Full phonetic segments in legal order (nickname IPA uses ` | ` only when IPA for
     * first/middle precedes it; no ASCII `"` wrappers), used for `full_name_phonetic_text`
     * and as fallback for `display_name_full_phonetic_text`.
     *
     * @param  array<string, mixed>  $normalized  Output of `normalizeValue()`
     * @return string Space-joined IPA segments
     */
    public static function buildPartialPhoneticText(array $normalized): string
    {
        $n = $normalized;

        $beforeNickSegments = [];

        $fT = \trim(self::pairSubValue($n, 'first_name', 'text'));
        // joinParts() trims this phonetic again and drops it when blank
        // @pest-mutate-ignore: UnwrapTrim
        $fP = \trim(self::pairSubValue($n, 'first_name', 'phonetic_text'));
        // joinParts() drops an empty phonetic, so only the text is checked
        if ($fT !== '') {
            $beforeNickSegments[] = $fP;
        }

        // The flag is only read through !, which casts it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        if (! (bool) ($n['no_middle_name'] ?? false)) {
            $mT = \trim(self::pairSubValue($n, 'middle_name', 'text'));
            // joinParts() trims this phonetic again and drops it when blank
            // @pest-mutate-ignore: UnwrapTrim
            $mP = \trim(self::pairSubValue($n, 'middle_name', 'phonetic_text'));
            // joinParts() drops an empty phonetic, so only the text is checked
            if ($mT !== '') {
                $beforeNickSegments[] = $mP;
            }
        }

        $nickIpa = null;
        // The flag is only read through !, which casts it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        if (! (bool) ($n['no_nickname'] ?? false)) {
            // joinPhoneticBeforeNicknameLast() trims the nickname phonetic again and reads blank as none
            // @pest-mutate-ignore: UnwrapTrim
            $nP = \trim(self::pairSubValue($n, 'nickname', 'phonetic_text'));
            // joinPhoneticBeforeNicknameLast() reads '' as no nickname
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($nP !== '') {
                $nickIpa = $nP;
            }
        }

        // joinPhoneticBeforeNicknameLast() trims the last phonetic again
        // @pest-mutate-ignore: UnwrapTrim
        $lP = \trim(self::pairSubValue($n, 'last_name', 'phonetic_text'));

        $base = self::joinPhoneticBeforeNicknameLast(
            self::joinParts(...$beforeNickSegments),
            $nickIpa,
            $lP
        );

        // The flag is only read through !, which casts it to bool anyway
        // @pest-mutate-ignore: RemoveBooleanCast
        if (! (bool) ($n['no_generational_suffix'] ?? false)) {
            $suf = $n['generational_suffix'] ?? null;
            // generationalSuffixPhoneticFromLabel() trims the label and maps no other text
            // @pest-mutate-ignore: EmptyStringToNotEmpty, UnwrapTrim
            $sufStr = \is_string($suf) ? \trim($suf) : '';
            if (
                // An empty label has no suffix phonetic
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                $sufStr !== ''
            ) {
                $sufIpa = self::generationalSuffixPhoneticFromLabel($sufStr);
                // appendSuffix() returns the base unchanged for an empty suffix
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                if ($sufIpa !== '') {
                    return self::appendSuffix(
                        $base,
                        $sufIpa
                    );
                }
            }
        }

        return $base;
    }

    /**
     * IPA string for a specific `display_format` key (e.g. `full_first_only@ns`).
     *
     * @param  array<string, mixed>  $normalized  Output of `normalizeValue()`
     * @return string Phonetic string, or empty when the key is invalid for the value
     */
    private static function phoneticTextForPatternKey(
        array $normalized,
        string $fullKey
    ): string {
        $parsed = self::parseDisplayFormatKey($fullKey);

        if ($parsed === null) {
            return '';
        }

        $patternId = $parsed['pattern'];
        $withSuffix = $parsed['with_suffix'];

        $n = $normalized;

        // initialsIpaFromDisplayText() trims the first name again
        // @pest-mutate-ignore: UnwrapTrim
        $first = \trim(self::pairSubValue($n, 'first_name', 'text'));
        // joinParts() trims this phonetic again
        // @pest-mutate-ignore: UnwrapTrim
        $fP = \trim(self::pairSubValue($n, 'first_name', 'phonetic_text'));
        // initialsIpaFromDisplayText() trims the middle name again
        // @pest-mutate-ignore: UnwrapTrim
        $middle = \trim(self::pairSubValue($n, 'middle_name', 'text'));
        // joinParts() trims this phonetic again
        // @pest-mutate-ignore: UnwrapTrim
        $mP = \trim(self::pairSubValue($n, 'middle_name', 'phonetic_text'));
        // A blank nickname is either not ready or switched off, so trimming never changes $hasNick
        // @pest-mutate-ignore: UnwrapTrim
        $nick = \trim(self::pairSubValue($n, 'nickname', 'text'));
        // joinPhoneticBeforeNicknameLast() trims the nickname phonetic again and reads blank as none
        // @pest-mutate-ignore: UnwrapTrim
        $nP = \trim(self::pairSubValue($n, 'nickname', 'phonetic_text'));
        // joinParts() and joinPhoneticBeforeNicknameLast() trim this phonetic again
        // @pest-mutate-ignore: UnwrapTrim
        $lP = \trim(self::pairSubValue($n, 'last_name', 'phonetic_text'));
        // The flag is only read through !, and with it on, the keys that use it are never offered
        // @pest-mutate-ignore: RemoveBooleanCast, CoalesceRemoveLeft
        $noNick = (bool) ($n['no_nickname'] ?? false);
        // The flag is only read through !, and with it on, the keys that use it are never offered
        // @pest-mutate-ignore: RemoveBooleanCast, CoalesceRemoveLeft
        $noGen = (bool) ($n['no_generational_suffix'] ?? false);
        $suffixRaw = $n['generational_suffix'] ?? null;
        // generationalSuffixPhoneticFromLabel() trims the label and maps no other text, so $applySuffix is unchanged
        // @pest-mutate-ignore: EmptyStringToNotEmpty, UnwrapTrim
        $suffixStr = \is_string($suffixRaw) ? \trim($suffixRaw) : '';
        // Nickname keys are only offered when the nickname is on and set, so $hasNick only matters then
        // @pest-mutate-ignore: BooleanAndToBooleanOr, EmptyStringToNotEmpty
        $hasNick = ! $noNick && $nick !== '';

        // With-suffix keys are only offered when the suffix is on and set, so $hasSuffix only matters then
        // @pest-mutate-ignore: BooleanAndToBooleanOr, EmptyStringToNotEmpty
        $hasSuffix = ! $noGen && $suffixStr !== '';
        // $applySuffix also needs $hasSuffix, so this default is never used
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $suffixIpa = $hasSuffix
            ? self::generationalSuffixPhoneticFromLabel($suffixStr)
            : '';
        // appendSuffix() returns the base unchanged for an empty suffix
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $applySuffix = $withSuffix && $hasSuffix && $suffixIpa !== '';

        $fI = self::initialsIpaFromDisplayText($first);
        $mI = self::initialsIpaFromDisplayText($middle);
        // Only nickname keys read this, they need $hasNick, and '' reads as no nickname
        // @pest-mutate-ignore: BooleanAndToBooleanOr, EmptyStringToNotEmpty
        $qNickPhonetic = ($hasNick && $nP !== '')
            ? $nP
            : null;

        $base = match ($patternId) {
            'full_first_full_middle' => self::joinParts(
                $fP,
                $mP,
                $lP
            ),
            'full_first_full_middle_nickname' => self::joinPhoneticBeforeNicknameLast(
                self::joinParts(
                    $fP,
                    $mP
                ),
                $qNickPhonetic,
                $lP
            ),
            'full_first_middle_initial' => self::joinParts(
                $fP,
                $mI,
                $lP
            ),
            'full_first_middle_initial_nickname' => self::joinPhoneticBeforeNicknameLast(
                self::joinParts(
                    $fP,
                    $mI
                ),
                $qNickPhonetic,
                $lP
            ),
            'first_initial_full_middle' => self::joinParts(
                $fI,
                $mP,
                $lP
            ),
            'first_initial_full_middle_nickname' => self::joinPhoneticBeforeNicknameLast(
                self::joinParts(
                    $fI,
                    $mP
                ),
                $qNickPhonetic,
                $lP
            ),
            'middle_only' => self::joinParts(
                $mP,
                $lP
            ),
            'middle_only_nickname' => self::joinPhoneticBeforeNicknameLast(
                self::joinParts($mP),
                $qNickPhonetic,
                $lP
            ),
            'first_initial_middle_initial' => self::joinParts(
                $fI,
                $mI,
                $lP
            ),
            'first_initial_middle_initial_nickname' => self::joinPhoneticBeforeNicknameLast(
                self::joinParts(
                    $fI,
                    $mI
                ),
                $qNickPhonetic,
                $lP
            ),
            'full_first_only' => self::joinParts(
                $fP,
                $lP
            ),
            'full_first_only_nickname' => self::joinPhoneticBeforeNicknameLast(
                self::joinParts($fP),
                $qNickPhonetic,
                $lP
            ),
            'nickname_only' => self::joinPhoneticBeforeNicknameLast(
                '',
                $qNickPhonetic,
                $lP
            ),
            default => '',
        };

        if ($base === '') {
            return '';
        }

        return $applySuffix
            ? self::appendSuffix(
                $base,
                $suffixIpa
            )
            : $base;
    }

    /**
     * Merge `text` / `phonetic_text` pairs by trimmed `text`: for each distinct text,
     * use the first non-empty non-null `phonetic_text` in encounter order (same rule as
     * nickname deduplication in `ComputedPropertiesProvider`).
     *
     * @param  list<array<string, mixed>>  $pairArrays  Raw `first_name`- or
     *                                                  `middle_name`-shaped arrays
     * @return array<string, string|null> Map trimmed text to chosen phonetic, or null
     */
    public static function mergedPhoneticTextByTrimmedText(
        array $pairArrays
    ): array {
        // Preserve first-seen text order for stable map iteration
        $textOrder = [];

        // Collect ordered phonetic candidates per distinct text
        $textToPhoneticsInOrder = [];

        foreach ($pairArrays as $pair) {
            // Resolve trimmed display text for the pair
            $textRaw = $pair['text'] ?? null;
            $text = \is_string($textRaw)
                ? \trim($textRaw)
                : (\is_scalar($textRaw) ? (string) $textRaw : '');

            // Skip blank text keys
            if ($text === '') {
                continue;
            }

            // Resolve phonetic candidate (may be null)
            $pRaw = $pair['phonetic_text'] ?? null;

            // The candidate is trimmed again when it is checked and chosen below
            // @pest-mutate-ignore: UnwrapTrim
            $phoneticCandidate = \is_string($pRaw)
                ? \trim($pRaw)
                : (\is_scalar($pRaw) ? (string) $pRaw : null);

            // The candidate is already trimmed, and a blank one is skipped when choosing below
            // @pest-mutate-ignore: EmptyStringToNotEmpty, UnwrapTrim
            if (
                $phoneticCandidate !== null
                && \trim($phoneticCandidate) === ''
            ) {
                $phoneticCandidate = null;
            }

            // Record first-seen order for each distinct text
            if (! \array_key_exists(
                $text,
                $textToPhoneticsInOrder
            )) {
                $textOrder[] = $text;
                $textToPhoneticsInOrder[$text] = [];
            }

            // Append this occurrence's phonetic for later winner selection
            $textToPhoneticsInOrder[$text][] = $phoneticCandidate;
        }

        // Choose the first non-empty phonetic in encounter order per text
        $out = [];

        foreach ($textOrder as $text) {
            // Candidates are trimmed and a blank one is null by now
            $chosen = \array_find(
                $textToPhoneticsInOrder[$text],
                static fn (?string $candidate): bool => $candidate !== null
            );
            $out[$text] = $chosen;
        }

        return $out;
    }

    /**
     * Build a `normalizeValue()`-compatible array from alternate-name expansion parts
     * (no stored `display_format`; flags derived from non-empty text segments).
     *
     * @param  array<string, mixed>  $firstName
     * @param  array<string, mixed>  $middleName
     * @param  array<string, mixed>  $nickname
     * @param  array<string, mixed>  $lastName
     * @return array<string, mixed>
     */
    public static function normalizedFromAlternateNameShell(
        array $firstName,
        array $middleName,
        array $nickname,
        array $lastName,
        mixed $generationalSuffix
    ): array {
        $pair = static function (array $p): array {
            $tRaw = $p['text'] ?? null;
            $t = \is_string($tRaw)
                ? \trim($tRaw)
                : (\is_scalar($tRaw) ? (string) $tRaw : '');
            $phRaw = $p['phonetic_text'] ?? null;
            $ph = \is_string($phRaw)
                ? \trim($phRaw)
                : (\is_scalar($phRaw) ? (string) $phRaw : '');

            return [
                'text' => $t,
                'phonetic_text' => $ph,
            ];
        };

        $first = $pair($firstName);
        $middle = $pair($middleName);
        $nick = $pair($nickname);
        $last = $pair($lastName);

        $suffixStr = \is_string($generationalSuffix)
            ? \trim($generationalSuffix)
            : '';

        $noMiddle = $middle['text'] === '';
        $noNick = $nick['text'] === '';
        $noGen = $suffixStr === '';

        return [
            'first_name' => $first,
            'no_middle_name' => $noMiddle,
            'middle_name' => $middle,
            'no_nickname' => $noNick,
            'nickname' => $nick,
            'last_name' => $last,
            'no_generational_suffix' => $noGen,
            'generational_suffix' => $noGen ? null : $suffixStr,
            'display_format' => null,
        ];
    }

    /**
     * Build display (and optional phonetic) strings for every `display_format` option
     * that would appear in the CP, optionally skipping one stored key (e.g. the entry's
     * current format). When options are not yet available, falls back to a single
     * partial display row.
     *
     * @param  array<string, mixed>  $normalized  Output of `normalizeValue()` or
     *                                            `normalizedFromAlternateNameShell()`
     * @param  string|null  $skipDisplayFormatKey  Stored `display_format` key to omit
     * @param  bool  $generatePhoneticText  Whether to compute pattern IPA (false yields
     *                                      null phonetic fields)
     * @return list<array{text: string, phonetic_text: string|null}> Each phonetic string
     *                                                               uses `normalizeNamePhoneticForComputedOutput()`.
     */
    public static function alternateNameDisplayExpansionRows(
        array $normalized,
        ?string $skipDisplayFormatKey,
        bool $generatePhoneticText
    ): array {
        // Normalize the skip key for comparison
        // An empty skip key matches no option key
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $skip = \is_string($skipDisplayFormatKey)
            ? \trim($skipDisplayFormatKey)
            : '';

        $rows = [];

        // Prefer full pattern options when the name is complete enough
        if (self::isReadyForDisplayFormatOptions($normalized)) {
            $opts = self::buildOptions($normalized);

            foreach ($opts as $o) {
                // Skip the variation that matches the entry's stored format key
                // An empty skip key matches no option key
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                if (
                    $skip !== ''
                    && $o['key'] === $skip
                ) {
                    continue;
                }

                // Resolve IPA for this pattern when requested
                $phonetic = '';

                if ($generatePhoneticText) {
                    // Pattern IPA uses nickname pipe delimiters; strip any legacy `"` for output
                    $phonetic = self::normalizeNamePhoneticForComputedOutput(
                        self::phoneticTextForPatternKey(
                            $normalized,
                            $o['key']
                        )
                    );
                }

                $rows[] = [
                    'text' => $o['label'],
                    'phonetic_text' => $phonetic !== ''
                        ? $phonetic
                        : null,
                ];
            }

            return $rows;
        }

        // Fallback to partial display when pattern options are not available
        $partialText = self::buildPartialDisplayText($normalized);

        if ($partialText === '') {
            return [];
        }

        $phoneticOut = null;

        if ($generatePhoneticText) {
            $partialPhonetic = self::legalOrderPhoneticTextForComputedField(
                $normalized
            );

            if ($partialPhonetic !== '') {
                $phoneticOut = $partialPhonetic;
            }
        }

        $rows[] = [
            'text' => $partialText,
            'phonetic_text' => $phoneticOut,
        ];

        return $rows;
    }
}
