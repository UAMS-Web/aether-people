<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use Statamic\Sites\Site;
use UAMSWeb\Aether\People\Lenses\Gates\ResearcherLensGate;

covers(ResearcherLensGate::class);

/**
 * The researcher lens gate admits a person exactly when they have a public research identity,
 * whichever site asks: researchers are scattered across sites and the org-to-people bridge has
 * no research role. `LensGatesTest` covers the signal on the default site; this covers the
 * independence from the site.
 *
 * @command  vendor/bin/pest tests/Integration/Lenses/ResearcherLensGateSiteTest.php
 */

/**
 * A `people` entry whose `get()` returns the given field values (null otherwise).
 *
 * @param  array<string, mixed>  $values
 */
function researcherGatePerson(array $values = []): Entry
{
    $entry = new Entry;
    $entry->data($values);

    return $entry;
}

/**
 * A site with the given handle.
 */
function researcherGateSite(string $handle): Site
{
    return new Site($handle, ['name' => $handle, 'locale' => 'en_US', 'url' => '/']);
}

it('admits a researcher to the researcher lens on any site', function (string $siteHandle): void {
    $person = researcherGatePerson(['orcid_id' => '0000-0002-1825-0097']);

    expect(app(ResearcherLensGate::class)->passes($person, researcherGateSite($siteHandle)))->toBeTrue();
})->with(['default', 'health', 'medicine_anesthesiology', 'research']);

it('rejects a non-researcher from the researcher lens on any site', function (string $siteHandle): void {
    $person = researcherGatePerson(['title' => 'James Kirk']);

    expect(app(ResearcherLensGate::class)->passes($person, researcherGateSite($siteHandle)))->toBeFalse();
})->with(['default', 'health', 'medicine_anesthesiology', 'research']);
