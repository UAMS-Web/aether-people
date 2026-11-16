<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Support\PersonNameComposite;
use UAMSWeb\Aether\People\Support\PersonNameDisplayFormats;

/**
 * The base person-name computeds and the helpers every other name class shares: normalizing
 * a `person_name` value, the text and phonetic pair, and the full-name and display-name
 * strings.
 *
 * Split from `ProviderPeoplePersonNameComputedValues` by moving methods only.
 */
class PersonNameBaseComputeds
{
    /**
     * A scalar read as trimmed text, or an empty string for anything else.
     *
     * @param  mixed  $value  Raw value from a name component
     * @return string Trimmed text of a string or scalar value, otherwise empty
     */
    public function trimmedScalarText(
        mixed $value
    ): string {
        // Null, arrays and objects carry no text
        if (! \is_scalar($value)) {
            return '';
        }

        return \trim((string) $value);
    }

    /**
     * Read the `phonetic_text` subfield from a name component (group or legacy
     * string).
     *
     * @param  mixed  $value  Raw value from `Entry::get()` for a name component
     * @return string Phonetic text, or empty string when absent
     */
    public function resolveNamePhoneticSubfieldValue(
        mixed $value
    ): string {
        // Check if the value is a collection
        if ($value instanceof IlluminateCollection) {
            // Recursively resolve the phonetic value from the collection
            return $this->resolveNamePhoneticSubfieldValue($value->all());
        }

        // Check if the value is an array
        if (\is_array($value)) {
            // Get the phonetic value from the array
            $phonetic = $value['phonetic_text'] ?? null;

            // Return the phonetic value if it is a string
            return \is_string($phonetic)
                ? $phonetic
                : (\is_scalar($phonetic) ? (string) $phonetic : '');
        }

        // Anything else (null, an empty value, a legacy string) carries no phonetic subfield
        return '';
    }

    /**
     * Normalize the `person_name` field for `PersonNameDisplayFormats` helpers.
     *
     * @param  Entry  $entry  The providers or people entry
     * @return array<string, mixed> Normalized person name array
     */
    public function getNormalizedPersonName(
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
    public function normalizedPrimaryPersonNameIsCompleteForComputedNames(
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
    public function computeComputedTextPhoneticPair(
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
    public function resolveDisplayNameFullText(
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
    public function resolveDisplayNameFullPhoneticText(
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
    public function resolveFullNameText(
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
    public function resolveFullNamePhoneticText(
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
}
