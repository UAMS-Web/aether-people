<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\Registration;

use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\AppointmentContextResolver;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ClinicalTitleResolver;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\MedicalOntologySuppressionResolver;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderProfileBodyResolver;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderResourcesArchiveUrlResolver;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ResearchPublicationSelectComputed;
use UAMSWeb\Aether\People\Support\PeopleCollection;

/**
 * Registers shared computed fields for the `clinical_providers` and `people` collections
 * (person-name variants, locations-derived values, PubMed publications, separation,
 * searchability).
 */
final readonly class ProvidersPeopleSharedCollectionComputedsRegistrar
{
    /**
     * Collection handles that receive the same providers-style computed field set.
     *
     * @return list<string>
     */
    private static function collectionHandles(): array
    {
        return [
            'clinical_providers',
            PeopleCollection::handle(),
        ];
    }

    /**
     * @param  ProviderPeoplePersonNameComputedValues  $personNameComputed  Person-name display and phonetic computeds.
     * @param  ProviderPeopleListingComputedValues  $listingComputed  Listing, portal, separation, and search computeds.
     * @param  ResearchPublicationSelectComputed  $researchPublicationSelectComputed  PubMed enrichment for publication select.
     * @param  ClinicalTitleResolver  $clinicalTitleResolver  Patient-facing clinical title from the primary specialization term (UAMS-Web/uams-statamic-archive#64).
     * @param  AppointmentContextResolver  $appointmentContextResolver  Appointment CTA + primary-location facts for the clinical lens (UAMS-Web/uams-statamic-archive#1676).
     * @param  ProviderProfileBodyResolver  $profileBodyResolver  Whether anything renders between the two appointment CTAs (UAMS-Web/uams-statamic-archive#1695).
     * @param  ProviderResourcesArchiveUrlResolver  $resourcesArchiveUrlResolver  The provider-filtered clinical resources archive URL (UAMS-Web/uams-statamic-archive#1692).
     * @param  MedicalOntologySuppressionResolver  $ontologySuppressionResolver  Whether a removal rule suppresses this provider's Areas of Expertise (UAMS-Web/uams-statamic-archive#1687).
     */
    public function __construct(
        private ProviderPeoplePersonNameComputedValues $personNameComputed,
        private ProviderPeopleListingComputedValues $listingComputed,
        private ResearchPublicationSelectComputed $researchPublicationSelectComputed,
        private ClinicalTitleResolver $clinicalTitleResolver,
        private AppointmentContextResolver $appointmentContextResolver,
        private ProviderProfileBodyResolver $profileBodyResolver,
        private ProviderResourcesArchiveUrlResolver $resourcesArchiveUrlResolver,
        private MedicalOntologySuppressionResolver $ontologySuppressionResolver
    ) {}

    /**
     * Register all shared providers and people collection computeds with Statamic.
     */
    public function register(): void
    {
        // Cache injected services in locals for readable first-class-callable bindings
        $names = $this->personNameComputed;
        $list = $this->listingComputed;

        // Register display_name_full computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'display_name_full',
            $names->getDisplayNameFull(...)
        );

        // Register display_name_full_with_prefix computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'display_name_full_with_prefix',
            $names->getDisplayNameFullWithPrefix(...)
        );

        // Register display_name_full_with_credentials computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'display_name_full_with_credentials',
            $names->getDisplayNameFullWithCredentials(...)
        );

        // Register display_name_full_with_clinical_credentials computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'display_name_full_with_clinical_credentials',
            $names->getDisplayNameFullWithClinicalCredentials(...)
        );

        // Register display_name_short_with_prefix computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'display_name_short_with_prefix',
            $names->getDisplayNameShortWithPrefix(...)
        );

        // Register full_name computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'full_name',
            $names->getFullName(...)
        );

        // Register full_name_with_credentials computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'full_name_with_credentials',
            $names->getFullNameWithCredentials(...)
        );

        // Register full_name_with_prefix computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'full_name_with_prefix',
            $names->getFullNameWithPrefix(...)
        );

        // Register nickname_list computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'nickname_list',
            $names->getNicknameList(...)
        );

        // Register alternate_name_list computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'alternate_name_list',
            $names->getAlternateNameList(...)
        );

        // Register legal_name_pairs pronounced legal name parts for both collections
        Collection::computed(
            self::collectionHandles(),
            'legal_name_pairs',
            $names->getLegalNamePairs(...)
        );

        // Register brand_organizations aggregated location entry computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'brand_organizations',
            $list->computeBrandOrganizations(...)
        );

        // Register regions aggregated location term computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'regions',
            $list->computeRegions(...)
        );

        // Register portal patient portal term computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'portal',
            $list->computePortal(...)
        );

        // Register prefix honorific computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'prefix',
            $names->getPrefixValue(...)
        );

        // Register asp_filter expertise and condition title list computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'asp_filter',
            $list->computeAspFilter(...)
        );

        // Register research_publication_select PubMed enrichment computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'research_publication_select',
            fn (Entry $entry): array => ($this->researchPublicationSelectComputed)($entry)
        );

        // Register separated_active_query computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'separated_active_query',
            $list->isSeparatedActive(...)
        );

        // Register separated_unpublish_query computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'separated_unpublish_query',
            $list->computeSeparatedUnpublishQuery(...)
        );

        // Register searchable computed for both collections
        Collection::computed(
            self::collectionHandles(),
            'searchable',
            $list->computeSearchable(...)
        );

        // Register clinical_title computed for both collections: the patient-facing
        // title of the primary clinical_specializations entry, rendered by the
        // Find-a-Doc provider card and shared with the SEO document_title (UAMS-Web/uams-statamic-archive#64).
        $clinicalTitle = $this->clinicalTitleResolver;
        Collection::computed(
            self::collectionHandles(),
            'clinical_title',
            fn (Entry $entry): string => $clinicalTitle->resolve($entry)
        );

        // Register appointment_context computed for both collections: the facts the
        // clinical lens's appointment CTA and Primary Appointment Location block are
        // keyed on - eligibility, published locations, the portal inherited from the
        // primary one, and the appointment line that portal implies (UAMS-Web/uams-statamic-archive#1676).
        $appointmentContext = $this->appointmentContextResolver;
        Collection::computed(
            self::collectionHandles(),
            'appointment_context',
            fn (Entry $entry): array => $appointmentContext->resolve($entry)
        );

        // Register provider_profile_body_rendered computed for both collections:
        // whether any section renders between the clinical lens's two appointment
        // CTAs, which is what gates the foot-of-page repeat (UAMS-Web/uams-statamic-archive#1695).
        $profileBody = $this->profileBodyResolver;
        Collection::computed(
            self::collectionHandles(),
            'provider_profile_body_rendered',
            fn (Entry $entry): bool => $profileBody->resolve($entry)
        );

        // Register provider_resources_url computed for both collections: the
        // clinical resources archive pre-filtered to this provider, which is the
        // lens's "more resources" destination (UAMS-Web/uams-statamic-archive#1692).
        $resourcesArchiveUrl = $this->resourcesArchiveUrlResolver;
        Collection::computed(
            self::collectionHandles(),
            'provider_resources_url',
            fn (Entry $entry): string => $resourcesArchiveUrl->resolve($entry)
        );

        // Register hide_medical_ontology computed for both collections: whether an
        // editor-configured removal rule suppresses this provider's Areas of
        // Expertise section on the profile (UAMS-Web/uams-statamic-archive#1687).
        $ontologySuppression = $this->ontologySuppressionResolver;
        Collection::computed(
            self::collectionHandles(),
            'hide_medical_ontology',
            fn (Entry $entry): bool => $ontologySuppression->resolve($entry)
        );
    }
}
