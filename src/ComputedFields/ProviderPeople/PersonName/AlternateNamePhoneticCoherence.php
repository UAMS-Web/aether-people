<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName;

/**
 * Phonetic handling for combined alternate names: merging name-part phonetics and keeping
 * the phonetic variations of a combined name coherent.
 *
 * Split from `ProviderPeoplePersonNameComputedValues` by moving methods only.
 */
class AlternateNamePhoneticCoherence
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
     * Apply merged first/middle phonetics using trimmed text as the lookup key.
     *
     * @param  array<string, mixed>  $shell  Person name shell with alternate-only subfields
     * @param  array<string, string|null>  $mergedFirstPhonetics  First-name text key to phonetic map
     * @param  array<string, string|null>  $mergedMiddlePhonetics  Middle-name text key to phonetic map
     * @return array<string, mixed> Shell with merged phonetics
     */
    public function applyMergedNamePartPhonetics(
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
        $fText = $this->base->trimmedScalarText($fRaw);

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
        $mText = $this->base->trimmedScalarText($mRaw);

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
     * Null phonetics for every name part that has text when any such part lacks
     * phonetic.
     *
     * @param  array<string, mixed>  $combined  Combined alternate name value object
     * @return array<string, mixed> Combined alternate name value object with nulled phonetics for parts without
     *                              phonetic
     */
    public function applyPhoneticCoherenceToCombinedAlternateName(
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
            $t = $this->base->trimmedScalarText($tRaw);

            // Track segments that participate in the phonetic completeness rule
            if ($t !== '') {
                $withText[] = $part;
            }
        }

        // Nothing to normalize when no text segments exist
        // Without a named part the loop below has nothing to check and returns the same object,
        // so removing this return changes nothing.
        // @pest-mutate-ignore: RemoveEarlyReturn
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
            // Every part in the list passed the same check in the first loop, so the block is always an array here.
            // @pest-mutate-ignore: FalseToTrue,BreakToContinue
            if (! \is_array($block)) {
                // Set the all have phonetic flag to false
                $allHavePhonetic = false;

                // Break out of the loop
                break;
            }

            // Raw phonetic text for this name part
            $pRaw = $block['phonetic_text'] ?? null;

            // Trimmed phonetic text, empty when absent
            $p = $this->base->trimmedScalarText($pRaw);

            // Treat empty phonetic as incomplete when text exists
            // A part without a phonetic sets the flag false, which it already is when the loop moves on, so continuing
            // instead of stopping changes nothing.
            // @pest-mutate-ignore: BreakToContinue
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
            // Every part in the list was an array in the first loop, so it is always set here.
            // @pest-mutate-ignore: ContinueToBreak
            if (! isset($out[$part])) {
                // Continue to the next part
                continue;
            }

            // Every part in the list was an array in the first loop.
            // @pest-mutate-ignore: ContinueToBreak
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
    public function combinedAlternateNameSupportsPhoneticVariations(
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
            $t = $this->base->trimmedScalarText($tRaw);

            // Ignore segments without display text
            if ($t === '') {
                continue;
            }

            // Raw phonetic text before trimming
            $pRaw = $block['phonetic_text'] ?? null;

            // Trimmed phonetic text, empty when absent
            $p = $this->base->trimmedScalarText($pRaw);

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
}
