<?php

declare(strict_types=1);

use Illuminate\Support\Collection as IlluminateCollection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Support\ResearcherProfileEligibility;

uses(MockeryPHPUnitIntegration::class);

covers(ResearcherProfileEligibility::class);

/**
 * Coverage for {@see ResearcherProfileEligibility} - the person-level researcher
 * signal that gates the `people/researcher/show` lens (UAMS-Web/uams-statamic-archive#1667). A person has a public
 * research identity when ANY configured signal field is non-empty; the signal is
 * person-level (not a site's organization role) because researchers are scattered
 * across sites and the org-to-people bridge has no research role.
 *
 * @command  vendor/bin/pest tests/Integration/Support/ResearcherProfileEligibilityTest.php
 */

/**
 * A `people` entry whose `get()` returns the given field values (null otherwise).
 *
 * Keys are field handles; an int key is allowed so a test can plant a value
 * under a non-string signal handle.
 *
 * @param  array<array-key, mixed>  $values
 */
function researcherEntry(array $values = []): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    return mustBe(Entry::class, $entry, 'the entry double');
}

it('qualifies a person with a TRI research profile URL', function (): void {
    $person = researcherEntry(['research_profile_url' => 'https://tri.uams.edu/profile/spock']);

    expect(app(ResearcherProfileEligibility::class)->qualifies($person))->toBeTrue();
});

it('qualifies a person with any single scholarly author identifier', function (string $field): void {
    $person = researcherEntry([$field => '0000-0002-1825-0097']);

    expect(app(ResearcherProfileEligibility::class)->qualifies($person))->toBeTrue();
})->with([
    'orcid_id',
    'researcherid',
    'scopus_author_id',
    'google_scholar_author_id',
    'pubmed_author_id',
]);

it('qualifies a person with declared research-interest replicator rows', function (): void {
    // `research_interests` is a replicator (free_text / controlled_vocabulary set rows),
    // not a flat list - a non-empty rows array is the real "has research interests" shape.
    $person = researcherEntry(['research_interests' => [
        ['type' => 'free_text', 'text' => 'Xenobiology'],
    ]]);

    expect(app(ResearcherProfileEligibility::class)->qualifies($person))->toBeTrue();
});

it('qualifies a person with research interests as a non-empty collection', function (): void {
    $person = researcherEntry(['research_interests' => new IlluminateCollection(['oncology'])]);

    expect(app(ResearcherProfileEligibility::class)->qualifies($person))->toBeTrue();
});

it('rejects a person with no research signal at all', function (): void {
    $person = researcherEntry(['title' => 'James Kirk', 'job_title' => 'Captain']);

    expect(app(ResearcherProfileEligibility::class)->qualifies($person))->toBeFalse();
});

it('treats empty signal values as no signal', function (mixed $emptyValue): void {
    $person = researcherEntry([
        'research_profile_url' => $emptyValue,
        'orcid_id' => $emptyValue,
        'research_interests' => $emptyValue,
    ]);

    expect(app(ResearcherProfileEligibility::class)->qualifies($person))->toBeFalse();
})->with([
    'null' => [null],
    'empty string' => [''],
    'empty array' => [[]],
    'empty collection' => [new IlluminateCollection([])],
]);

it('is not a researcher when the signal list is misconfigured', function (): void {
    config()->set('researcher_profile.signals', 'not-an-array');

    $person = researcherEntry(['orcid_id' => '0000-0002-1825-0097']);

    expect(app(ResearcherProfileEligibility::class)->qualifies($person))->toBeFalse();
});

it('skips blank and non-string signal handles before reading the entry', function (): void {
    config()->set('researcher_profile.signals', ['', 42, 'orcid_id']);

    expect(app(ResearcherProfileEligibility::class)->qualifies(
        researcherEntry(['orcid_id' => '0000-0002-1825-0097']),
    ))->toBeTrue()
        ->and(app(ResearcherProfileEligibility::class)->qualifies(
            researcherEntry(['orcid_id' => '']),
        ))->toBeFalse();
});

it('never qualifies on an empty-string signal handle alone', function (): void {
    config()->set('researcher_profile.signals', ['']);

    expect(app(ResearcherProfileEligibility::class)->qualifies(
        researcherEntry([
            '' => 'non-empty',
            'orcid_id' => '0000-0002-1825-0097',
        ]),
    ))->toBeFalse();
});

it('ignores a non-string signal handle even when that key is non-empty', function (): void {
    config()->set('researcher_profile.signals', [42]);

    expect(app(ResearcherProfileEligibility::class)->qualifies(
        researcherEntry([
            42 => 'bogus-signal',
            'orcid_id' => '0000-0002-1825-0097',
        ]),
    ))->toBeFalse();
});
