<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ResearchPublicationSelectComputed;
use UAMSWeb\Aether\People\Contracts\PublicationCitations;

covers(ResearchPublicationSelectComputed::class);

/**
 * The `research_publication_select` computed hands the entry's stored sets to the host's
 * `PublicationCitations` service and returns what it answers (UAMS-Web/uams-statamic-archive#865:
 * the service reads its cache only, so the computed never blocks a render on PubMed).
 *
 * The site's tests of the PubMed cache behavior stay with the service in the host; here a fake
 * contract records its input.
 *
 * @command  vendor/bin/pest tests/Integration/ComputedFields/ProviderPeople/ResearchPublicationSelectComputedTest.php
 */

/**
 * A citations fake that records each input and answers a fixed result.
 */
final class ResearchComputedCitationsFake implements PublicationCitations
{
    /**
     * @var list<array<int|string, mixed>> The sets handed over, in call order.
     */
    public array $received = [];

    /**
     * @param  array<int|string, mixed>  $answer  What `enrichResearchPublicationSelectSets()` returns.
     */
    public function __construct(private readonly array $answer) {}

    public function enrichResearchPublicationSelectSets(array $selectPublications): array
    {
        $this->received[] = $selectPublications;

        return $this->answer;
    }
}

/**
 * An unsaved entry holding the given data.
 *
 * @param  array<string, mixed>  $data
 */
function researchComputedEntry(array $data): Entry
{
    $entry = new Entry;
    $entry->data($data);

    return $entry;
}

it('hands the stored sets to the citations service and returns its answer', function (): void {
    $sets = ['abc' => ['pmid' => '456'], 'def' => ['pmid' => '789']];
    $answer = ['abc' => ['pmid' => '456', 'pubmed_information' => 'PMID: 456']];
    $fake = new ResearchComputedCitationsFake($answer);
    app()->instance(PublicationCitations::class, $fake);

    $out = app(ResearchPublicationSelectComputed::class)(researchComputedEntry(['research_publication_select' => $sets]));

    expect($out)->toBe($answer)
        ->and($fake->received)->toBe([$sets]);
});

it('hands an empty array to the citations service when the entry stores no sets', function (): void {
    $fake = new ResearchComputedCitationsFake([]);
    app()->instance(PublicationCitations::class, $fake);

    $out = app(ResearchPublicationSelectComputed::class)(researchComputedEntry([]));

    expect($out)->toBe([])
        ->and($fake->received)->toBe([[]]);
});
