<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Support\PersonNameComposite;

/**
 * The nickname list computed: collecting raw nickname values from an entry, normalizing
 * each, and removing duplicates in order.
 *
 * Split from `ProviderPeoplePersonNameComputedValues` by moving methods only.
 */
class PersonNameNicknameComputeds
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
        $text = $this->base->trimmedScalarText($textRaw);

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

            // Keep a string or scalar phonetic as given; whitespace-only values count as absent
            $phoneticText = \is_string($p)
                ? $p
                : (\is_scalar($p) ? (string) $p : null);

            if (
                $phoneticText !== null
                && \trim($phoneticText) === ''
            ) {
                $phoneticText = null;
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
                // Take the first candidate with text once trimmed
                $trimmedCandidate = $this->base->trimmedScalarText($candidate);

                if ($trimmedCandidate !== '') {
                    // Set the chosen phonetic to the candidate
                    $chosenPhonetic = $trimmedCandidate;

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
}
