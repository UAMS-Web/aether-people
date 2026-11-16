<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Support\PersonNameComposite;
use UAMSWeb\Aether\People\Support\PersonNameDisplayFormats;

/**
 * Collecting the raw name shells an alternate-name list is built from, and deduplicating
 * them and the rows built from them.
 *
 * Split from `ProviderPeoplePersonNameComputedValues` by moving methods only.
 */
class AlternateNameCollector
{
    /**
     * Build the class over its collaborators, each defaulting to a new instance.
     *
     * @param  PersonNameBaseComputeds  $base  The collaborator this class calls
     */
    public function __construct(
        private readonly PersonNameBaseComputeds $base = new PersonNameBaseComputeds,
    ) {}

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
        // The person name is always an array when a shell was built from the primary name, and a null one reads the same
        // as the early return below, so removing the return changes nothing.
        // @pest-mutate-ignore: RemoveEarlyReturn
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
    public function collectRawPersonNameFieldValuesFromEntry(
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
    public function collectFreeTextAlternateNamesFromEntry(
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
    public function dedupePersonNameShellsWithSourceFlags(
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
            // json_encode() returns false only for a value it cannot encode, which a shell of strings never is, so the cast
            // never changes the fingerprint.
            // @pest-mutate-ignore: RemoveStringCast
            $key = \hash(
                'xxh128',
                (string) \json_encode($row['shell'])
            );

            // Initialize bucket for this legal-name shell
            $byHash[$key] ??= [
                'shell' => $row['shell'],
                'from_primary' => $row['from_primary'],
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
    public function resolveSkipDisplayFormatKeyForAlternateExpansion(
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
        $normalized = $this->base->getNormalizedPersonName($entry);

        // Get the primary no nickname flag from the normalized person name
        $primaryNoNick = $normalized['no_nickname'] === true;

        // Get the primary nickname text from the normalized person name
        $primaryNickBlock = $normalized['nickname'] ?? null;
        $primaryNickText = $this->base->trimmedScalarText(\is_array($primaryNickBlock) ? ($primaryNickBlock['text'] ?? null) : null);

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

        // Return true if the variant text matches the primary nickname text
        return $variantText === $primaryNickText;
    }

    /**
     * Remove subfields that are not part of the alternate expansion shell.
     *
     * @param  array<string, mixed>  $raw  Raw `person_name` field value
     * @return array<string, mixed> Shell suitable for alternate-name expansion with nickname and display-format keys removed
     */
    public function stripPersonNameFieldForAlternateExpansion(
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
     * Whether the shell has non-empty first and last name text.
     *
     * @param  array<string, mixed>  $shell  Person name shell used for alternate expansion
     * @return bool True when first and last name groups carry non-empty text
     */
    public function personNameShellHasRequiredLegalNames(
        array $shell
    ): bool {
        // Read the first_name group from the shell
        $first = $shell['first_name'] ?? [];

        // Read the last_name group from the shell
        $last = $shell['last_name'] ?? [];

        // Normalize first_name text for required-name checks (a part that is not a pair has no text)
        $f = $this->base->trimmedScalarText(\is_array($first) ? ($first['text'] ?? null) : null);

        // Normalize last_name text for required-name checks
        $l = $this->base->trimmedScalarText(\is_array($last) ? ($last['text'] ?? null) : null);

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
    public function buildCombinedAlternateNameValueObject(
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
     * Deduplicate `{ text, phonetic_text }` rows for the computed field output.
     *
     * @param  list<array{text: string, phonetic_text: string|null}>  $rows  Candidate display rows before uniqueness filtering
     * @return list<array{text: string, phonetic_text: string|null}> First occurrence of each distinct text plus phonetic pair
     */
    public function dedupeAlternateNameDisplayRows(
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
            // isset() is true for a stored false as well, so the stored value never matters.
            // @pest-mutate-ignore: TrueToFalse
            $seen[$key] = true;

            // Add the row to the output array
            $out[] = $row;
        }

        // Return the output array
        return $out;
    }
}
