<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople;

use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName\AlternateNameCollector;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName\AlternateNameListBuilder;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName\AlternateNamePhoneticCoherence;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName\PersonNameBaseComputeds;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName\PersonNameNicknameComputeds;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\PersonName\PersonNamePrefixAndCredentialComputeds;

/**
 * Person-name Statamic computed field values for providers and people entries.
 *
 * Resolves display strings, IPA phonetics, nickname and alternate name expansions,
 * and credential-augmented variants from `person_name` and related fields.
 *
 * A facade over the classes in the `PersonName` namespace, which hold the methods this class
 * used to carry. It keeps the class name and the 12 public signatures, so the shared
 * registrar, the payload transformer and the site's spotlight computed values call it as before.
 */
class ProviderPeoplePersonNameComputedValues
{
    /**
     * The base computeds and shared helpers.
     */
    private readonly PersonNameBaseComputeds $base;

    /**
     * The nickname list computed.
     */
    private readonly PersonNameNicknameComputeds $nicknames;

    /**
     * The alternate-name list computed.
     */
    private readonly AlternateNameListBuilder $alternateNames;

    /**
     * The prefix and credential variants.
     */
    private readonly PersonNamePrefixAndCredentialComputeds $prefixAndCredentials;

    /**
     * Build the pieces over one shared base, so every piece resolves names the same way.
     */
    public function __construct()
    {
        // The base every other piece calls into
        $this->base = new PersonNameBaseComputeds;

        // Nicknames, then the alternate-name pieces that build on them
        $this->nicknames = new PersonNameNicknameComputeds($this->base);
        $this->alternateNames = new AlternateNameListBuilder(
            $this->nicknames,
            new AlternateNameCollector($this->base),
            new AlternateNamePhoneticCoherence($this->base)
        );

        // Prefix and credential variants
        $this->prefixAndCredentials = new PersonNamePrefixAndCredentialComputeds($this->base);
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
        // Delegate to the class that now holds the method
        return $this->prefixAndCredentials->getPrefixValue($entry);
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
        // Delegate to the class that now holds the method
        return $this->base->getLegalNamePairs($entry);
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
        // Delegate to the class that now holds the method
        return $this->base->getDisplayNameFull($entry);
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
        // Delegate to the class that now holds the method
        return $this->base->getFullName($entry);
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
        // Delegate to the class that now holds the method
        return $this->nicknames->getNicknameList($entry);
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
        // Delegate to the class that now holds the method
        return $this->alternateNames->getAlternateNameList($entry);
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
        // Delegate to the class that now holds the method
        return $this->prefixAndCredentials->getDisplayNameFullWithPrefix($entry);
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
        // Delegate to the class that now holds the method
        return $this->prefixAndCredentials->getDisplayNameFullWithCredentials($entry);
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
        // Delegate to the class that now holds the method
        return $this->prefixAndCredentials->getDisplayNameFullWithClinicalCredentials($entry);
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
        // Delegate to the class that now holds the method
        return $this->prefixAndCredentials->getDisplayNameShortWithPrefix($entry);
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
        // Delegate to the class that now holds the method
        return $this->prefixAndCredentials->getFullNameWithPrefix($entry);
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
        // Delegate to the class that now holds the method
        return $this->prefixAndCredentials->getFullNameWithCredentials($entry);
    }
}
