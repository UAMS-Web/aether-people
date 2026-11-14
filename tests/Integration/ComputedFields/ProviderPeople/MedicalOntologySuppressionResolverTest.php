<?php

declare(strict_types=1);

use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Path;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Globals\GlobalSet as GlobalSetInstance;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteInstance;
use Statamic\Stache\Stores\Store;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\MedicalOntologySuppressionResolver;
use UAMSWeb\Aether\People\Contracts\LocationRegions;

covers(MedicalOntologySuppressionResolver::class);

/**
 * The Find-a-Doc **Remove Medical Ontology** rules (UAMS-Web/uams-statamic-archive#1687): whether a provider's Areas
 * of Expertise section is suppressed by an editor-configured removal row.
 *
 * Every case runs against real persisted location entries and a real global, because the region
 * side is a Stache walk: a provider's `regions` is an aggregate of the regions its linked
 * locations resolve to, so it has to actually resolve rather than be mocked. The host's
 * `LocationRegions` service (which follows a child location to its parent) is replaced by a
 * fake that answers each location's stored `regions`. The provider is an unsaved entry, because
 * saving one would resolve the host's `person` blueprint. The site keeps the two cases that read
 * the `hide_medical_ontology` computed through augmentation; the registrar's own test covers
 * that the handle is wired.
 *
 * Two decisions are pinned here rather than merely implemented, because both diverge
 * deliberately from WordPress and a future reader would otherwise "fix" them back:
 *
 * - **Region matching is ANY, not sole.** WordPress compares
 *   `in_array(implode('', $provider_region), $remove_region)`, so a multi-region provider
 *   concatenates to `"6566"` and can never match `["65"]`. The multi-region case below is
 *   the guard against that accident being reintroduced as though it were the rule.
 * - **Both storage shapes must match.** A `max_items: 1` field is a bare scalar when
 *   saved through the CP and a single-element array when written programmatically, and
 *   both are present in real content.
 *
 * @command  vendor/bin/pest tests/Integration/ComputedFields/ProviderPeople/MedicalOntologySuppressionResolverTest.php
 */
beforeEach(function (): void {
    // Keep the Stache out of the shared fixtures directory, one directory per test
    $root = storage_path('framework/testing/stache/'.uniqid('ontology-suppression-', true).'/dev-null');
    Stache::stores()->each(function (Store $store) use ($root): void {
        $store->directory(Path::tidy($root.'/'.$store->key()));
    });
    Stache::clear();

    // The host answers a location's regions (following a child to its parent); the fake answers the stored value
    app()->instance(LocationRegions::class, new class implements LocationRegions
    {
        public function resolveRegions(EntryInstance $location): mixed
        {
            return $location->get('regions');
        }
    });

    foreach (['people', 'clinical_locations'] as $handle) {
        $collection = CollectionFacade::make($handle);
        $collection->title($handle);
        $collection->save();
    }

    // The global is deliberately NOT created here. One case below covers a site whose
    // editor has never opened the Find-a-Doc settings at all, and creating it in the
    // shared setup would leave that case unable to express its own precondition.
});

/**
 * Persist the Find-a-Doc settings global carrying the given removal rows.
 *
 * Passing no rows is the unconfigured state the feature ships in.
 *
 * Typed loosely on purpose: the malformed-row case below feeds it editor data that is
 * not a row at all, which is exactly what the resolver has to survive.
 *
 * @param  list<mixed>|null  $rows
 */
function ontologySuppressionSettings(?array $rows = null): void
{
    $set = mustBe(GlobalSetInstance::class, GlobalSet::make('uams_fad_settings'), 'the settings global');
    $set->title('Find-a-Doc Settings');
    $set->save();

    $defaultSite = mustBe(SiteInstance::class, Site::default(), 'the default site');

    $vars = mustBe(Variables::class, $set->makeLocalization($defaultSite->handle()), 'the settings variables');
    $vars->data($rows === null ? [] : ['remove_ontology_criteria' => $rows]);
    $vars->save();
}

/**
 * Build one removal row. A null criterion is an unset field, not an empty string.
 *
 * @return array<string, mixed>
 */
function ontologySuppressionRow(mixed $serviceLine = null, mixed $region = null, bool $enabled = true): array
{
    $row = ['type' => 'new_removal', 'enabled' => $enabled];

    if ($serviceLine !== null) {
        $row['remove_service_lines'] = $serviceLine;
    }

    if ($region !== null) {
        $row['remove_regions'] = $region;
    }

    return $row;
}

/**
 * Persist a location carrying a region slug, and return its id.
 *
 * A location's `regions` is a single-taxonomy terms field at `max_items: 1`, which
 * `Terms::process()` persists as a bare scalar slug.
 */
function ontologySuppressionLocation(string $id, mixed $region): string
{
    $entry = mustBe(EntryInstance::class, Entry::make(), 'an entry');
    $entry->collection('clinical_locations');
    $entry->id($id);
    $entry->slug($id);
    $entry->published(true);
    $entry->data(['title' => ucfirst($id)] + ($region === null ? [] : ['regions' => $region]));
    $entry->saveQuietly();

    return $id;
}

/**
 * Whether the resolver suppresses a provider carrying the given stored data.
 *
 * @param  array<string, mixed>  $data
 */
function ontologySuppressionResolves(array $data): bool
{
    $provider = new EntryInstance;
    $provider->collection('people');
    $provider->id('mccoy');
    $provider->slug('mccoy');
    $provider->data($data + ['title' => 'Leonard McCoy']);

    return app(MedicalOntologySuppressionResolver::class)->resolve($provider);
}

// --- The unconfigured feature ------------------------------------------------

it('suppresses nothing while no removal rows are configured', function (): void {
    // The shipped state: the criteria exist in the blueprint and nothing populates
    // them yet, so the feature must be completely inert rather than default-on.
    ontologySuppressionSettings();

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeFalse();
});

it('suppresses nothing when the settings global does not exist at all', function (): void {
    // A site whose editor has never opened the Find-a-Doc settings must not fatal and
    // must not suppress. `?->inDefaultSite()` is what carries this. The global is never
    // created in this case - the shared setup deliberately leaves it absent.
    expect(GlobalSet::findByHandle('uams_fad_settings'))->toBeNull();

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
    ]))->toBeFalse();
});

it('treats an all-empty row as matching nothing', function (): void {
    // WordPress has no branch that fires without at least one non-empty criterion. A row
    // an editor added and abandoned must not suppress every provider on the site - the
    // most destructive way this feature could fail.
    ontologySuppressionSettings([ontologySuppressionRow()]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeFalse();
});

// --- Single-criterion rows ---------------------------------------------------

it('suppresses on a region-only row when the provider is in that region', function (): void {
    ontologySuppressionSettings([ontologySuppressionRow(region: 'northwest-arkansas')]);

    expect(ontologySuppressionResolves([
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeTrue();
});

it('leaves a provider in a different region alone on a region-only row', function (): void {
    ontologySuppressionSettings([ontologySuppressionRow(region: 'northwest-arkansas')]);

    expect(ontologySuppressionResolves([
        'clinical_locations' => [ontologySuppressionLocation('central-clinic', 'central-arkansas')],
    ]))->toBeFalse();
});

it('suppresses on a service-line-only row when the provider matches', function (): void {
    ontologySuppressionSettings([ontologySuppressionRow(serviceLine: 'surgical-specialties')]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
    ]))->toBeTrue();
});

it('leaves a provider on a different service line alone', function (): void {
    ontologySuppressionSettings([ontologySuppressionRow(serviceLine: 'surgical-specialties')]);

    expect(ontologySuppressionResolves([
        'service_line' => 'primary-care',
    ]))->toBeFalse();
});

// --- Both criteria set: every non-empty criterion must match -----------------

it('suppresses when both criteria are set and both match', function (): void {
    ontologySuppressionSettings([
        ontologySuppressionRow(serviceLine: 'surgical-specialties', region: 'northwest-arkansas'),
    ]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeTrue();
});

it('does not suppress when only the region half of a two-criterion row matches', function (): void {
    // The partial-match case. A row naming both criteria is narrower than either alone,
    // and an implementation that ORed them would suppress far more than the editor asked.
    ontologySuppressionSettings([
        ontologySuppressionRow(serviceLine: 'surgical-specialties', region: 'northwest-arkansas'),
    ]);

    expect(ontologySuppressionResolves([
        'service_line' => 'primary-care',
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeFalse();
});

it('does not suppress when only the service-line half of a two-criterion row matches', function (): void {
    ontologySuppressionSettings([
        ontologySuppressionRow(serviceLine: 'surgical-specialties', region: 'northwest-arkansas'),
    ]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
        'clinical_locations' => [ontologySuppressionLocation('central-clinic', 'central-arkansas')],
    ]))->toBeFalse();
});

// --- Decision 4: ANY region, not sole region --------------------------------

it('suppresses a multi-region provider when any one of their regions matches', function (): void {
    // The decision-4 pin. WordPress concatenates a provider's region ids with
    // `implode('')` before comparing, so a provider in two regions becomes `"6566"` and
    // can never match a removal list of `["65"]` - only a SOLE-region provider can be
    // suppressed there. That is a string-concatenation accident rather than a rule, and
    // our aggregated `regions` makes multi-region providers ordinary, so reproducing it
    // would leave the feature nearly inert. If this case ever goes red because someone
    // "restored parity", read `docs/DECISIONS.md` before changing it back.
    ontologySuppressionSettings([ontologySuppressionRow(region: 'northwest-arkansas')]);

    expect(ontologySuppressionResolves([
        'clinical_locations' => [
            ontologySuppressionLocation('central-clinic', 'central-arkansas'),
            ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas'),
        ],
    ]))->toBeTrue();
});

it('leaves a multi-region provider alone when none of their regions match', function (): void {
    // Bounds the case above on the other side: "any" must still mean "any", not "always".
    ontologySuppressionSettings([ontologySuppressionRow(region: 'northwest-arkansas')]);

    expect(ontologySuppressionResolves([
        'clinical_locations' => [
            ontologySuppressionLocation('central-clinic', 'central-arkansas'),
            ontologySuppressionLocation('delta-clinic', 'delta'),
        ],
    ]))->toBeFalse();
});

// --- Absent values ----------------------------------------------------------

it('does not suppress a provider with no service line against a service-line row', function (): void {
    // `service_line` is conditional and its `required_unless` leaves real gaps, so an
    // absent value is ordinary. A null-vs-null comparison must not read as a match.
    ontologySuppressionSettings([ontologySuppressionRow(serviceLine: 'surgical-specialties')]);

    expect(ontologySuppressionResolves([]))->toBeFalse();
});

it('does not suppress a provider with no locations against a region row', function (): void {
    // A provider with no regions is normal rather than exceptional: the location `regions`
    // field is hidden for a facility and for a child location in the structured tree, so
    // those locations contribute nothing to the aggregate.
    ontologySuppressionSettings([ontologySuppressionRow(region: 'northwest-arkansas')]);

    expect(ontologySuppressionResolves([]))->toBeFalse();
});

it('does not suppress when the provider has locations that carry no region', function (): void {
    ontologySuppressionSettings([ontologySuppressionRow(region: 'northwest-arkansas')]);

    expect(ontologySuppressionResolves([
        'clinical_locations' => [ontologySuppressionLocation('facility', null)],
    ]))->toBeFalse();
});

// --- Storage shapes ---------------------------------------------------------

it('matches a criterion stored as a single-element array against a scalar provider value', function (): void {
    // A `max_items: 1` field is a bare scalar through the CP and a single-element array
    // when written programmatically. Both shapes are present in real content, so a direct
    // `===` between the two sides silently fails to match.
    ontologySuppressionSettings([ontologySuppressionRow(serviceLine: ['surgical-specialties'])]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
    ]))->toBeTrue();
});

it('matches a provider service line stored as a single-element array', function (): void {
    ontologySuppressionSettings([ontologySuppressionRow(serviceLine: 'surgical-specialties')]);

    expect(ontologySuppressionResolves([
        'service_line' => ['surgical-specialties'],
    ]))->toBeTrue();
});

it('matches a region a location stored as a single-element array', function (): void {
    // The provider side of the shape problem, and the half that was missing. A location
    // written programmatically stores `regions: ['slug']` while a CP save stores the bare
    // slug, and `computeRegions()` maps each location to its RAW value - so the aggregate
    // can hold either shape. `unique()` cannot collapse them, because a loose `==` does
    // not equate `'x'` and `['x']`. Comparing only the string ones silently skipped a
    // location the rule was written to catch.
    ontologySuppressionSettings([ontologySuppressionRow(region: 'northwest-arkansas')]);

    expect(ontologySuppressionResolves([
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', ['northwest-arkansas'])],
    ]))->toBeTrue();
});

it('matches when one of a mixed-shape set of locations carries the region', function (): void {
    // Both shapes on one provider at once, which is what makes this reachable in real
    // content rather than only in a fixture.
    ontologySuppressionSettings([ontologySuppressionRow(region: 'northwest-arkansas')]);

    expect(ontologySuppressionResolves([
        'clinical_locations' => [
            ontologySuppressionLocation('central-clinic', 'central-arkansas'),
            ontologySuppressionLocation('northwest-clinic', ['northwest-arkansas']),
        ],
    ]))->toBeTrue();
});

it('matches a region criterion stored as a single-element array', function (): void {
    ontologySuppressionSettings([ontologySuppressionRow(region: ['northwest-arkansas'])]);

    expect(ontologySuppressionResolves([
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeTrue();
});

it('treats an empty-array criterion as unset rather than as a value', function (): void {
    // An editor who added a row and cleared the field leaves `[]`, not absence. Read as a
    // value it would match nothing and, paired with a set criterion, silently disable the
    // row; read as unset - which is what it means - the row is all-empty and matches
    // nothing, which is the same outcome by the correct route.
    ontologySuppressionSettings([ontologySuppressionRow(serviceLine: [], region: [])]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
    ]))->toBeFalse();
});

// --- Multiple rows ----------------------------------------------------------

it('reads every configured row rather than stopping at the first non-matching one', function (): void {
    // A loop that returned on the first row's failure would silently honor only row one.
    ontologySuppressionSettings([
        ontologySuppressionRow(serviceLine: 'primary-care'),
        ontologySuppressionRow(region: 'northwest-arkansas'),
    ]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeTrue();
});

it('ignores a row the editor disabled', function (): void {
    // An editor pausing a rule uses the replicator set's disable toggle rather than
    // deleting the row and losing the configuration. `Variables::get()` returns raw stored
    // data, not the augmented value, so the `enabled` flag that
    // `Replicator::performAugmentation()` would have acted on is still here and has to be
    // honored by hand. Without this the toggle does nothing, clinical content stays hidden,
    // and there is no signal anywhere explaining why.
    ontologySuppressionSettings([
        ontologySuppressionRow(serviceLine: 'surgical-specialties', enabled: false),
    ]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
    ]))->toBeFalse();
});

it('still honors an enabled row that follows a disabled one', function (): void {
    // Bounds the case above: skipping a disabled row must not skip the rest of the list.
    ontologySuppressionSettings([
        ontologySuppressionRow(serviceLine: 'surgical-specialties', enabled: false),
        ontologySuppressionRow(region: 'northwest-arkansas'),
    ]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeTrue();
});

it('treats a row with no enabled flag as enabled', function (): void {
    // An ABSENT flag means enabled - the fieldtype's default, and what every row written
    // by the importer will look like. A truthiness test here would disable them all.
    ontologySuppressionSettings([
        ['type' => 'new_removal', 'remove_service_lines' => 'surgical-specialties'],
    ]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
    ]))->toBeTrue();
});

it('ignores a malformed row without letting it mask a later matching one', function (): void {
    // The global is editor-authored data; a non-array row must not fatal the profile
    // render, and must not swallow the rows after it.
    ontologySuppressionSettings([
        'not-a-row',
        ontologySuppressionRow(region: 'northwest-arkansas'),
    ]);

    expect(ontologySuppressionResolves([
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeTrue();
});

it('reads past an all-empty row to a later matching one', function (): void {
    // The all-empty guard skips its row; it must not end the scan.
    ontologySuppressionSettings([
        ontologySuppressionRow(),
        ontologySuppressionRow(serviceLine: 'surgical-specialties'),
    ]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
    ]))->toBeTrue();
});

it('reads past a row whose region does not match to a later matching one', function (): void {
    ontologySuppressionSettings([
        ontologySuppressionRow(region: 'central-arkansas'),
        ontologySuppressionRow(serviceLine: 'surgical-specialties'),
    ]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeTrue();
});

it('reads a non-string criterion as unset, widening its row to the other criterion', function (): void {
    // The sharp edge the resolver's singleValue() docblock records: an int criterion is
    // not unusable, it is unset, so the row matches on its region alone.
    ontologySuppressionSettings([ontologySuppressionRow(serviceLine: 5, region: 'northwest-arkansas')]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeTrue();
});

it('reads an empty-string criterion as unset rather than as a value', function (): void {
    // Read as a value, '' would match no provider and silently disable the region half.
    ontologySuppressionSettings([ontologySuppressionRow(serviceLine: '', region: 'northwest-arkansas')]);

    expect(ontologySuppressionResolves([
        'service_line' => 'surgical-specialties',
        'clinical_locations' => [ontologySuppressionLocation('northwest-clinic', 'northwest-arkansas')],
    ]))->toBeTrue();
});
