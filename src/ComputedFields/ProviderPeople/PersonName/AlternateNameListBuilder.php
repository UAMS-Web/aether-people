<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName;

use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Support\PersonNameDisplayFormats;

/**
 * The alternate-name list computed: expands the primary name, nicknames and free-text names
 * into deduplicated rows.
 *
 * Split from `ProviderPeoplePersonNameComputedValues` by moving methods only.
 */
class AlternateNameListBuilder
{
    /**
     * Build the class over its collaborators, each defaulting to a new instance.
     *
     * @param  PersonNameNicknameComputeds  $nicknames  The collaborator this class calls
     * @param  AlternateNameCollector  $collector  The collaborator this class calls
     * @param  AlternateNamePhoneticCoherence  $phonetics  The collaborator this class calls
     */
    public function __construct(
        private readonly PersonNameNicknameComputeds $nicknames = new PersonNameNicknameComputeds,
        private readonly AlternateNameCollector $collector = new AlternateNameCollector,
        private readonly AlternateNamePhoneticCoherence $phonetics = new AlternateNamePhoneticCoherence,
    ) {}

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
        $taggedRawPersonNames = $this->collector->collectRawPersonNameFieldValuesFromEntry(
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
            $stripped = $this->collector->stripPersonNameFieldForAlternateExpansion(
                $rawPersonName
            );

            // Apply merged phonetics for shared first/middle text keys
            $withMerge = $this->phonetics->applyMergedNamePartPhonetics(
                $stripped,
                $mergedFirstPhonetics,
                $mergedMiddlePhonetics
            );

            // Skip rows missing required first and last name text
            if (! $this->collector->personNameShellHasRequiredLegalNames($withMerge)) {
                continue;
            }

            // Add the shell with source to the shells with source array
            $shellsWithSource[] = [
                'shell' => $withMerge,
                'from_primary' => $tagged['from_primary'],
            ];
        }

        // Deduplicate equivalent shells and merge source flags (alternate disallows skip)
        $shellsWithSkipRules = $this->collector->dedupePersonNameShellsWithSourceFlags(
            $shellsWithSource
        );

        // ---
        // Build nickname variants including an explicit empty nickname row
        // ---

        // Get the nickname variants from the entry
        $nicknameVariants = $this->nicknames->getNicknameList($entry);

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
                $combined = $this->collector->buildCombinedAlternateNameValueObject(
                    $shell,
                    $nickVariant
                );

                // Clear phonetics when any text segment lacks a companion phonetic
                $combined = $this->phonetics->applyPhoneticCoherenceToCombinedAlternateName(
                    $combined
                );

                // Decide whether pattern IPA can be generated for this combination
                $generatePhonetics = $this->phonetics->combinedAlternateNameSupportsPhoneticVariations(
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
                $skipDisplayFormatKey = $this->collector->resolveSkipDisplayFormatKeyForAlternateExpansion(
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
        foreach ($this->collector->collectFreeTextAlternateNamesFromEntry($entry) as $freeText) {
            $allRows[] = [
                'text' => $freeText,
                'phonetic_text' => null,
            ];
        }

        // Deduplicate the final flattened list
        return $this->collector->dedupeAlternateNameDisplayRows($allRows);
    }
}
