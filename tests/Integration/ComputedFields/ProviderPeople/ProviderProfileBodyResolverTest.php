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
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderProfileBodyResolver;

covers(ProviderProfileBodyResolver::class);

/**
 * Whether anything renders between the clinical provider lens's two appointment
 * CTAs (UAMS-Web/uams-statamic-archive#1695) - the fact that gates the foot-of-page repeat.
 *
 * Every case runs against real persisted entries and a real global, because the
 * relation checks ARE a Stache walk: the sections are gated on the entries a
 * relation RESOLVES, so an unpublished-only relation has to actually resolve to
 * nothing rather than be asserted against a mock.
 *
 * The site keeps the two drift-guard cases, which read the host's `_profile.antlers.html`
 * to count the sections it renders between the two CTAs; that template is a host file. The
 * provider is an unsaved entry, because saving one would resolve the host's `person` blueprint.
 *
 * @command  vendor/bin/pest tests/Integration/ComputedFields/ProviderPeople/ProviderProfileBodyResolverTest.php
 */

/**
 * Each `entries` relation section, mapped to the collection its blueprint field
 * actually points at (the host's `uamsweb_common_fad` fieldset).
 *
 * @var array<string, string>
 */
const PROFILE_BODY_RELATIONS = [
    'expertise' => 'clinical_expertise',
    'conditions' => 'clinical_conditions',
    'treatments' => 'clinical_services',
    'clinical_locations' => 'clinical_locations',
    'clinical_resources' => 'clinical_resources',
];

/**
 * The distinct collections the relation sections resolve against.
 *
 * @var list<string>
 */
const PROFILE_BODY_RELATION_COLLECTIONS = [
    'clinical_expertise',
    'clinical_conditions',
    'clinical_services',
    'clinical_locations',
    'clinical_resources',
];

beforeEach(function (): void {
    // Keep the Stache out of the shared fixtures directory, one directory per test
    $root = storage_path('framework/testing/stache/'.uniqid('profile-body-', true).'/dev-null');
    Stache::stores()->each(function (Store $store) use ($root): void {
        $store->directory(Path::tidy($root.'/'.$store->key()));
    });
    Stache::clear();

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    // Dated with `future: private`, mirroring `content/collections/clinical_locations.yaml`,
    // so a scheduled entry behaves here as it does in production. All five are created
    // because each relation handle points at a DIFFERENT collection in the blueprint
    // (`conditions` -> clinical_conditions, `treatments` -> clinical_services), and a
    // test that resolved three handles against one collection would only look like it
    // covered them.
    foreach (PROFILE_BODY_RELATION_COLLECTIONS as $handle) {
        $collection = CollectionFacade::make($handle);
        $collection->title($handle);
        $collection->dated(true);
        $collection->futureDateBehavior('private');
        $collection->save();
    }

    profileBodySettings();
});

/**
 * Persist the Find-a-Doc settings global with the given field values.
 *
 * @param  array<string, mixed>  $data
 */
function profileBodySettings(array $data = []): void
{
    $set = mustBe(GlobalSetInstance::class, GlobalSet::make('uams_fad_settings'), 'the settings global');
    $set->title('Find-a-Doc Settings');
    $set->save();

    $defaultSite = mustBe(SiteInstance::class, Site::default(), 'the default site');

    $vars = mustBe(Variables::class, $set->makeLocalization($defaultSite->handle()), 'the settings variables');
    $vars->data($data);
    $vars->save();
}

/**
 * Persist one related entry and return its id.
 *
 * @param  array<string, mixed>  $data
 */
function profileBodyRelated(string $collection, string $id, bool $published = true, ?string $date = null, array $data = []): string
{
    $entry = mustBe(EntryInstance::class, Entry::make(), 'an entry');
    $entry->collection($collection);
    $entry->id($id);
    $entry->slug($id);
    $entry->published($published);
    $entry->data($data + ['title' => ucfirst($id)]);
    $entry->date($date ?? '2020-01-01');
    $entry->saveQuietly();

    return $id;
}

/**
 * Whether the profile body renders for a provider with the given stored data.
 *
 * @param  array<string, mixed>  $data
 */
function resolvesProfileBody(array $data): bool
{
    $provider = new EntryInstance;
    $provider->collection('people');
    $provider->id('mccoy');
    $provider->slug('mccoy');
    $provider->data($data + ['title' => 'Leonard McCoy']);

    return app(ProviderProfileBodyResolver::class)->resolve($provider);
}

// --- The empty profile -------------------------------------------------------

it('reports nothing rendered for a provider carrying only appointment facts', function (): void {
    // The whole point of the ticket: an appointment-eligible provider with no body
    // content at all, who otherwise got the same CTA twice, back to back.
    expect(resolvesProfileBody([
        'appointments_query' => true,
        'appointments_individual_query' => true,
        'accepting_patients_query' => true,
    ]))->toBeFalse();
});

it('reports nothing rendered for a provider whose section fields are all empty', function (): void {
    // Empty is empty in every shape a cleared field can take: an empty Bard array,
    // an empty replicator, an empty relation, and a blank string.
    expect(resolvesProfileBody([
        'bio' => [],
        'bio_clinical' => [],
        'clinical_focus' => [],
        'certifications' => [],
        'member_of' => [],
        'awards' => [],
        'expertise' => [],
        'clinical_locations' => [],
        'clinical_resources' => [],
        'podcast_guest_name_uams_health_talk' => '   ',
    ]))->toBeFalse();
});

// --- The content sections ----------------------------------------------------

it('reports a rendered body for each Bard and replicator section in turn', function (string $handle): void {
    expect(resolvesProfileBody([$handle => [['type' => 'paragraph']]]))->toBeTrue();
})->with(['bio', 'bio_clinical', 'clinical_focus', 'certifications', 'member_of', 'awards']);

it('reports nothing rendered for a replicator holding only disabled sets', function (string $handle): void {
    // `Replicator::performAugmentation()` rejects every set flagged `enabled: false`,
    // so the field augments to `[]` and its section renders nothing. Counting the raw
    // stored array would report a section that is not on the page and put the
    // back-to-back CTA the ticket removes straight back.
    expect(resolvesProfileBody([
        $handle => [
            ['id' => 'one', 'type' => 'award', 'enabled' => false, 'title' => 'Hidden'],
        ],
    ]))->toBeFalse();
})->with(['awards', 'certifications']);

it('reports a rendered body when a replicator mixes a disabled and an enabled set', function (): void {
    expect(resolvesProfileBody([
        'awards' => [
            ['id' => 'one', 'type' => 'award', 'enabled' => false, 'title' => 'Hidden'],
            ['id' => 'two', 'type' => 'award', 'title' => 'Shown'],
        ],
    ]))->toBeTrue();
});

it('treats a set with no enabled flag as enabled, like the fieldtype does', function (): void {
    // Only an explicit `false` removes a set; an absent flag means enabled, which is
    // also what every Bard node looks like.
    expect(resolvesProfileBody(['awards' => [['id' => 'one', 'type' => 'award']]]))->toBeTrue()
        ->and(resolvesProfileBody(['bio' => [['type' => 'paragraph']]]))->toBeTrue();
});

// --- The relation sections ---------------------------------------------------

it('reports a rendered body for each relation section in turn', function (string $handle, string $collection): void {
    $id = profileBodyRelated($collection, $handle.'-one');

    expect(resolvesProfileBody([$handle => [$id]]))->toBeTrue();
})->with(array_map(
    static fn (string $handle, string $collection): array => [$handle, $collection],
    array_keys(PROFILE_BODY_RELATIONS),
    array_values(PROFILE_BODY_RELATIONS)
));

it('reports nothing rendered for a relation holding only an unpublished entry', function (): void {
    // The UAMS-Web/uams-statamic-archive#1693 trap. The section gate is `| count`, which counts the published entries
    // of the augmented StatusQueryBuilder, so an unpublished-only relation renders NO
    // section. Counting the stored ids here would report a section that never appears
    // and put the foot-of-page CTA back on a profile with nothing above it.
    $id = profileBodyRelated('clinical_resources', 'draft-resource', published: false);

    expect(resolvesProfileBody(['clinical_resources' => [$id]]))->toBeFalse();
});

it('reports nothing rendered for a relation holding only a future-dated entry', function (): void {
    // `status()` not `published()`: a flagged-published entry dated tomorrow is
    // `scheduled` on a `future: private` collection and the relation excludes it.
    $id = profileBodyRelated('clinical_locations', 'future-clinic', date: now()->addYear()->format('Y-m-d'));

    expect(resolvesProfileBody(['clinical_locations' => [$id]]))->toBeFalse();
});

it('reports a rendered body when a relation mixes a published and an unpublished entry', function (): void {
    $live = profileBodyRelated('clinical_resources', 'live-resource');
    $draft = profileBodyRelated('clinical_resources', 'draft-resource-two', published: false);

    expect(resolvesProfileBody(['clinical_resources' => [$draft, $live]]))->toBeTrue();
});

it('skips a malformed relation id and still finds a published entry after it', function (): void {
    $live = profileBodyRelated('clinical_locations', 'live-clinic');

    expect(resolvesProfileBody(['clinical_locations' => [1701, '', $live]]))->toBeTrue();
});

it('reports nothing rendered for a relation holding an id that no longer exists', function (): void {
    expect(resolvesProfileBody(['clinical_locations' => ['deleted-clinic']]))->toBeFalse();
});

// --- The podcast section -----------------------------------------------------

it('reports a rendered body for a provider carrying a podcast guest name', function (): void {
    expect(resolvesProfileBody(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']))->toBeTrue();
});

it('reports nothing rendered when the podcast toggle is explicitly off', function (): void {
    profileBodySettings(['health_talk_query' => false]);

    expect(resolvesProfileBody(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']))->toBeFalse();
});

it('reports a rendered body when the podcast toggle has never been saved', function (): void {
    // The field's own `default: true`, and the partial gates on `!== false` for the
    // same reason - an editor who has never opened the tab has not turned it off.
    profileBodySettings();

    expect(resolvesProfileBody(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']))->toBeTrue();
});

it('reports nothing rendered for the podcast toggle alone, with no guest name', function (): void {
    profileBodySettings(['health_talk_query' => true]);

    expect(resolvesProfileBody([]))->toBeFalse();
});

// --- Ontology suppression (UAMS-Web/uams-statamic-archive#1687) --------------------------------------------

it('does not count a suppressed Areas of Expertise as a rendered section', function (): void {
    // UAMS-Web/uams-statamic-archive#1687 hides the expertise section for a provider matching a removal rule. A
    // provider whose ONLY body content was that section then has nothing between the two
    // appointment CTAs, so counting it here would put UAMS-Web/uams-statamic-archive#1695's back-to-back CTA straight
    // back - and the template drift guard cannot see it, because the partial call is
    // still present inside its new `{{ if }}`.
    profileBodySettings([
        'remove_ontology_criteria' => [
            ['type' => 'new_removal', 'enabled' => true, 'remove_service_lines' => 'surgical-specialties'],
        ],
    ]);

    expect(resolvesProfileBody([
        'service_line' => 'surgical-specialties',
        'expertise' => [profileBodyRelated('clinical_expertise', 'cardiology')],
    ]))->toBeFalse();
});

it('still counts Areas of Expertise for a provider no removal rule matches', function (): void {
    // Bounds the case above: suppression is the exception, and an unmatched provider's
    // section must go on counting. Same fixture, different service line.
    profileBodySettings([
        'remove_ontology_criteria' => [
            ['type' => 'new_removal', 'enabled' => true, 'remove_service_lines' => 'surgical-specialties'],
        ],
    ]);

    expect(resolvesProfileBody([
        'service_line' => 'primary-care',
        'expertise' => [profileBodyRelated('clinical_expertise', 'cardiology')],
    ]))->toBeTrue();
});

it('still counts conditions and treatments for a suppressed provider', function (): void {
    // Only the routable Areas of Expertise is suppressed (UAMS-Web/uams-statamic-archive#1687). A suppressed provider
    // who also has conditions keeps a rendered body, so the foot CTA still repeats,
    // under-reporting here would DROP a real CTA, the regression the class docblock
    // names as the one it must never cause.
    profileBodySettings([
        'remove_ontology_criteria' => [
            ['type' => 'new_removal', 'enabled' => true, 'remove_service_lines' => 'surgical-specialties'],
        ],
    ]);

    expect(resolvesProfileBody([
        'service_line' => 'surgical-specialties',
        'expertise' => [profileBodyRelated('clinical_expertise', 'cardiology')],
        'conditions' => [profileBodyRelated('clinical_conditions', 'arrhythmia')],
    ]))->toBeTrue();
});

// --- Edge branches (mutation hardening) --------------------------------------

// `settingValue()`: the `?->inDefaultSite()` null-safe and the `instanceof`
// guard - an absent global must resolve to null, not fatal. The podcast gate
// then reads `null !== false` as true, so a provider with only a podcast guest
// name still renders (matching the field's `default: true`).
it('renders the podcast section when the global is absent (health_talk defaults on)', function (): void {
    $set = mustBe(GlobalSetInstance::class, GlobalSet::findByHandle('uams_fad_settings'), 'the settings global');
    $set->delete();

    expect(resolvesProfileBody(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']))->toBeTrue();
});

// `hasContent()` scalar fallback: a stored `false` is NOT content (the `!== false`
// arm), so a section field holding it renders nothing.
it('treats a scalar false section value as no content', function (): void {
    expect(resolvesProfileBody(['bio' => false]))->toBeFalse();
});

// `hasContent()` scalar fallback: a non-null, non-false scalar IS content (the
// `!== null` arm), so a section field holding it renders.
it('treats a non-null scalar section value as content', function (): void {
    expect(resolvesProfileBody(['bio' => 42]))->toBeTrue();
});

// `isEnabledItem()`: a list element that is not a set carries no `enabled` flag to
// read, so it counts as content rather than being dereferenced.
it('treats a non-array list element as content', function (): void {
    expect(resolvesProfileBody(['bio' => ['Captain Kirk']]))->toBeTrue();
});

// `hasPublishedRelation()`: a non-string relation id (malformed data) is skipped
// before `find()`, not passed to it (a TypeError) - the `! \is_string($id)`
// disjunct.
it('skips a non-string relation id rather than dereferencing it', function (): void {
    expect(resolvesProfileBody(['expertise' => [['nested']]]))->toBeFalse();
});

// `hasPublishedRelation()` `continue`: a skippable id followed by a valid
// published relation must still resolve - the loop continues past the bad id
// rather than breaking out of it.
it('continues past a skippable relation id to a later valid one', function (): void {
    $id = profileBodyRelated('clinical_expertise', 'cardiology');

    expect(resolvesProfileBody(['expertise' => ['', $id]]))->toBeTrue();
});

// --- Equivalent mutant (documented, not killable) ----------------------------
// `hasPublishedRelation()` `$id === ''` -> `$id === '...'`: an empty id that
// slips past the string check is caught downstream by `find('') === null`, so no
// relation resolves either way - the mutation cannot change the result.
