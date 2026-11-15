<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName;

use Statamic\Entries\Entry;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Prefix and credential variants of the name computeds: the doctor honorific, and display
 * and full names with a prefix or credential titles and phonetic abbreviations.
 *
 * Split from `ProviderPeoplePersonNameComputedValues` by moving methods only.
 */
class PersonNamePrefixAndCredentialComputeds
{
    /**
     * International Phonetic Alphabet for the spoken "doctor" honorific, used when
     * credentials imply a "Dr." prefix in phonetic computeds.
     */
    private const string DOCTOR_HONORIFIC_IPA = 'ˈdɑktər';

    /**
     * Build the class over its collaborators, each defaulting to a new instance.
     *
     * @param  PersonNameBaseComputeds  $base  The collaborator this class calls
     */
    public function __construct(
        private readonly PersonNameBaseComputeds $base = new PersonNameBaseComputeds,
    ) {}

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
        return $this->base->computeComputedTextPhoneticPair(
            $text,
            $phonetic
        );
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
        $baseDisplayName = $this->base->resolveDisplayNameFullText($entry);

        // Return empty pair when the base display name cannot be built
        if ($baseDisplayName === '') {
            return $this->base->computeComputedTextPhoneticPair(
                '',
                ''
            );
        }

        // Determine if "Dr." prefix applies based on credentials
        $prefix = $this->getPrefix($entry);

        // Build display text with optional prefix
        $text = $prefix !== ''
            ? sprintf('%s %s', $prefix, $baseDisplayName)
            : $baseDisplayName;

        // ---
        // Build phonetic text with optional doctor honorific IPA
        // ---

        // Resolve the base phonetic text from the entry
        $basePhonetic = $this->base->resolveDisplayNameFullPhoneticText($entry);

        // Initialize an empty string for the phonetic text
        $phonetic = '';

        // Check if the base phonetic text is not empty
        if ($basePhonetic !== '') {
            // ---
            // Set the phonetic text to the base phonetic text with optional doctor honorific
            // IPA
            // ---
            $phonetic = $prefix !== ''
                ? self::DOCTOR_HONORIFIC_IPA.' '.$basePhonetic
                : $basePhonetic;
        }

        // Return the computed text/phonetic pair
        return $this->base->computeComputedTextPhoneticPair(
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
        $baseDisplayName = $this->base->resolveDisplayNameFullText($entry);

        // Return empty pair when the base display name cannot be built
        if ($baseDisplayName === '') {
            return $this->base->computeComputedTextPhoneticPair(
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
        $basePhonetic = $this->base->resolveDisplayNameFullPhoneticText($entry);

        // Set the phonetic text to the base phonetic text with the credential abbreviation phonetics joined by '.'
        $phonetic = $this->phoneticWithCredentialAbbreviationSuffix(
            $basePhonetic,
            $credentials
        );

        // Return the computed text/phonetic pair
        return $this->base->computeComputedTextPhoneticPair(
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
        if ($prefix !== '') {
            // Get the normalized person name from the entry
            $normalized = $this->base->getNormalizedPersonName($entry);

            // Check if the normalized person name is not complete for computed names
            if (! $this->base->normalizedPrimaryPersonNameIsCompleteForComputedNames(
                $normalized
            )) {
                // ---
                // Return empty pair when the normalized person name is not complete for computed
                // names
                // ---
                return $this->base->computeComputedTextPhoneticPair(
                    '',
                    ''
                );
            }

            // Get the last name from the normalized person name
            $lastNameBlock = $normalized['last_name'] ?? null;
            $lastName = $this->base->trimmedScalarText(\is_array($lastNameBlock) ? ($lastNameBlock['text'] ?? null) : null);

            // A complete name always has a last name once trimmed, so there is no empty case to return here

            // Build the text with the prefix and last name
            $text = sprintf('%s %s', $prefix, $lastName);

            // Get the last name phonetic text from the normalized person name
            $lastPhonetic = $this->base->trimmedScalarText(\is_array($lastNameBlock) ? ($lastNameBlock['phonetic_text'] ?? null) : null);

            // Check if the last name phonetic text is not empty
            $phonetic = $lastPhonetic !== ''
                ? self::DOCTOR_HONORIFIC_IPA.' '.$lastPhonetic
                : '';

            // Return the computed text/phonetic pair
            return $this->base->computeComputedTextPhoneticPair(
                $text,
                $phonetic
            );
        }

        // Fallback to full display name pair when not a doctor-style short form
        return $this->base->getDisplayNameFull($entry);
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
        // An empty credentials value gives an empty title list, which returns the base name below as well.
        // @pest-mutate-ignore: RemoveEarlyReturn
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
        // Return empty array when the credentials are an empty string (null and an empty array have nothing to loop over)
        if ($credentials === '') {
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
        return $this->base->resolveNamePhoneticSubfieldValue($raw);
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
        $fullName = $this->base->resolveFullNameText($entry);

        // Return empty pair when there is no full name text
        if ($fullName === '') {
            return $this->base->computeComputedTextPhoneticPair(
                '',
                ''
            );
        }

        // Determine if "Dr." prefix applies based on credentials
        $prefix = $this->getPrefix($entry);

        // Build the text with the prefix and full name
        $text = $prefix !== ''
            ? sprintf('%s %s', $prefix, $fullName)
            : $fullName;

        // Resolve the base phonetic text from the entry
        $basePhonetic = $this->base->resolveFullNamePhoneticText($entry);

        // Initialize an empty string for the phonetic text
        $phonetic = '';

        // Check if the base phonetic text is not empty
        if ($basePhonetic !== '') {
            // ---
            // Set the phonetic text to the base phonetic text with optional doctor honorific
            // IPA
            // ---
            $phonetic = $prefix !== ''
                ? self::DOCTOR_HONORIFIC_IPA.' '.$basePhonetic
                : $basePhonetic;
        }

        // Return the computed text/phonetic pair
        return $this->base->computeComputedTextPhoneticPair(
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
        $fullName = $this->base->resolveFullNameText($entry);

        // Return empty pair when there is no full name text
        if ($fullName === '') {
            return $this->base->computeComputedTextPhoneticPair(
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
        $basePhonetic = $this->base->resolveFullNamePhoneticText($entry);

        // Set the phonetic text to the base phonetic text with the credential abbreviation phonetics joined by '.'
        $phonetic = $this->phoneticWithCredentialAbbreviationSuffix(
            $basePhonetic,
            $credentials
        );

        // Return the computed text/phonetic pair
        return $this->base->computeComputedTextPhoneticPair(
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
