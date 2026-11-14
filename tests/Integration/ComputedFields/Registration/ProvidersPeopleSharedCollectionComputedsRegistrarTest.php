<?php

declare(strict_types=1);

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
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
use UAMSWeb\Aether\People\ComputedFields\Registration\ProvidersPeopleSharedCollectionComputedsRegistrar;
use UAMSWeb\Aether\People\Contracts\PublicationCitations;

uses(MockeryPHPUnitIntegration::class);

covers(ProvidersPeopleSharedCollectionComputedsRegistrar::class);

/**
 * The full set of computed handles the registrar owns, in registration order.
 *
 * @var list<string>
 */
const PP_SHARED_HANDLES = [
    'display_name_full',
    'display_name_full_with_prefix',
    'display_name_full_with_credentials',
    'display_name_full_with_clinical_credentials',
    'display_name_short_with_prefix',
    'full_name',
    'full_name_with_credentials',
    'full_name_with_prefix',
    'nickname_list',
    'alternate_name_list',
    'legal_name_pairs',
    'brand_organizations',
    'regions',
    'portal',
    'prefix',
    'asp_filter',
    'research_publication_select',
    'separated_active_query',
    'separated_unpublish_query',
    'searchable',
    'clinical_title',
    'appointment_context',
    'provider_profile_body_rendered',
    'provider_resources_url',
    'hide_medical_ontology',
];

/**
 * A bare entry that stores nothing, so every resolver reads its defaults.
 */
function ppSharedBareEntry(): Entry
{
    $entry = Mockery::mock(Entry::class)->shouldIgnoreMissing();
    $entry->shouldReceive('get')->andReturnUsing(fn (string $key, mixed $default = null): mixed => $default);

    return mustBe(Entry::class, $entry, 'the entry double');
}

/**
 * The registrar over the given name and listing services and a publication-citations service, with
 * the resolvers the container builds.
 */
function ppSharedRegistrar(
    MockInterface $names,
    MockInterface $listing,
    ?PublicationCitations $citations = null,
): ProvidersPeopleSharedCollectionComputedsRegistrar {
    return new ProvidersPeopleSharedCollectionComputedsRegistrar(
        mustBe(ProviderPeoplePersonNameComputedValues::class, $names, 'the name computeds double'),
        mustBe(ProviderPeopleListingComputedValues::class, $listing, 'the listing computeds double'),
        new ResearchPublicationSelectComputed($citations ?? app(PublicationCitations::class)),
        app(ClinicalTitleResolver::class),
        app(AppointmentContextResolver::class),
        app(ProviderProfileBodyResolver::class),
        app(ProviderResourcesArchiveUrlResolver::class),
        app(MedicalOntologySuppressionResolver::class)
    );
}

/**
 * Register the registrar against a stubbed `Collection::computed` and return each handle's callback.
 *
 * @return array<string, callable>
 */
function ppSharedCapture(ProvidersPeopleSharedCollectionComputedsRegistrar $registrar): array
{
    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collections, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    $registrar->register();

    return $captured;
}

/**
 * @command  vendor/bin/pest tests/Integration/ComputedFields/Registration/ProvidersPeopleSharedCollectionComputedsRegistrarTest.php
 */
it('registers every shared provider/people computed exactly once on both collections', function (): void {
    // Resolve from the container (the ResearchPublicationSelectComputed dependency is
    // `final`, so the whole graph is easier real than mocked) and capture the surface.
    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collections, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collections;
        }
    );

    app(ProvidersPeopleSharedCollectionComputedsRegistrar::class)->register();

    // Exactly one registration per handle: a duplicate registration (same handle
    // twice) would collapse in the keyed map, so pin the raw call count too.
    expect($callCount)->toBe(count(PP_SHARED_HANDLES) + 1)
        ->and(array_keys($captured))->toEqualCanonicalizing([...PP_SHARED_HANDLES, 'clinical_provider_profile_eligible_query']);

    // Each shared computed targets exactly both consumer collections.
    foreach (PP_SHARED_HANDLES as $handle) {
        expect($captured[$handle])->toBe(['clinical_providers', 'people']);
    }

    // The eligibility flag is for people alone
    expect($captured['clinical_provider_profile_eligible_query'])->toBe('people');
});

it('targets the configured people collection handle', function (): void {
    config()->set('people.collection', 'staff');

    $targets = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collections, string $handle) use (&$targets): void {
            $targets[$handle] = $collections;
        }
    );

    app(ProvidersPeopleSharedCollectionComputedsRegistrar::class)->register();

    expect($targets['searchable'])->toBe(['clinical_providers', 'staff'])
        ->and($targets['clinical_provider_profile_eligible_query'])->toBe('staff');
});

it('wires each name and listing handle to its owning service method', function (): void {
    $names = Mockery::mock(ProviderPeoplePersonNameComputedValues::class);
    $listing = Mockery::mock(ProviderPeopleListingComputedValues::class);
    $entry = Mockery::mock(Entry::class);

    // Each method answers with its own name, so a swap between sibling bindings surfaces.
    $nameMethods = [
        'display_name_full' => 'getDisplayNameFull',
        'display_name_full_with_prefix' => 'getDisplayNameFullWithPrefix',
        'display_name_full_with_credentials' => 'getDisplayNameFullWithCredentials',
        'display_name_full_with_clinical_credentials' => 'getDisplayNameFullWithClinicalCredentials',
        'display_name_short_with_prefix' => 'getDisplayNameShortWithPrefix',
        'full_name' => 'getFullName',
        'full_name_with_credentials' => 'getFullNameWithCredentials',
        'full_name_with_prefix' => 'getFullNameWithPrefix',
        'nickname_list' => 'getNicknameList',
        'alternate_name_list' => 'getAlternateNameList',
        'legal_name_pairs' => 'getLegalNamePairs',
        'prefix' => 'getPrefixValue',
    ];
    $listingMethods = [
        'brand_organizations' => 'computeBrandOrganizations',
        'regions' => 'computeRegions',
        'portal' => 'computePortal',
        'asp_filter' => 'computeAspFilter',
        'separated_active_query' => 'isSeparatedActive',
        'separated_unpublish_query' => 'computeSeparatedUnpublishQuery',
        'searchable' => 'computeSearchable',
    ];

    foreach ($nameMethods as $method) {
        $names->shouldReceive($method)->with($entry)->andReturn(['text' => $method]);
    }

    // Typed returns need a value of the right type, so each listing method answers with a distinct one
    $listingAnswers = [
        'computeBrandOrganizations' => ['brand'],
        'computeRegions' => ['regions'],
        'computePortal' => 'portal',
        'computeAspFilter' => 'asp',
        'isSeparatedActive' => true,
        'computeSeparatedUnpublishQuery' => false,
        'computeSearchable' => true,
    ];
    foreach ($listingMethods as $method) {
        $listing->shouldReceive($method)->with($entry)->andReturn($listingAnswers[$method]);
    }

    $captured = ppSharedCapture(ppSharedRegistrar($names, $listing));

    foreach ($nameMethods as $handle => $method) {
        expect($captured[$handle]($entry))->toBe(['text' => $method]);
    }

    foreach ($listingMethods as $handle => $method) {
        expect($captured[$handle]($entry))->toBe($listingAnswers[$method]);
    }
});

it('enriches research_publication_select through the publication-citations contract', function (): void {
    $citations = new class implements PublicationCitations
    {
        public function enrichResearchPublicationSelectSets(array $selectPublications): array
        {
            return ['enriched' => $selectPublications];
        }
    };

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->with('research_publication_select', [])->andReturn([['id' => 'a']]);

    $captured = ppSharedCapture(ppSharedRegistrar(
        Mockery::mock(ProviderPeoplePersonNameComputedValues::class),
        Mockery::mock(ProviderPeopleListingComputedValues::class),
        $citations,
    ));

    expect($captured['research_publication_select']($entry))->toBe(['enriched' => [['id' => 'a']]]);
});

it('binds each resolver handle to a callback that returns its resolver result for an entry', function (): void {
    $entry = ppSharedBareEntry();

    $captured = ppSharedCapture(ppSharedRegistrar(
        Mockery::mock(ProviderPeoplePersonNameComputedValues::class),
        Mockery::mock(ProviderPeopleListingComputedValues::class),
    ));

    expect($captured['clinical_title']($entry))->toBe(app(ClinicalTitleResolver::class)->resolve($entry))
        ->and($captured['appointment_context']($entry))->toBe(app(AppointmentContextResolver::class)->resolve($entry))
        ->and($captured['provider_profile_body_rendered']($entry))->toBe(app(ProviderProfileBodyResolver::class)->resolve($entry))
        ->and($captured['provider_resources_url']($entry))->toBe(app(ProviderResourcesArchiveUrlResolver::class)->resolve($entry))
        ->and($captured['hide_medical_ontology']($entry))->toBe(app(MedicalOntologySuppressionResolver::class)->resolve($entry));
});

it('binds the eligibility flag on people to the listing eligibility computation', function (): void {
    $listing = Mockery::mock(ProviderPeopleListingComputedValues::class);
    $entry = Mockery::mock(Entry::class);
    $listing->shouldReceive('computeClinicalProviderProfileEligibleQuery')->with($entry)->once()->andReturn(true);

    $captured = ppSharedCapture(ppSharedRegistrar(Mockery::mock(ProviderPeoplePersonNameComputedValues::class), $listing));

    expect($captured['clinical_provider_profile_eligible_query']($entry))->toBeTrue();
});
