<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteInstance;
use UAMSWeb\Aether\People\Contracts\ClinicalProviderEligibility;
use UAMSWeb\Aether\People\Contracts\DepartmentHubRoster;
use UAMSWeb\Aether\People\Contracts\ResearcherEligibility;
use UAMSWeb\Aether\People\Lenses\Gates\AcademicLensGate;
use UAMSWeb\Aether\People\Lenses\Gates\ClinicalProviderLensGate;
use UAMSWeb\Aether\People\Lenses\Gates\InstitutionalLensGate;
use UAMSWeb\Aether\People\Lenses\Gates\ResearcherLensGate;

covers(
    ClinicalProviderLensGate::class,
    ResearcherLensGate::class,
    AcademicLensGate::class,
    InstitutionalLensGate::class,
);

/**
 * The lens gates over the contracts a host binds. Each gate reads the host's answer
 * and nothing else, so a fake contract stands in for the host (UAMS-Web/uams-statamic#281).
 *
 * @command vendor/bin/pest tests/Integration/Lenses/LensGatesTest.php
 */
beforeEach(function (): void {
    // `value()` reads through the entry's collection, so the collection must exist
    Collection::make('organizations')->save();
});

/**
 * The default site, narrowed to its class.
 */
function lensGateSite(): SiteInstance
{
    return mustBe(SiteInstance::class, Site::default(), 'the default site');
}

/**
 * A `people` entry with the given id.
 */
function lensGatePerson(string $id): Entry
{
    $entry = new Entry;
    $entry->id($id);

    return $entry;
}

/**
 * A hub roster fake: the organization for each site handle, and the people in each organization.
 *
 * @param  array<array-key, Entry>  $organizations  The organization by site handle.
 * @param  array<string, list<Entry>>  $rosters  The people by organization id.
 */
function lensGateRoster(array $organizations, array $rosters = []): DepartmentHubRoster
{
    return new readonly class($organizations, $rosters) implements DepartmentHubRoster
    {
        /**
         * @param  array<array-key, Entry>  $organizations
         * @param  array<string, list<Entry>>  $rosters
         */
        public function __construct(private array $organizations, private array $rosters) {}

        public function organization(string $siteHandle): ?Entry
        {
            return $this->organizations[$siteHandle] ?? null;
        }

        public function resolvePeople(Entry $organization): EntryCollection
        {
            $id = $organization->id();

            return new EntryCollection(\is_string($id) ? ($this->rosters[$id] ?? []) : []);
        }
    };
}

/**
 * An `organizations` entry with an id and a role.
 */
function lensGateOrganization(string $id, ?string $role): Entry
{
    $entry = new Entry;
    $entry->collection('organizations');
    $entry->id($id);
    $entry->data(['organization_role' => $role]);

    return $entry;
}

it('admits a person to the clinical lens exactly when the host says they qualify', function (bool $qualifies): void {
    $eligibility = new readonly class($qualifies) implements ClinicalProviderEligibility
    {
        public function __construct(private bool $qualifies) {}

        public function qualifies(Entry $entry): bool
        {
            return $this->qualifies;
        }
    };

    expect(new ClinicalProviderLensGate($eligibility)->passes(lensGatePerson('p1'), lensGateSite()))->toBe($qualifies);
})->with([true, false]);

it('admits a person to the researcher lens exactly when the host says they qualify', function (bool $qualifies): void {
    $eligibility = new readonly class($qualifies) implements ResearcherEligibility
    {
        public function __construct(private bool $qualifies) {}

        public function qualifies(Entry $entry): bool
        {
            return $this->qualifies;
        }
    };

    expect(new ResearcherLensGate($eligibility)->passes(lensGatePerson('p1'), lensGateSite()))->toBe($qualifies);
})->with([true, false]);

it('admits a rostered person on a hub site whose organization has a role the lens serves', function (string $role, bool $academic): void {
    $person = lensGatePerson('p1');
    $roster = lensGateRoster(['default' => lensGateOrganization('o1', $role)], ['o1' => [$person]]);
    $gate = $academic ? new AcademicLensGate($roster) : new InstitutionalLensGate($roster);

    expect($gate->passes($person, lensGateSite()))->toBeTrue();
})->with([
    'department academic' => ['department_academic', true],
    'division academic' => ['division_academic', true],
    'department institutional' => ['department_institutional', false],
]);

it('refuses the org lenses when the site is not a hub, the role is not served, or the person is not rostered', function (): void {
    $person = lensGatePerson('p1');
    $other = lensGatePerson('p2');
    $org = lensGateOrganization('o1', 'department_academic');

    // No hub organization for the site
    expect(new AcademicLensGate(lensGateRoster([]))->passes($person, lensGateSite()))->toBeFalse();

    // The organization has the other lens's role
    expect(new InstitutionalLensGate(lensGateRoster(['default' => $org], ['o1' => [$person]]))->passes($person, lensGateSite()))->toBeFalse();

    // An organization with no role at all
    $roleless = lensGateOrganization('o2', null);
    expect(new AcademicLensGate(lensGateRoster(['default' => $roleless], ['o2' => [$person]]))->passes($person, lensGateSite()))->toBeFalse();

    // The person is not in the organization's roster
    expect(new AcademicLensGate(lensGateRoster(['default' => $org], ['o1' => [$other]]))->passes($person, lensGateSite()))->toBeFalse();

    // The person has no id
    expect(new AcademicLensGate(lensGateRoster(['default' => $org], ['o1' => [$person]]))->passes(new Entry, lensGateSite()))->toBeFalse();
});

it('refuses the org lenses for a site whose handle is empty or not a string, without asking the host', function (mixed $handle): void {
    $person = lensGatePerson('p1');
    $org = lensGateOrganization('o1', 'department_academic');
    // The roster would admit the person under any handle the gate dared to ask about
    $roster = lensGateRoster(['' => $org, '5' => $org], ['o1' => [$person]]);
    $site = new class($handle) extends SiteInstance
    {
        public function __construct(private readonly mixed $fakeHandle)
        {
            parent::__construct('default', ['name' => 'Default', 'locale' => 'en_US', 'url' => '/']);
        }

        /**
         * @return mixed
         */
        #[Override]
        public function handle()
        {
            return $this->fakeHandle;
        }
    };

    expect(new AcademicLensGate($roster)->passes($person, $site))->toBeFalse();
})->with(['empty' => [''], 'integer' => [5], 'null' => [null]]);

it('refuses the org lenses for a person whose id is empty, even when the roster holds an empty id', function (): void {
    $blank = lensGatePerson('');
    $roster = lensGateRoster(['default' => lensGateOrganization('o1', 'department_academic')], ['o1' => [$blank]]);

    expect(new AcademicLensGate($roster)->passes($blank, lensGateSite()))->toBeFalse();
});
