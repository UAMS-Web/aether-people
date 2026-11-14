<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use UAMSWeb\Aether\People\Support\PersonNameComposite;
use UAMSWeb\Aether\People\Support\PersonNameDisplayFormats;

/**
 * Person-name Statamic computed field values for providers and people entries.
 *
 * Resolves display strings, IPA phonetics, nickname and alternate name expansions,
 * and credential-augmented variants from `person_name` and related fields.
 */
class ProviderPeoplePersonNameComputedValues
{
    /**
     * International Phonetic Alphabet for the spoken "doctor" honorific, used when
     * credentials imply a "Dr." prefix in phonetic computeds.
     */
    private const string DOCTOR_HONORIFIC_IPA = 'ˈdɑktər';

    /**
     * Read the `phonetic_text` subfield from a name component (group or legacy
     * string).
     *
     * @param  mixed  $value  Raw value from `Entry::get()` for a name component
     * @return string Phonetic text, or empty string when absent
     */
    private function resolveNamePhoneticSubfieldValue(
        mixed $value
    ): string {
        // Return early if the value is null or empty
        if (
            $value === null
            || $value === ''
        ) {
            return '';
        }

        // Return early if the value is a string
        if (\is_string($value)) {
            return '';
        }

        // Check if the value is a collection
        if ($value instanceof IlluminateCollection) {
            // Recursively resolve the phonetic value from the collection
            return $this->resolveNamePhoneticSubfieldValue($value->all());
        }

        // Check if the value is an array
        if (\is_array($value)) {
            // Get the phonetic value from the array
            $phonetic = $value['phonetic_text'] ?? null;

            // Return early if the phonetic value is null or empty
            if (
                $phonetic === null
                || $phonetic === ''
            ) {
                return '';
            }

            // Return the phonetic value if it is a string
            return \is_string($phonetic)
                ? $phonetic
                : (\is_scalar($phonetic) ? (string) $phonetic : '');
        }

        // Return an empty string
        return '';
    }

    /**
     * Normalize the `person_name` field for `PersonNameDisplayFormats` helpers.
     *
     * @param  Entry  $entry  The providers or people entry
     * @return array<string, mixed> Normalized person name array
     */
    private function getNormalizedPersonName(
        Entry $entry
    ): array {
        // Get the person name, joined with its refinement fields (UAMS-Web/uams-statamic-archive#3186)
        $raw = PersonNameComposite::fromEntry($entry);

        // Narrow the raw person name to a typed array for normalization
        /** @var array<string, mixed>|null $rawPersonName */
        $rawPersonName = \is_array($raw) ? $raw : null;

        // Normalize the raw person name
        return PersonNameDisplayFormats::normalizeValue(
            $rawPersonName
        );
    }

    /**
     * Whether normalized primary `person_name` satisfies required subfields for
     * computed name outputs (same gate as display-format options in the CP).
     *
     * @param  array<string, mixed>  $normalized  Output of `getNormalizedPersonName()`
     * @return bool True when required subfields are present for name computeds
     */
    private function normalizedPrimaryPersonNameIsCompleteForComputedNames(
        array $normalized
    ): bool {
        // Return true if the normalized person name is ready for display format options
        return PersonNameDisplayFormats::isReadyForDisplayFormatOptions(
            $normalized
        );
    }

    /**
     * Build a `{ text, phonetic_text }` pair for computed name fields.
     *
     * @param  string  $text  Display string
     * @param  string  $phonetic  Raw phonetic string (empty becomes null)
     * @return array{text: string, phonetic_text: string|null} Paired display string and IPA; phonetic is null when trimmed empty
     */
    private function computeComputedTextPhoneticPair(
        string $text,
        string $phonetic
    ): array {
        // Trim the phonetic text
        $phoneticTrimmed = \trim($phonetic);

        // Return the computed text/phonetic pair
        return [
            'text' => $text,
            'phonetic_text' => $phoneticTrimmed !== ''
                ? $phoneticTrimmed
                : null,
        ];
    }

    /**
     * `prefix` computed: "Dr." text with doctor honorific IPA when credentials apply.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return array{text: string, phonetic_text: string|null} Paired "Dr." label and doctor honorific IPA when credentials apply
     */
    public function getPrefixValue(
        Entry $entry
    ): array {
        // Get the prefix text from the entry
        $text = $this->getPrefix($entry);

        // Get the prefix phonetic from the text
        $phonetic = $text !== ''
            ? self::DOCTOR_HONORIFIC_IPA
            : '';

        // Return the computed text/phonetic pair
        return $this->computeComputedTextPhoneticPair(
            $text,
            $phonetic
        );
    }

    /**
     * `legal_name_pairs` computed: each legal name part with its pronunciation.
     *
     * A part's pronunciation is kept in `name_pronunciation` beside
     * `person_name` (UAMS-Web/uams-statamic-archive#3186), so a template that pronounces one part reads it
     * from here rather than from `person_name` itself. Each part is passed on
     * as stored, so the pronounced output is the one the template produced
     * when the pronunciation was kept inside `person_name`.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return array<string, mixed> The `first_name`, `middle_name` and
     *                              `last_name` text/phonetic rows, each null
     *                              when the name has no such part
     */
    public function getLegalNamePairs(
        Entry $entry
    ): array {
        // Join the legal name with the pronunciation kept beside it
        $personName = PersonNameComposite::fromEntry($entry) ?? [];

        // Collect each pronounced part as stored
        $pairs = [];

        foreach (PersonNameComposite::PRONOUNCED_PARTS as $part) {
            $pairs[$part] = \is_array($personName[$part] ?? null)
                ? $personName[$part]
                : null;
        }

        return $pairs;
    }

    /**
     * Computes `display_name_full` as `text` plus pattern-aligned `phonetic_text`.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return array{text: string, phonetic_text: string|null} Paired display-order full name and pattern-aligned IPA
     */
    public function getDisplayNameFull(
        Entry $entry
    ): array {
        // Return the computed text/phonetic pair
        return $this->computeComputedTextPhoneticPair(
            $this->resolveDisplayNameFullText($entry),
            $this->resolveDisplayNameFullPhoneticText($entry)
        );
    }

    /**
     * Resolves display text for `display_name_full` (stored `display_format` when
     * valid).
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return string Display-order full name text for the configured format
     */
    private function resolveDisplayNameFullText(
        Entry $entry
    ): string {
        // Get the normalized person name from the entry
        $normalized = $this->getNormalizedPersonName($entry);

        // Return early if the normalized person name is not complete for computed names
        if (! $this->normalizedPrimaryPersonNameIsCompleteForComputedNames(
            $normalized
        )) {
            return '';
        }

        // Get the display format key from the normalized person name
        $displayFormatKey = $normalized['display_format'] ?? null;

        // Get the key from the display format key
        $key = \is_string($displayFormatKey) ? $displayFormatKey : null;

        // Return the resolved display text
        return PersonNameDisplayFormats::resolvedDisplayText(
            $normalized,
            $key
        );
    }

    /**
     * Resolves phonetic text aligned with `resolveDisplayNameFullText()`.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return string IPA text aligned with display name full
     */
    private function resolveDisplayNameFullPhoneticText(
        Entry $entry
    ): string {
        // Get the normalized person name from the entry
        $normalized = $this->getNormalizedPersonName($entry);

        // Return early if the normalized person name is not complete for computed names
        if (! $this->normalizedPrimaryPersonNameIsCompleteForComputedNames(
            $normalized
        )) {
            return '';
        }

        // Get the display format key from the normalized person name
        $displayFormatKey = $normalized['display_format'] ?? null;

        // Get the key from the display format key
        $key = \is_string($displayFormatKey) ? $displayFormatKey : null;

        // Return the resolved phonetic text
        return PersonNameDisplayFormats::resolvedPhoneticText(
            $normalized,
            $key
        );
    }

    /**
     * Computes `full_name` as legal-order text and phonetic segments.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return array{text: string, phonetic_text: string|null} Paired legal-order full name and IPA segments
     */
    public function getFullName(
        Entry $entry
    ): array {
        // Return the computed text/phonetic pair
        return $this->computeComputedTextPhoneticPair(
            $this->resolveFullNameText($entry),
            $this->resolveFullNamePhoneticText($entry)
        );
    }

    /**
     * Legal-style display text (full segments; ignores pattern initials).
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return string Legal-order display text for full name computeds
     */
    private function resolveFullNameText(
        Entry $entry
    ): string {
        // Get the normalized person name from the entry
        $normalized = $this->getNormalizedPersonName($entry);

        // Return early if the normalized person name is not complete for computed names
        if (! $this->normalizedPrimaryPersonNameIsCompleteForComputedNames(
            $normalized
        )) {
            return '';
        }

        // Return the resolved display text
        return PersonNameDisplayFormats::buildPartialDisplayText($normalized);
    }

    /**
     * Legal-order phonetic text (no initial-style letter IPA).
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return string Space-joined IPA for legal-order full name
     */
    private function resolveFullNamePhoneticText(
        Entry $entry
    ): string {
        // Get the normalized person name from the entry
        $normalized = $this->getNormalizedPersonName($entry);

        // Return early if the normalized person name is not complete for computed names
        if (! $this->normalizedPrimaryPersonNameIsCompleteForComputedNames(
            $normalized
        )) {
            return '';
        }

        // Return the resolved phonetic text
        return PersonNameDisplayFormats::legalOrderPhoneticTextForComputedField(
            $normalized
        );
    }

    /**
     * Computed list of nickname `{ text, phonetic_text }` entries for the entry.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return list<array{text: string, phonetic_text: string|null}> Ordered nickname rows for the `nickname_list` computed field
     */
    public function getNicknameList(
        Entry $entry
    ): array {
        // Collect raw nickname payloads from `person_name` and alternate name sets
        $rawNicknames = $this->collectRawNicknameValuesFromEntry($entry);

        // ---
        // Normalize each value to a text/phonetic pair, skipping invalid shapes
        // ---

        // Initialize an empty array for the normalized nicknames
        $normalized = [];

        // Loop through each raw nickname
        foreach ($rawNicknames as $rawNickname) {
            // Normalize one nickname value when it is structurally usable
            $one = $this->normalizeOneNicknameRawValue($rawNickname);

            // Keep non-null normalized nicknames for merging
            if ($one !== null) {
                $normalized[] = $one;
            }
        }

        // Merge duplicates by text and preserve first-seen text order
        return $this->dedupeNicknamesByTextPreservingOrder($normalized);
    }

    /**
     * Computed list of display text / phonetic rows for alternate naming variations.
     *
     * Omits the stored primary `display_format` option only when the legal-name shell
     * exists solely on the primary `person_name` field (not also from an
     * `alternate_name_set`) and the nickname variant matches the primary nickname, so
     * other nickname variants still emit every pattern (e.g., nickname_only for
     * alternate nicknames).
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return list<array{text: string, phonetic_text: string|null}> Ordered alternate display rows for the `alternate_name_list` computed field
     */
    public function getAlternateNameList(
        Entry $entry
    ): array {
        // Collect raw person_name payloads from the entry and alternate full-name sets
        $taggedRawPersonNames = $this->collectRawPersonNameFieldValuesFromEntry(
            $entry
        );

        // ---
        // Build merged phonetic maps for first and middle name text keys
        // ---

        // Initialize an empty array for the first pairs
        $firstPairs = [];

        // Initialize an empty array for the middle pairs
        $middlePairs = [];

        // Loop through each tagged raw person name
        foreach ($taggedRawPersonNames as $tagged) {
            // Get the raw person name from the tagged raw person name
            $rawPersonName = $tagged['raw'];

            // Extract first_name pairs for cross-source phonetic merging
            /** @var array<string, mixed> $firstPair */
            $firstPair = \is_array($rawPersonName['first_name'] ?? null)
                ? $rawPersonName['first_name']
                : [];
            $firstPairs[] = $firstPair;

            // Extract middle_name pairs for cross-source phonetic merging
            /** @var array<string, mixed> $middlePair */
            $middlePair = \is_array($rawPersonName['middle_name'] ?? null)
                ? $rawPersonName['middle_name']
                : [];
            $middlePairs[] = $middlePair;
        }

        // Merge phonetics by trimmed text (first occurrence of non-empty wins)
        $mergedFirstPhonetics = PersonNameDisplayFormats::mergedPhoneticTextByTrimmedText(
            $firstPairs
        );

        // Merge phonetics by trimmed text (first occurrence of non-empty wins)
        $mergedMiddlePhonetics = PersonNameDisplayFormats::mergedPhoneticTextByTrimmedText(
            $middlePairs
        );

        // ---
        // Strip and merge each person_name shell, carrying primary vs alternate provenance
        // ---

        // Initialize an empty array for the shells with source
        $shellsWithSource = [];

        // Loop through each tagged raw person name
        foreach ($taggedRawPersonNames as $tagged) {
            // Get the raw person name from the tagged raw person name
            $rawPersonName = $tagged['raw'];

            // Strip fields that are not part of the alternate shell
            $stripped = $this->stripPersonNameFieldForAlternateExpansion(
                $rawPersonName
            );

            // Apply merged phonetics for shared first/middle text keys
            $withMerge = $this->applyMergedNamePartPhonetics(
                $stripped,
                $mergedFirstPhonetics,
                $mergedMiddlePhonetics
            );

            // Skip rows missing required first and last name text
            if (! $this->personNameShellHasRequiredLegalNames($withMerge)) {
                continue;
            }

            // Add the shell with source to the shells with source array
            $shellsWithSource[] = [
                'shell' => $withMerge,
                'from_primary' => $tagged['from_primary'],
            ];
        }

        // Deduplicate equivalent shells and merge source flags (alternate disallows skip)
        $shellsWithSkipRules = $this->dedupePersonNameShellsWithSourceFlags(
            $shellsWithSource
        );

        // ---
        // Build nickname variants including an explicit empty nickname row
        // ---

        // Get the nickname variants from the entry
        $nicknameVariants = $this->getNicknameList($entry);

        // Add an explicit empty nickname row
        $nicknameVariants[] = [
            'text' => null,
            'phonetic_text' => null,
        ];

        // ---
        // Accumulate expansion rows across all shell × nickname combinations
        // ---

        // Initialize an empty array for the all rows
        $allRows = [];

        // Loop through each shell with skip rules
        foreach ($shellsWithSkipRules as $shellRow) {
            // Get the shell from the shell row
            $shell = $shellRow['shell'];

            // Get the omit stored display format variation from the shell row
            $omitStoredDisplayFormatVariation = $shellRow['omit_stored_display_format_variation'];

            // Loop through each nickname variant
            foreach ($nicknameVariants as $nickVariant) {
                // Build one combined value object (legal shell + nickname variant)
                $combined = $this->buildCombinedAlternateNameValueObject(
                    $shell,
                    $nickVariant
                );

                // Clear phonetics when any text segment lacks a companion phonetic
                $combined = $this->applyPhoneticCoherenceToCombinedAlternateName(
                    $combined
                );

                // Decide whether pattern IPA can be generated for this combination
                $generatePhonetics = $this->combinedAlternateNameSupportsPhoneticVariations(
                    $combined
                );

                // Narrow each name-part block to an array for the typed shell arguments
                /** @var array<string, mixed> $combinedFirstName */
                $combinedFirstName = \is_array($combined['first_name'] ?? null) ? $combined['first_name'] : [];
                /** @var array<string, mixed> $combinedMiddleName */
                $combinedMiddleName = \is_array($combined['middle_name'] ?? null) ? $combined['middle_name'] : [];
                /** @var array<string, mixed> $combinedNickname */
                $combinedNickname = \is_array($combined['nickname'] ?? null) ? $combined['nickname'] : [];
                /** @var array<string, mixed> $combinedLastName */
                $combinedLastName = \is_array($combined['last_name'] ?? null) ? $combined['last_name'] : [];

                // Convert to normalized shape for PersonNameDisplayFormats
                $normalized = PersonNameDisplayFormats::normalizedFromAlternateNameShell(
                    $combinedFirstName,
                    $combinedMiddleName,
                    $combinedNickname,
                    $combinedLastName,
                    $combined['generational_suffix'] ?? null
                );

                // Omit stored format only for primary-only shells when the nickname matches
                // the primary person_name nickname (so other nickname variants keep all rows)
                $skipDisplayFormatKey = $this->resolveSkipDisplayFormatKeyForAlternateExpansion(
                    $entry,
                    $omitStoredDisplayFormatVariation,
                    $nickVariant
                );

                // Expand into display rows for this combination
                $rows = PersonNameDisplayFormats::alternateNameDisplayExpansionRows(
                    $normalized,
                    $skipDisplayFormatKey,
                    $generatePhonetics
                );

                // Loop through each row
                foreach ($rows as $row) {
                    // Add the row to the all rows array
                    $allRows[] = $row;
                }
            }
        }

        // Append free-text alternate names. A `free_text` set carries a literal
        // full-name string with no `person_name` shell, so it bypasses the
        // display-format expansion above and contributes verbatim; dedupe below
        // then folds it against any structurally-equivalent row (UAMS-Web/uams-statamic-archive#855).
        foreach ($this->collectFreeTextAlternateNamesFromEntry($entry) as $freeText) {
            $allRows[] = [
                'text' => $freeText,
                'phonetic_text' => null,
            ];
        }

        // Deduplicate the final flattened list
        return $this->dedupeAlternateNameDisplayRows($allRows);
    }

    /**
     * Read `person_name.display_format` from the entry when present.
     *
     * @param  Entry  $entry  The providers or people entry
     * @return string|null Trimmed key, or null
     */
    private function resolvePrimaryPersonNameDisplayFormatKey(
        Entry $entry
    ): ?string {
        // Get the person name, joined with its refinement fields (UAMS-Web/uams-statamic-archive#3186)
        $personName = PersonNameComposite::fromEntry($entry);

        // Check if the person name is an array
        if (! \is_array($personName)) {
            // Return null to signal that the display format key is not applicable
            return null;
        }

        // Get the display format key from the person name
        $df = $personName['display_format'] ?? null;

        // Check if the display format key is a string
        if (! \is_string($df)) {
            // Return null to signal that the display format key is not applicable
            return null;
        }

        // Trim the display format key
        $t = \trim($df);

        // Return the trimmed display format key if it is not empty, otherwise return null
        return $t === '' ? null : $t;
    }

    /**
     * Collect `person_name` arrays from the primary field and each
     * `alternate_name_set`.
     *
     * @param  Entry  $entry  The providerss or people entry
     * @return list<array{raw: array<string, mixed>, from_primary: bool}> Primary and embedded `person_name` payloads tagged by whether they came from the root field
     */
    private function collectRawPersonNameFieldValuesFromEntry(
        Entry $entry
    ): array {
        // Initialize an empty array for the output
        $out = [];

        // Get the person name, joined with its refinement fields (UAMS-Web/uams-statamic-archive#3186)
        $main = PersonNameComposite::fromEntry($entry);

        // Check if the person name is an array
        if (\is_array($main)) {
            /** @var array<string, mixed> $mainPersonName */
            $mainPersonName = $main;

            // Add the person name to the output array
            $out[] = [
                'raw' => $mainPersonName,
                'from_primary' => true,
            ];
        }

        // Walk alternate_name replicator sets
        $alternateName = $entry->get(
            'alternate_name',
            []
        );

        // Check if the alternate name is a collection
        if ($alternateName instanceof IlluminateCollection) {
            // Convert the alternate name to an array
            $alternateName = $alternateName->all();
        }

        // Check if the alternate name is an array
        if (! \is_array($alternateName)) {
            // Return the output array
            return $out;
        }

        // Loop through each alternate name set
        foreach ($alternateName as $set) {
            // Check if the set is a collection
            if ($set instanceof IlluminateCollection) {
                // Convert the set to an array
                $set = $set->all();
            }

            // Check if the set is an array
            if (! \is_array($set)) {
                // Continue to the next set
                continue;
            }

            // Only full alternate name sets embed `person_name`
            if (($set['type'] ?? null) !== 'alternate_name_set') {
                continue;
            }

            // Get the embedded person name from the set
            $embedded = $set['person_name'] ?? null;

            // Check if the embedded person name is a collection
            if ($embedded instanceof IlluminateCollection) {
                // Convert the embedded person name to an array
                $embedded = $embedded->all();
            }

            // Check if the embedded person name is an array
            if (\is_array($embedded)) {
                /** @var array<string, mixed> $embeddedPersonName */
                $embeddedPersonName = $embedded;

                // Add the embedded person name to the output array
                $out[] = [
                    'raw' => $embeddedPersonName,
                    'from_primary' => false,
                ];
            }
        }

        // Return the output array
        return $out;
    }

    /**
     * Collect the free-text alternate-name set values from the entry's
     * `alternate_name` replicator.
     *
     * A `free_text` set (added by UAMS-Web/uams-statamic-archive#802, `hide: true`) carries a literal full-name
     * string in its `text` field with no `person_name` shell, so it cannot go
     * through the structured display-format expansion the `alternate_name_set`
     * rows use - it contributes to `alternate_name_list` verbatim (UAMS-Web/uams-statamic-archive#855).
     *
     * @param  Entry  $entry  The providers or people entry
     * @return list<string> Trimmed, non-empty free-text alternate names, in order
     */
    private function collectFreeTextAlternateNamesFromEntry(
        Entry $entry
    ): array {
        // Walk the alternate_name replicator sets
        $alternateName = $entry->get(
            'alternate_name',
            []
        );

        // Check if the alternate name is a collection
        if ($alternateName instanceof IlluminateCollection) {
            // Convert the alternate name to an array
            $alternateName = $alternateName->all();
        }

        // Check if the alternate name is an array
        if (! \is_array($alternateName)) {
            // Return an empty list when the field is absent or malformed
            return [];
        }

        // Initialize an empty list for the collected free-text names
        $out = [];

        // Loop through each alternate name set
        foreach ($alternateName as $set) {
            // Check if the set is a collection
            if ($set instanceof IlluminateCollection) {
                // Convert the set to an array
                $set = $set->all();
            }

            // Check if the set is an array
            if (! \is_array($set)) {
                // Continue to the next set
                continue;
            }

            // Only free_text sets carry a literal alternate-name string
            if (($set['type'] ?? null) !== 'free_text') {
                continue;
            }

            // Get the literal text value from the set
            $text = $set['text'] ?? null;

            // Skip when the text is not a string
            if (! \is_string($text)) {
                continue;
            }

            // Trim the text value
            $trimmed = \trim($text);

            // Collect the value only when it is non-empty
            if ($trimmed !== '') {
                $out[] = $trimmed;
            }
        }

        // Return the collected free-text names
        return $out;
    }

    /**
     * Deduplicate legal-name shells; if the same shell appears from the replicator,
     * never omit the stored `display_format` variation for that shell.
     *
     * @param  list<array{shell: array<string, mixed>, from_primary: bool}>  $shellsWithSource  Shells with source flags
     * @return list<array{shell: array<string, mixed>, omit_stored_display_format_variation: bool}> Deduplicated shells with omit flag
     */
    private function dedupePersonNameShellsWithSourceFlags(
        array $shellsWithSource
    ): array {
        // ---
        // Map content hash to merged shell metadata
        // ---

        // Initialize an empty array for the by hash
        $byHash = [];

        // Loop through each shell with source
        foreach ($shellsWithSource as $row) {
            // Build a stable fingerprint for deduplication
            $key = \hash(
                'xxh128',
                (string) \json_encode($row['shell'])
            );

            // Initialize bucket for this legal-name shell
            $byHash[$key] ??= [
                'shell' => $row['shell'],
                'from_primary' => false,
                'from_alternate' => false,
            ];

            // Record whether this shell was contributed by primary or replicator data
            if ($row['from_primary']) {
                $byHash[$key]['from_primary'] = true;
            } else {
                $byHash[$key]['from_alternate'] = true;
            }
        }

        // Initialize an empty array for the output
        $out = [];

        // Loop through each merged shell
        foreach ($byHash as $merged) {
            // Skip stored format only when the shell exists solely on the primary field
            $omitStored = $merged['from_primary'] && ! $merged['from_alternate'];

            // Add the merged shell to the output array
            $out[] = [
                'shell' => $merged['shell'],
                'omit_stored_display_format_variation' => $omitStored,
            ];
        }

        // Return the output array
        return $out;
    }

    /**
     * Resolve whether to pass the stored `display_format` key into expansion skip
     * logic.
     *
     * @param  Entry  $entry  The providers or people entry
     * @param  bool  $omitStoredDisplayFormatVariation  From shell deduplication
     * @param  array{text: string|null, phonetic_text: string|null}  $nickVariant  Nickname variant from nickname_list
     * @return string|null Stored display_format key, or null when skip logic is not applicable
     */
    private function resolveSkipDisplayFormatKeyForAlternateExpansion(
        Entry $entry,
        bool $omitStoredDisplayFormatVariation,
        array $nickVariant
    ): ?string {
        // Never skip when an equivalent shell exists from alternate_name_set
        if (! $omitStoredDisplayFormatVariation) {
            return null;
        }

        // Only skip when this nickname variant matches the primary person_name nickname
        if (! $this->nicknameVariantMatchesPrimaryPersonNameNickname(
            $entry,
            $nickVariant
        )) {
            return null;
        }

        // Resolve the primary person name display format key
        return $this->resolvePrimaryPersonNameDisplayFormatKey($entry);
    }

    /**
     * Whether a nickname_list variant matches the primary `person_name` nickname.
     *
     * @param  Entry  $entry  The providers or people entry
     * @param  array{text: string|null, phonetic_text: string|null}  $nickVariant  Nickname variant from nickname_list
     * @return bool True when the variant matches primary nickname semantics
     */
    private function nicknameVariantMatchesPrimaryPersonNameNickname(
        Entry $entry,
        array $nickVariant
    ): bool {
        // ---
        // Normalize primary person_name for nickname flags and text
        // ---

        // Get the normalized person name from the entry
        $normalized = $this->getNormalizedPersonName($entry);

        // Get the primary no nickname flag from the normalized person name
        $primaryNoNick = (bool) ($normalized['no_nickname'] ?? false);

        // Get the primary nickname text from the normalized person name
        $primaryNickBlock = $normalized['nickname'] ?? null;
        $primaryNickTextRaw = \is_array($primaryNickBlock) ? ($primaryNickBlock['text'] ?? '') : '';
        $primaryNickText = \trim(\is_scalar($primaryNickTextRaw) ? (string) $primaryNickTextRaw : '');

        // ---
        // Resolve variant text (explicit empty counts as no nickname text)
        // ---

        // Get the variant text from the nickname variant
        $vRaw = $nickVariant['text'];

        // Get the variant text from the nickname variant
        $variantText = \is_string($vRaw)
            ? \trim($vRaw)
            : '';

        // Get the variant empty flag from the nickname variant
        $variantEmpty = $variantText === ''
            && ($vRaw === null || $vRaw === '');

        // Primary has no nickname: only the empty variant matches
        if (
            $primaryNoNick
            || $primaryNickText === ''
        ) {
            return $variantEmpty;
        }

        // Primary has a nickname: empty variant does not match
        if ($variantEmpty) {
            return false;
        }

        // Return true if the variant text matches the primary nickname text
        return $variantText === $primaryNickText;
    }

    /**
     * Remove subfields that are not part of the alternate expansion shell.
     *
     * @param  array<string, mixed>  $raw  Raw `person_name` field value
     * @return array<string, mixed> Shell suitable for alternate-name expansion with nickname and display-format keys removed
     */
    private function stripPersonNameFieldForAlternateExpansion(
        array $raw
    ): array {
        // Initialize a copy of the raw person name
        $copy = $raw;

        // Loop through each field to drop
        foreach (
            [
                'no_middle_name',
                'no_nickname',
                'nickname',
                'no_generational_suffix',
                'display_format',
            ] as $drop
        ) {
            // Unset the field from the copy
            unset($copy[$drop]);
        }

        // Return the copy
        return $copy;
    }

    /**
     * Apply merged first/middle phonetics using trimmed text as the lookup key.
     *
     * @param  array<string, mixed>  $shell  Person name shell with alternate-only subfields
     * @param  array<string, string|null>  $mergedFirstPhonetics  First-name text key to phonetic map
     * @param  array<string, string|null>  $mergedMiddlePhonetics  Middle-name text key to phonetic map
     * @return array<string, mixed> Shell with merged phonetics
     */
    private function applyMergedNamePartPhonetics(
        array $shell,
        array $mergedFirstPhonetics,
        array $mergedMiddlePhonetics
    ): array {
        // Initialize a copy of the shell
        $out = $shell;

        // ---
        // Ensure expected subfield arrays exist
        // ---

        // Get the first name from the shell
        $out['first_name'] = \is_array($out['first_name'] ?? null)
            ? $out['first_name']
            : [];

        // Get the middle name from the shell
        $out['middle_name'] = \is_array($out['middle_name'] ?? null)
            ? $out['middle_name']
            : [];

        // Get the last name from the shell
        $out['last_name'] = \is_array($out['last_name'] ?? null)
            ? $out['last_name']
            : [];

        // ---
        // Resolve first name text for lookup
        // ---

        // Get the first name text from the shell
        $fRaw = $out['first_name']['text'] ?? null;

        // Get the first name text from the shell
        $fText = \is_string($fRaw)
            ? \trim($fRaw)
            : (\is_scalar($fRaw) ? \trim((string) $fRaw) : '');

        // Apply merged phonetic when this text key exists in the map
        if (
            $fText !== ''
            && \array_key_exists(
                $fText,
                $mergedFirstPhonetics
            )
        ) {
            $out['first_name']['phonetic_text'] = $mergedFirstPhonetics[$fText];
        }

        // ---
        // Resolve middle name text for lookup
        // ---

        // Get the middle name text from the shell
        $mRaw = $out['middle_name']['text'] ?? null;

        // Get the middle name text from the shell
        $mText = \is_string($mRaw)
            ? \trim($mRaw)
            : (\is_scalar($mRaw) ? \trim((string) $mRaw) : '');

        // Apply merged phonetic when this text key exists in the map
        if (
            $mText !== ''
            && \array_key_exists(
                $mText,
                $mergedMiddlePhonetics
            )
        ) {
            $out['middle_name']['phonetic_text'] = $mergedMiddlePhonetics[$mText];
        }

        // Return the copy
        return $out;
    }

    /**
     * Whether the shell has non-empty first and last name text.
     *
     * @param  array<string, mixed>  $shell  Person name shell used for alternate expansion
     * @return bool True when first and last name groups carry non-empty text
     */
    private function personNameShellHasRequiredLegalNames(
        array $shell
    ): bool {
        // Read the first_name group from the shell
        $first = $shell['first_name'] ?? [];

        // Read the last_name group from the shell
        $last = $shell['last_name'] ?? [];

        // Check if the first name is not an array or the last name is not an array
        if (
            ! \is_array($first)
            || ! \is_array($last)
        ) {
            // Return false to signal that the shell does not have required legal names
            return false;
        }

        // Read raw first_name text for trimming
        $fRaw = $first['text'] ?? null;

        // Normalize first_name text for required-name checks
        $f = \is_string($fRaw)
            ? \trim($fRaw)
            : (\is_scalar($fRaw) ? \trim((string) $fRaw) : '');

        // Read raw last_name text for trimming
        $lRaw = $last['text'] ?? null;

        // Normalize last_name text for required-name checks
        $l = \is_string($lRaw)
            ? \trim($lRaw)
            : (\is_scalar($lRaw) ? \trim((string) $lRaw) : '');

        // Shell is complete when both names are non-empty after trim
        return $f !== '' && $l !== '';
    }

    /**
     * Combine a legal-name shell with one nickname variant for expansion.
     *
     * @param  array<string, mixed>  $shell  Legal-name shell (first, middle, last, suffix) without nickname-only flags
     * @param  array{text: string|null, phonetic_text: string|null}  $nickVariant  One nickname row to merge into the shell for expansion
     * @return array<string, mixed> Combined `person_name`-shaped value for `PersonNameDisplayFormats` expansion
     */
    private function buildCombinedAlternateNameValueObject(
        array $shell,
        array $nickVariant
    ): array {
        // Read nickname display text from the variant
        $nickText = $nickVariant['text'];

        // Read nickname phonetic text from the variant
        $nickPhonetic = $nickVariant['phonetic_text'];

        // Assemble a person_name-shaped value for one nickname expansion
        return [
            'first_name' => \is_array($shell['first_name'] ?? null)
                ? $shell['first_name']
                : [],
            'middle_name' => \is_array($shell['middle_name'] ?? null)
                ? $shell['middle_name']
                : [],
            'nickname' => [
                'text' => $nickText,
                'phonetic_text' => $nickPhonetic,
            ],
            'last_name' => \is_array($shell['last_name'] ?? null)
                ? $shell['last_name']
                : [],
            'generational_suffix' => $shell['generational_suffix'] ?? null,
        ];
    }

    /**
     * Null phonetics for every name part that has text when any such part lacks
     * phonetic.
     *
     * @param  array<string, mixed>  $combined  Combined alternate name value object
     * @return array<string, mixed> Combined alternate name value object with nulled phonetics for parts without
     *                              phonetic
     */
    private function applyPhoneticCoherenceToCombinedAlternateName(
        array $combined
    ): array {
        // Fixed set of name parts scanned for phonetic completeness
        $parts = [
            'first_name',
            'middle_name',
            'nickname',
            'last_name',
        ];

        // ---
        // Identify parts that carry display text
        // ---

        // Initialize an empty array for the parts with text
        $withText = [];

        // Loop through each part
        foreach ($parts as $part) {
            // Get the block from the combined
            $block = $combined[$part] ?? [];

            // Check if the block is not an array
            if (! \is_array($block)) {
                // Continue to the next part
                continue;
            }

            // Raw display text for this name part
            $tRaw = $block['text'] ?? null;

            // Trimmed display text for semantic checks
            $t = \is_string($tRaw)
                ? \trim($tRaw)
                : (\is_scalar($tRaw) ? \trim((string) $tRaw) : '');

            // Track segments that participate in the phonetic completeness rule
            if ($t !== '') {
                $withText[] = $part;
            }
        }

        // Nothing to normalize when no text segments exist
        if ($withText === []) {
            return $combined;
        }

        // Determine whether every text segment also has phonetic data
        $allHavePhonetic = true;

        // Loop through each part with text
        foreach ($withText as $part) {
            // Get the block from the combined
            $block = $combined[$part] ?? [];

            // Check if the block is not an array
            if (! \is_array($block)) {
                // Set the all have phonetic flag to false
                $allHavePhonetic = false;

                // Break out of the loop
                break;
            }

            // Raw phonetic text for this name part
            $pRaw = $block['phonetic_text'] ?? null;

            // Working buffer for trimmed phonetic text
            $p = '';

            // Check if the phonetic text is not null and not empty
            if (
                $pRaw !== null
                && $pRaw !== ''
            ) {
                // Coerce non-string phonetics to trimmed strings when present
                $p = \is_string($pRaw)
                    ? \trim($pRaw)
                    : (\is_scalar($pRaw) ? \trim((string) $pRaw) : '');
            }

            // Treat empty phonetic as incomplete when text exists
            if ($p === '') {
                // Set the all have phonetic flag to false
                $allHavePhonetic = false;

                // Break out of the loop
                break;
            }
        }

        // Keep the combined object when phonetics are complete for all text segments
        if ($allHavePhonetic) {
            return $combined;
        }

        // Clear phonetics for every segment that has text when the set is incomplete
        $out = $combined;

        // Loop through each part with text
        foreach ($withText as $part) {
            // Check if the part is not set or not an array
            if (! isset($out[$part])) {
                // Continue to the next part
                continue;
            }

            if (! \is_array($out[$part])) {
                // Continue to the next part
                continue;
            }

            // Set the phonetic text to null
            $out[$part]['phonetic_text'] = null;
        }

        // Return the output
        return $out;
    }

    /**
     * Whether every text-bearing segment has non-empty phonetic text after coherence.
     *
     * @param  array<string, mixed>  $combined  Combined alternate name value object
     * @return bool False when any text segment lacks non-empty phonetic
     */
    private function combinedAlternateNameSupportsPhoneticVariations(
        array $combined
    ): bool {
        // Name parts scanned for phonetic readiness
        $parts = [
            'first_name',
            'middle_name',
            'nickname',
            'last_name',
        ];

        // Loop through each part
        foreach ($parts as $part) {
            // Get the block from the combined
            $block = $combined[$part] ?? [];

            // Check if the block is not an array
            if (! \is_array($block)) {
                // Continue to the next part
                continue;
            }

            // Raw display text before trimming
            $tRaw = $block['text'] ?? null;

            // Trimmed display text for gating phonetic checks
            $t = \is_string($tRaw)
                ? \trim($tRaw)
                : (\is_scalar($tRaw) ? \trim((string) $tRaw) : '');

            // Ignore segments without display text
            if ($t === '') {
                continue;
            }

            // Raw phonetic text before trimming
            $pRaw = $block['phonetic_text'] ?? null;

            // Working buffer for trimmed phonetic text
            $p = '';

            // Check if the phonetic text is not null and not empty
            if (
                $pRaw !== null
                && $pRaw !== ''
            ) {
                // Coerce stored phonetics to comparable trimmed strings
                $p = \is_string($pRaw)
                    ? \trim($pRaw)
                    : (\is_scalar($pRaw) ? \trim((string) $pRaw) : '');
            }

            // Any text without phonetic means IPA expansion is disabled
            if ($p === '') {
                return false;
            }
        }

        // ---
        // Return true to signal that the combined alternate name supports phonetic
        // variations
        // ---
        return true;
    }

    /**
     * Deduplicate `{ text, phonetic_text }` rows for the computed field output.
     *
     * @param  list<array{text: string, phonetic_text: string|null}>  $rows  Candidate display rows before uniqueness filtering
     * @return list<array{text: string, phonetic_text: string|null}> First occurrence of each distinct text plus phonetic pair
     */
    private function dedupeAlternateNameDisplayRows(
        array $rows
    ): array {
        // Initialize an empty array for the seen keys
        $seen = [];

        // Initialize an empty array for the output
        $out = [];

        // Loop through each row
        foreach ($rows as $row) {
            // Get the phonetic text from the row
            $phon = $row['phonetic_text'];

            // Build a stable key from text and optional phonetic payload
            $key = $row['text']."\0".($phon ?? '');

            // Skip rows already emitted
            if (isset($seen[$key])) {
                continue;
            }

            // Set the key to true in the seen array
            $seen[$key] = true;

            // Add the row to the output array
            $out[] = $row;
        }

        // Return the output array
        return $out;
    }

    /**
     * Gather nickname field values from `person_name` and `alternate_name` replicator
     * (`nickname_set` sets only).
     *
     * @param  Entry  $entry  The providers or people entry
     * @return list<mixed> Raw nickname values in field order
     */
    private function collectRawNicknameValuesFromEntry(
        Entry $entry
    ): array {
        // Initialize an empty array for the raw values
        $rawValues = [];

        // Read the primary person name, joined with its refinement fields (UAMS-Web/uams-statamic-archive#3186)
        $personName = PersonNameComposite::fromEntry($entry);

        // Append `person_name.nickname` when present
        if (
            \is_array($personName)
            && \array_key_exists(
                'nickname',
                $personName
            )
        ) {
            $rawValues[] = $personName['nickname'];
        }

        // Read `alternate_name` replicator sets
        $alternateName = $entry->get(
            'alternate_name',
            []
        );

        // Convert Statamic collection values to plain arrays when needed
        if ($alternateName instanceof IlluminateCollection) {
            $alternateName = $alternateName->all();
        }

        // Skip when `alternate_name` is not a list of sets
        if (! \is_array($alternateName)) {
            return $rawValues;
        }

        // Loop through each alternate name set
        foreach ($alternateName as $set) {
            // Check if the set is a collection
            if ($set instanceof IlluminateCollection) {
                // Convert the set to an array
                $set = $set->all();
            }

            // Ignore non-array sets
            if (! \is_array($set)) {
                continue;
            }

            // Only `nickname_set` sets contribute `nickname` here
            $setType = $set['type'] ?? null;

            // Check if the set type is not a nickname set
            if ($setType !== 'nickname_set') {
                // Continue to the next set
                continue;
            }

            // Append `nickname` from this set when defined
            if (\array_key_exists(
                'nickname',
                $set
            )) {
                $rawValues[] = $set['nickname'];
            }
        }

        // Return the raw values array
        return $rawValues;
    }

    /**
     * Normalize a single nickname field value to `{ text, phonetic_text }` or skip it.
     *
     * @param  mixed  $raw  Raw value from the CMS (string, array, or collection)
     * @return array{text: string, phonetic_text: string|null}|null Normalized pair, or null when the value should be ignored
     */
    private function normalizeOneNicknameRawValue(
        mixed $raw
    ): ?array {
        // Recurse into Statamic collections
        if ($raw instanceof IlluminateCollection) {
            return $this->normalizeOneNicknameRawValue($raw->all());
        }

        // Treat plain strings as nickname text without phonetic data
        if (\is_string($raw)) {
            // Trim leading and trailing whitespace from string nicknames
            $text = \trim($raw);

            // Ignore empty string nicknames
            if ($text === '') {
                return null;
            }

            // Return the text and phonetic text
            return [
                'text' => $text,
                'phonetic_text' => null,
            ];
        }

        // Only arrays can represent text/phonetic pairs beyond strings
        if (! \is_array($raw)) {
            return null;
        }

        // ---
        // Skip phonetic-only objects (no `text` key)
        // ---

        // Check if the text key exists in the raw array
        $hasTextKey = \array_key_exists(
            'text',
            $raw
        );

        // Check if the phonetic text key exists in the raw array
        $hasPhoneticKey = \array_key_exists(
            'phonetic_text',
            $raw
        );

        // Ignore if the phonetic text key exists and the text key does not exist
        if (
            $hasPhoneticKey
            && ! $hasTextKey
        ) {
            return null;
        }

        // Ignore if the text key does not exist
        if (! $hasTextKey) {
            return null;
        }

        // ---
        // Resolve display text from the `text` subfield
        // ---

        // Get the text from the raw array
        $textRaw = $raw['text'];

        // Check if the text is a string
        $text = \is_string($textRaw)
            ? \trim($textRaw)
            : (\is_scalar($textRaw) ? \trim((string) $textRaw) : '');

        // Ignore blank nickname text
        if ($text === '') {
            return null;
        }

        // Default phonetic to null when absent or empty
        $phoneticText = null;

        // Check if the phonetic text key exists in the raw array
        if ($hasPhoneticKey) {
            // Read optional phonetic companion text
            $p = $raw['phonetic_text'];

            // Check if the phonetic text is not null and not empty
            if (
                $p !== null
                && $p !== ''
            ) {
                // Set the phonetic text to the phonetic text
                $phoneticText = \is_string($p)
                    ? $p
                    : (\is_scalar($p) ? (string) $p : null);

                // Treat whitespace-only phonetic values as absent
                if (
                    $phoneticText !== null
                    && \trim($phoneticText) === ''
                ) {
                    $phoneticText = null;
                }
            }
        }

        // Return the text and phonetic text
        return [
            'text' => $text,
            'phonetic_text' => $phoneticText,
        ];
    }

    /**
     * Collapse duplicate `text` values using first-seen text order and the first
     * non-empty `phonetic_text` in encounter order among duplicates.
     *
     * @param  list<array{text: string, phonetic_text: string|null}>  $items  Normalized nickname pairs before duplicate collapse
     * @return list<array{text: string, phonetic_text: string|null}> One row per distinct text with first non-empty phonetic among duplicates
     */
    private function dedupeNicknamesByTextPreservingOrder(
        array $items
    ): array {
        // ---
        // Track distinct texts in first-occurrence order
        // ---

        // Initialize an empty array for the text order
        $textOrder = [];

        // ---
        // Map each text to ordered phonetic candidates from duplicates
        // ---

        // Initialize an empty array for the text to phonetics in order
        $textToPhoneticsInOrder = [];

        // Loop through each item
        foreach ($items as $item) {
            // Read stable text key for grouping
            $text = $item['text'];

            // Record first-seen order for each distinct text
            if (! \array_key_exists(
                $text,
                $textToPhoneticsInOrder
            )) {
                $textOrder[] = $text;
                $textToPhoneticsInOrder[$text] = [];
            }

            // Append this row's phonetic candidate for later winner selection
            $textToPhoneticsInOrder[$text][] = $item['phonetic_text'];
        }

        // Build merged rows with one phonetic winner per text
        $out = [];

        // Loop through each text in the text order
        foreach ($textOrder as $text) {
            // Ordered phonetic candidates for this nickname text
            $phonetics = $textToPhoneticsInOrder[$text];

            // Choose the first non-empty phonetic in encounter order
            $chosenPhonetic = null;

            // Loop through each phonetic candidate
            foreach ($phonetics as $candidate) {
                // Check if the candidate is not null and not empty
                if (
                    $candidate !== null
                    && \trim((string) $candidate) !== ''
                ) {
                    // Set the chosen phonetic to the candidate
                    $chosenPhonetic = \trim((string) $candidate);

                    // Break out of the loop
                    break;
                }
            }

            // Add the text and chosen phonetic to the output array
            $out[] = [
                'text' => $text,
                'phonetic_text' => $chosenPhonetic,
            ];
        }

        // Return the output array
        return $out;
    }

    /**
     * Display name with optional "Dr." prefix and paired phonetic text.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return array{text: string, phonetic_text: string|null} Paired display name with optional "Dr." prefix and matching IPA
     */
    public function getDisplayNameFullWithPrefix(
        Entry $entry
    ): array {
        // Resolve base display string from `person_name`
        $baseDisplayName = $this->resolveDisplayNameFullText($entry);

        // Return empty pair when the base display name cannot be built
        if ($baseDisplayName === '') {
            return $this->computeComputedTextPhoneticPair(
                '',
                ''
            );
        }

        // Determine if "Dr." prefix applies based on credentials
        $prefix = $this->getPrefix($entry);

        // Build display text with optional prefix
        $text = $prefix !== '' && $prefix !== '0'
            ? sprintf('%s %s', $prefix, $baseDisplayName)
            : $baseDisplayName;

        // ---
        // Build phonetic text with optional doctor honorific IPA
        // ---

        // Resolve the base phonetic text from the entry
        $basePhonetic = $this->resolveDisplayNameFullPhoneticText($entry);

        // Initialize an empty string for the phonetic text
        $phonetic = '';

        // Check if the base phonetic text is not empty
        if ($basePhonetic !== '') {
            // ---
            // Set the phonetic text to the base phonetic text with optional doctor honorific
            // IPA
            // ---
            $phonetic = $prefix !== '' && $prefix !== '0'
                ? self::DOCTOR_HONORIFIC_IPA.' '.$basePhonetic
                : $basePhonetic;
        }

        // Return the computed text/phonetic pair
        return $this->computeComputedTextPhoneticPair(
            $text,
            $phonetic
        );
    }

    /**
     * Display name with credential titles and paired phonetic text (credential IPA
     * appended with `.`, no doctor honorific prefix).
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return array{text: string, phonetic_text: string|null} Paired display name with credential titles and credential IPA suffixes joined by `.`
     */
    public function getDisplayNameFullWithCredentials(
        Entry $entry
    ): array {
        // Build the pair from every selected credential
        return $this->displayNameFullPairWithCredentials(
            $entry,
            $entry->get('credentials')
        );
    }

    /**
     * Display name with only the regulatory-designation credential titles, for the
     * clinical (provider) lens and the Find-a-Doc card (UAMS-Web/uams-statamic#344).
     *
     * A credential term is kept when it answers yes to both "Is this a clinical
     * credential?" (`clinical_query`) and "Is this a regulatory designation?"
     * (`clinical_statutory_credential_query`). The Control Panel shows the second
     * question only when the first is answered yes, so requiring both stops a
     * hidden, stale regulatory answer on a non-clinical term from reaching the
     * clinical name. Every other lens keeps `display_name_full_with_credentials`.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return array{text: string, phonetic_text: string|null} Paired display name with regulatory credential titles and their IPA suffixes joined by `.`
     */
    public function getDisplayNameFullWithClinicalCredentials(
        Entry $entry
    ): array {
        // Build the pair from the regulatory-designation credentials only
        return $this->displayNameFullPairWithCredentials(
            $entry,
            $this->regulatoryCredentialSlugs($entry->get('credentials'))
        );
    }

    /**
     * Credential term slugs, in their selected order, whose term answers yes to
     * both the clinical and the regulatory-designation questions.
     *
     * @param  mixed  $credentials  Entry `credentials` field (term slugs)
     * @return list<string> The kept slugs
     */
    private function regulatoryCredentialSlugs(
        mixed $credentials
    ): array {
        // Treat a bare slug as a one-item list, as the full name's `collect()` does
        if (\is_string($credentials)) {
            $credentials = [$credentials];
        }

        // Return no slugs when the credentials are not a list
        if (! \is_array($credentials)) {
            return [];
        }

        // Keep each slug whose term answers yes to both questions
        $kept = [];

        foreach ($credentials as $slug) {
            // Read an integer slug as a string, as the full name's title lookup does
            if (\is_int($slug)) {
                $slug = (string) $slug;
            }

            // Skip a value that cannot name a term
            if (! \is_string($slug) || $slug === '') {
                continue;
            }

            // Find the credential term
            $term = Term::find('credentials::'.$slug);

            // Keep the slug when both answers are a strict `true`
            if (
                $term instanceof LocalizedTerm
                && $term->get('clinical_query') === true
                && $term->get('clinical_statutory_credential_query') === true
            ) {
                $kept[] = $slug;
            }
        }

        return $kept;
    }

    /**
     * Display name pair with the titles and abbreviation IPA of the given
     * credential slugs appended.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @param  mixed  $credentials  Credential term slugs to append
     * @return array{text: string, phonetic_text: string|null} Paired display name with credential titles and credential IPA suffixes joined by `.`
     */
    private function displayNameFullPairWithCredentials(
        Entry $entry,
        mixed $credentials
    ): array {
        // Resolve base display string from `person_name`
        $baseDisplayName = $this->resolveDisplayNameFullText($entry);

        // Return empty pair when the base display name cannot be built
        if ($baseDisplayName === '') {
            return $this->computeComputedTextPhoneticPair(
                '',
                ''
            );
        }

        // Build the text with the credential titles
        $text = $this->displayNameWithCredentialTitles(
            $baseDisplayName,
            $credentials
        );

        // Resolve the base phonetic text from the entry
        $basePhonetic = $this->resolveDisplayNameFullPhoneticText($entry);

        // Set the phonetic text to the base phonetic text with the credential abbreviation phonetics joined by '.'
        $phonetic = $this->phoneticWithCredentialAbbreviationSuffix(
            $basePhonetic,
            $credentials
        );

        // Return the computed text/phonetic pair
        return $this->computeComputedTextPhoneticPair(
            $text,
            $phonetic
        );
    }

    /**
     * Short display with optional "Dr." plus last name, else full display; paired
     * phonetics.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return array{text: string, phonetic_text: string|null} Short display name pair
     */
    public function getDisplayNameShortWithPrefix(
        Entry $entry
    ): array {
        // Determine if "Dr." prefix applies
        $prefix = $this->getPrefix($entry);

        // Build doctor-style short form when prefix applies
        if ($prefix !== '' && $prefix !== '0') {
            // Get the normalized person name from the entry
            $normalized = $this->getNormalizedPersonName($entry);

            // Check if the normalized person name is not complete for computed names
            if (! $this->normalizedPrimaryPersonNameIsCompleteForComputedNames(
                $normalized
            )) {
                // ---
                // Return empty pair when the normalized person name is not complete for computed
                // names
                // ---
                return $this->computeComputedTextPhoneticPair(
                    '',
                    ''
                );
            }

            // Get the last name from the normalized person name
            $lastNameBlock = $normalized['last_name'] ?? null;
            $lastNameTextRaw = \is_array($lastNameBlock) ? ($lastNameBlock['text'] ?? '') : '';
            $lastName = \trim(\is_scalar($lastNameTextRaw) ? (string) $lastNameTextRaw : '');

            // Return prefixed last name if last name exists, otherwise empty pair
            if ($lastName === '') {
                return $this->computeComputedTextPhoneticPair(
                    '',
                    ''
                );
            }

            // Build the text with the prefix and last name
            $text = sprintf('%s %s', $prefix, $lastName);

            // Get the last name phonetic text from the normalized person name
            $lastPhoneticRaw = \is_array($lastNameBlock) ? ($lastNameBlock['phonetic_text'] ?? '') : '';
            $lastPhonetic = \trim(\is_scalar($lastPhoneticRaw) ? (string) $lastPhoneticRaw : '');

            // Check if the last name phonetic text is not empty
            $phonetic = $lastPhonetic !== ''
                ? self::DOCTOR_HONORIFIC_IPA.' '.$lastPhonetic
                : '';

            // Return the computed text/phonetic pair
            return $this->computeComputedTextPhoneticPair(
                $text,
                $phonetic
            );
        }

        // Fallback to full display name pair when not a doctor-style short form
        return $this->getDisplayNameFull($entry);
    }

    /**
     * Append comma-separated credential titles after the base name when taxonomy titles exist.
     *
     * @param  string  $baseName  Resolved name text (display or legal order)
     * @param  mixed  $credentials  Entry `credentials` field (term slugs)
     * @return string Base name alone, or `"{base}, {title1}, {title2}"`
     */
    private function displayNameWithCredentialTitles(
        string $baseName,
        mixed $credentials
    ): string {
        // Return the base name if the credentials are not set
        if (! $credentials) {
            return $baseName;
        }

        // Get the credential titles from the credentials
        /** @var list<string> $credentials */
        /** @var list<string> $credentialTitles */
        $credentialTitles = collect($credentials)
            ->map(fn ($slug) => Term::find('credentials::'.$slug))
            ->filter()
            ->pluck('title')
            ->all();

        // Return the base name if the credential titles are not set
        if ($credentialTitles === []) {
            return $baseName;
        }

        // Return the base name with the credential titles joined by ','
        return $baseName.', '.\implode(
            ', ',
            $credentialTitles
        );
    }

    /**
     * Merge base name IPA with `.`-joined credential abbreviation IPAs when any exist.
     *
     * @param  string  $basePhonetic  Resolved IPA for the name only
     * @param  mixed  $credentials  Entry `credentials` field (term slugs)
     * @return string Empty when `$basePhonetic` is empty; otherwise base IPA plus optional credential suffixes
     */
    private function phoneticWithCredentialAbbreviationSuffix(
        string $basePhonetic,
        mixed $credentials
    ): string {
        // Return empty string when the base phonetic text is empty
        if ($basePhonetic === '') {
            return '';
        }

        // Get the credential abbreviation phonetic segments
        $credentialPhonetics = $this->credentialAbbreviationPhoneticSegments(
            $credentials
        );

        // Return the base phonetic text with the credential abbreviation phonetics joined by '.'
        return $credentialPhonetics !== []
            ? $this->joinPhoneticWithCredentialAbbreviations(
                $basePhonetic,
                $credentialPhonetics
            )
            : $basePhonetic;
    }

    /**
     * Ordered non-empty `abbreviation_phonetic_text` values for credential term slugs.
     *
     * @param  mixed  $credentials  Entry `credentials` field (list of slugs)
     * @return list<string> Ordered non-empty IPA strings from each credential term `abbreviation_phonetic_text`
     */
    private function credentialAbbreviationPhoneticSegments(
        mixed $credentials
    ): array {
        // Return empty array when the credentials are null, empty, or an empty array
        if (
            in_array($credentials, [null, '', []], true)
        ) {
            return [];
        }

        // Initialize an empty array for the segments
        $segments = [];

        // Loop through each credential
        /** @var list<mixed> $credentialValues */
        $credentialValues = $credentials;

        foreach (collect($credentialValues)->all() as $slug) {
            // Check if the credential is not a string or an integer
            if (
                ! \is_string($slug)
                && ! \is_int($slug)
            ) {
                continue;
            }

            // Get the term from the credentials
            $term = Term::find('credentials::'.$slug);

            // Check if the term is null
            if ($term === null) {
                // Continue to the next credential
                continue;
            }

            // Resolve the credential term abbreviation phonetic
            $segment = $this->resolveCredentialTermAbbreviationPhonetic(
                $term
            );

            // Check if the segment is not empty
            if ($segment !== '') {
                // Add the segment to the segments array
                $segments[] = $segment;
            }
        }

        // Return the segments array
        return $segments;
    }

    /**
     * Resolve IPA for a credentials taxonomy term from `abbreviation_phonetic_text`.
     *
     * @param  mixed  $term  Resolved credentials term, or null
     * @return string Trimmed phonetic string, or empty when missing
     */
    private function resolveCredentialTermAbbreviationPhonetic(
        mixed $term
    ): string {
        // Return empty string when the term is not an object or does not have a get method
        if (
            ! \is_object($term)
            || ! \method_exists($term, 'get')
        ) {
            return '';
        }

        // Get the raw abbreviation phonetic text from the term
        $raw = $term->get('abbreviation_phonetic_text');

        // Check if the raw abbreviation phonetic text is a string
        if (\is_string($raw)) {
            // Return the trimmed raw abbreviation phonetic text
            return \trim($raw);
        }

        // Resolve the name phonetic subfield value from the raw abbreviation phonetic text
        return $this->resolveNamePhoneticSubfieldValue($raw);
    }

    /**
     * Join name phonetic text with credential abbreviation phonetics using `.`
     * delimiters.
     *
     * @param  string  $basePhonetic  Space-joined name IPA segments
     * @param  list<string>  $credentialPhonetics  Non-empty `abbreviation_phonetic_text` values
     * @return string Base plus `.cred1.cred2`
     */
    private function joinPhoneticWithCredentialAbbreviations(
        string $basePhonetic,
        array $credentialPhonetics
    ): string {
        // ---
        // Return the base phonetic text with the credential abbreviation phonetics joined
        // by '.'
        // ---
        return $basePhonetic.'.'.\implode(
            '.',
            $credentialPhonetics
        );
    }

    /**
     * Optional "Dr." prefix plus legal full name with paired phonetic text.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return array{text: string, phonetic_text: string|null} Paired legal full name with optional "Dr." prefix and matching IPA
     */
    public function getFullNameWithPrefix(
        Entry $entry
    ): array {
        // Resolve legal-order display text
        $fullName = $this->resolveFullNameText($entry);

        // Return empty pair when there is no full name text
        if ($fullName === '') {
            return $this->computeComputedTextPhoneticPair(
                '',
                ''
            );
        }

        // Determine if "Dr." prefix applies based on credentials
        $prefix = $this->getPrefix($entry);

        // Build the text with the prefix and full name
        $text = $prefix !== '' && $prefix !== '0'
            ? sprintf('%s %s', $prefix, $fullName)
            : $fullName;

        // Resolve the base phonetic text from the entry
        $basePhonetic = $this->resolveFullNamePhoneticText($entry);

        // Initialize an empty string for the phonetic text
        $phonetic = '';

        // Check if the base phonetic text is not empty
        if ($basePhonetic !== '') {
            // ---
            // Set the phonetic text to the base phonetic text with optional doctor honorific
            // IPA
            // ---
            $phonetic = $prefix !== '' && $prefix !== '0'
                ? self::DOCTOR_HONORIFIC_IPA.' '.$basePhonetic
                : $basePhonetic;
        }

        // Return the computed text/phonetic pair
        return $this->computeComputedTextPhoneticPair(
            $text,
            $phonetic
        );
    }

    /**
     * Legal full name with credential titles and paired phonetic text.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return array{text: string, phonetic_text: string|null} Paired legal full name with comma-suffixed credential titles and merged IPA
     */
    public function getFullNameWithCredentials(
        Entry $entry
    ): array {
        // Resolve legal-order display text
        $fullName = $this->resolveFullNameText($entry);

        // Return empty pair when there is no full name text
        if ($fullName === '') {
            return $this->computeComputedTextPhoneticPair(
                '',
                ''
            );
        }

        // Get the credentials from the entry
        $credentials = $entry->get('credentials');

        // Build the text with the credential titles
        $text = $this->displayNameWithCredentialTitles(
            $fullName,
            $credentials
        );

        // Resolve the base phonetic text from the entry
        $basePhonetic = $this->resolveFullNamePhoneticText($entry);

        // Set the phonetic text to the base phonetic text with the credential abbreviation phonetics joined by '.'
        $phonetic = $this->phoneticWithCredentialAbbreviationSuffix(
            $basePhonetic,
            $credentials
        );

        // Return the computed text/phonetic pair
        return $this->computeComputedTextPhoneticPair(
            $text,
            $phonetic
        );
    }

    /**
     * Determines the prefix based on the entry's credential field.
     * Returns "Dr." if the entry has an appropriate credential, otherwise returns an
     * empty string.
     *
     * @param  Entry  $entry  The providers or people entry to process
     * @return string The computed prefix ('Dr.' or '')
     */
    private function getPrefix(
        Entry $entry
    ): string {
        // Fetch the 'credentials' field, default to empty array if unset
        $credentials = $entry->get(
            'credentials',
            []
        );

        // Check if 'md' or 'do' is present in the credential slugs
        /** @var list<string> $credentials */
        $isDoctor = collect($credentials)->contains(
            fn ($slug): bool => \in_array(
                $slug,
                [
                    'md',
                    'do',
                ]
            )
        );

        // ---
        // Return "Dr." if the provider has an appropriate credential, otherwise return an
        // empty string
        // ---
        return $isDoctor ? 'Dr.' : '';
    }
}
