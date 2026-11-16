<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use UAMSWeb\Aether\People\Contracts\EntryReferenceRepointer;
use UAMSWeb\Aether\People\Merge\PeopleMergeException;
use UAMSWeb\Aether\People\Merge\PeopleMergeService;
use UAMSWeb\Aether\People\Support\ReferenceRepointSummary;
use UAMSWeb\Aether\People\Tests\Support\HostFakes;

/**
 * Service coverage for {@see PeopleMergeService} (UAMS-Web/uams-statamic-archive#838): reconciliation,
 * preview safety, the committed merge (survivor identity, per-field selection,
 * reference re-pointing, redirects, loser retirement), and the multisite rule.
 *
 * Fixtures persist a real `people` collection + person blueprint, a referrer
 * collection, and the redirects global into an isolated Stache; entries are
 * written with `saveQuietly()` so no entry-saved listener mutates them.
 */
const PM_ARTICLES = 'pm_articles';

/**
 * All configured site handles.
 *
 * @return list<string>
 */
function pmAllSiteHandles(): array
{
    /** @var Collection<int|string, Statamic\Sites\Site> $sites */
    $sites = Site::all();

    /** @var list<string> $handles */
    $handles = $sites->map(fn (Statamic\Sites\Site $site) => $site->handle())->values()->all();

    return $handles;
}

/**
 * The default site handle.
 */
function pmDefaultSiteHandle(): string
{
    /** @var Statamic\Sites\Site $site */
    $site = Site::default();

    /** @var string $handle */
    $handle = $site->handle();

    return $handle;
}

/**
 * The persisted data array for the entry with the given id.
 *
 * @return array<string, mixed>
 */
function pmData(string $id): array
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::find($id);

    /** @var Collection<string, mixed> $data */
    $data = $entry->data();

    return $data->all();
}

/**
 * Two distinct configured site handles (the app is multisite).
 *
 * @return array{0: string, 1: string}
 */
function pmTwoSites(): array
{
    $handles = pmAllSiteHandles();
    Assert::assertGreaterThanOrEqual(2, \count($handles));

    $default = pmDefaultSiteHandle();

    return [$default, $handles[0] === $default ? $handles[1] : $handles[0]];
}

/**
 * Persist a person entry with the given data, bypassing save listeners.
 *
 * @param  array<string, mixed>  $data
 */
function pmMakePerson(string $id, string $slug, array $data, ?string $site = null): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection(PeopleMergeService::peopleCollection());
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($slug);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);

    if ($site !== null) {
        $entry->locale($site);
    }

    $entry->saveQuietly();

    return $entry;
}

/**
 * Persist a bare article entry, bypassing save listeners.
 *
 * @param  array<string, mixed>  $data
 */
function pmMakeArticle(string $id, string $slug, array $data = []): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection(PM_ARTICLES);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($slug);

    if ($data !== []) {
        /** @var Statamic\Entries\Entry $entry */
        $entry = $entry->data($data);
    }

    $entry->saveQuietly();
}

beforeEach(function (): void {
    isolateStacheAndBlueprintsIn('merge-people-merge-service');

    // A collection serves more than its default site only when multisite is on
    config(['statamic.system.multisite' => true, 'statamic.editions.pro' => true]);
    Site::setSites([
        'default' => ['name' => 'Default', 'locale' => 'en_US', 'url' => '/'],
        'health' => ['name' => 'Health', 'locale' => 'en_US', 'url' => '/health/'],
    ]);

    [$default] = pmTwoSites();

    $peopleCollection = CollectionFacade::make(PeopleMergeService::peopleCollection());
    $peopleCollection->title('People');
    $peopleCollection->sites(pmAllSiteHandles());
    $peopleCollection->routes(array_fill_keys(pmAllSiteHandles(), '/people/{slug}'));
    $peopleCollection->save();

    $blueprint = Blueprint::makeFromFields([
        'bio' => ['type' => 'text', 'display' => 'Biography'],
        'email' => ['type' => 'text'],
        'nickname' => ['type' => 'text'],
        'computed_field' => ['type' => 'text', 'visibility' => 'computed'],
    ]);
    $blueprint->setHandle('person');
    $blueprint->setNamespace('collections.'.PeopleMergeService::peopleCollection());
    $blueprint->save();

    $articles = CollectionFacade::make(PM_ARTICLES);
    $articles->title('Articles');
    $articles->save();

    // The redirects global the merge appends to.
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->save();
    /** @var Variables $vars */
    $vars = $set->makeLocalization($default);
    $vars->data(['redirects' => []]);
    $vars->save();
});

function pmService(): PeopleMergeService
{
    return new PeopleMergeService(HostFakes::entryReferenceRepointer());
}

it('refuses to reconcile fewer than two people', function (): void {
    pmMakePerson('only-1', 'only', ['bio' => 'A']);

    pmService()->buildReconciliation(['only-1']);
})->throws(PeopleMergeException::class);

it('refuses a candidate that is not a people entry', function (): void {
    pmMakePerson('p-1', 'p1', ['bio' => 'A']);
    pmMakeArticle('a-1', 'a1');

    pmService()->buildReconciliation(['p-1', 'a-1']);
})->throws(PeopleMergeException::class);

it('builds a reconciliation flagging conflicts and excluding identity + computed fields', function (): void {
    pmMakePerson('surv', 'survivor', ['bio' => 'A bio', 'email' => 'same@x.test', 'computed_field' => 'x']);
    pmMakePerson('lose', 'loser', ['bio' => 'B bio', 'email' => 'same@x.test', 'nickname' => 'Nick']);

    $reconciliation = pmService()->buildReconciliation(['surv', 'lose']);

    expect($reconciliation->survivorId)->toBe('surv')
        ->and($reconciliation->candidates)->toHaveCount(2);

    $byHandle = collect($reconciliation->fields)->keyBy('handle');

    /** @var array{handle: string, display: string, type: string, conflict: bool, values: list<array{entry_id: string, value: mixed, display: string, has_value: bool}>, default_source: string} $bioField */
    $bioField = $byHandle['bio'];
    /** @var array{handle: string, display: string, type: string, conflict: bool, values: list<array{entry_id: string, value: mixed, display: string, has_value: bool}>, default_source: string} $emailField */
    $emailField = $byHandle['email'];
    /** @var array{handle: string, display: string, type: string, conflict: bool, values: list<array{entry_id: string, value: mixed, display: string, has_value: bool}>, default_source: string} $nicknameField */
    $nicknameField = $byHandle['nickname'];

    // bio differs -> conflict; email agrees -> no conflict; nickname only on loser.
    expect($byHandle->has('bio'))->toBeTrue()
        ->and($bioField['conflict'])->toBeTrue()
        ->and($bioField['display'])->toBe('Biography')
        ->and($bioField['default_source'])->toBe('surv')
        ->and($emailField['conflict'])->toBeFalse()
        ->and($nicknameField['conflict'])->toBeFalse()
        // Only the loser has a nickname, so it defaults to the loser (no value lost).
        ->and($nicknameField['default_source'])->toBe('lose');

    // Identity, derived, and computed fields are never offered for reconciliation.
    expect($byHandle->has('title'))->toBeFalse()
        ->and($byHandle->has('slug'))->toBeFalse()
        ->and($byHandle->has('computed_field'))->toBeFalse();
});

it('preview writes nothing and leaves the losers intact', function (): void {
    pmMakePerson('surv', 'survivor', ['bio' => 'A']);
    pmMakePerson('lose', 'loser', ['bio' => 'B']);
    pmMakeArticle('art', 'art', ['author' => 'lose']);

    $result = pmService()->preview(['surv', 'lose'], 'surv', ['bio' => 'lose']);

    expect($result->dryRun)->toBeTrue()
        ->and($result->references->occurrences)->toBe(1)
        ->and($result->redirectsCreated)->toBeGreaterThanOrEqual(1);

    // Nothing was written: loser still exists, survivor bio untouched, ref intact.
    expect(Entry::find('lose'))->not->toBeNull()
        ->and(pmData('surv')['bio'])->toBe('A')
        ->and(pmData('art')['author'])->toBe('lose');
});

it('throws when the survivor is not among the candidates', function (): void {
    pmMakePerson('surv', 'survivor', ['bio' => 'A']);
    pmMakePerson('lose', 'loser', ['bio' => 'B']);

    pmService()->merge(['surv', 'lose'], 'not-a-candidate', []);
})->throws(PeopleMergeException::class);

it('commits a merge: survivor identity preserved, selection applied, refs re-pointed, loser retired', function (): void {
    $survivor = pmMakePerson('surv', 'survivor', ['bio' => 'A bio', 'email' => 'a@x.test']);
    pmMakePerson('lose', 'loser', ['bio' => 'B bio', 'email' => 'a@x.test', 'nickname' => 'Nick']);
    pmMakeArticle('art', 'art', ['author' => 'lose']);

    $survivorIdBefore = $survivor->id();
    $survivorSlugBefore = $survivor->slug();
    $survivorUrlBefore = $survivor->url();

    // Take bio from the loser; nickname auto-fills from the loser (only it has one).
    $result = pmService()->merge(['surv', 'lose'], 'surv', ['bio' => 'lose']);

    expect($result->dryRun)->toBeFalse()
        ->and($result->retiredIds)->toBe(['lose']);

    /** @var Statamic\Entries\Entry $survivorAfter */
    $survivorAfter = Entry::find('surv');

    // Identity is preserved.
    expect($survivorAfter)->not->toBeNull()
        ->and($survivorAfter->id())->toBe($survivorIdBefore)
        ->and($survivorAfter->slug())->toBe($survivorSlugBefore)
        ->and($survivorAfter->url())->toBe($survivorUrlBefore);

    /** @var Collection<string, mixed> $survivorData */
    $survivorData = $survivorAfter->data();

    // Selected + auto-filled values landed on the survivor.
    expect($survivorData->all()['bio'])->toBe('B bio')
        ->and($survivorData->all()['nickname'])->toBe('Nick');

    // The inbound reference now points at the survivor.
    expect(pmData('art')['author'])->toBe('surv');

    // The loser is gone.
    expect(Entry::find('lose'))->toBeNull();
});

it('creates a redirect from the retired URL to the survivor URL', function (): void {
    pmMakePerson('surv', 'jane-smith', ['bio' => 'A']);
    pmMakePerson('lose', 'jane-smith-2', ['bio' => 'B']);

    $result = pmService()->merge(['surv', 'lose'], 'surv', []);

    expect($result->redirectsCreated)->toBeGreaterThanOrEqual(1);

    /** @var Statamic\Globals\GlobalSet $redirectsGlobal */
    $redirectsGlobal = GlobalSet::findByHandle('redirects');
    /** @var Variables $redirectsVars */
    $redirectsVars = $redirectsGlobal->in(pmDefaultSiteHandle());
    /** @var list<array<string, mixed>> $redirects */
    $redirects = $redirectsVars->get('redirects');

    $sources = collect($redirects)->pluck('source')->all();
    $targets = collect($redirects)->pluck('target_url')->all();

    expect($sources)->toContain('/people/jane-smith-2')
        ->and($targets)->toContain('/people/jane-smith');
});

it('honors the multisite rule: a redirect is created for each site the loser was reachable in', function (): void {
    [$default, $other] = pmTwoSites();

    pmMakePerson('surv', 'jane', ['bio' => 'A'], $default);

    // The loser exists in the default site and is also localized into another site.
    $loser = pmMakePerson('lose', 'jane-2', ['bio' => 'B'], $default);
    /** @var Statamic\Entries\Entry $localization */
    $localization = $loser->makeLocalization($other);
    $localization->slug('jane-2-other');
    $localization->saveQuietly();

    $result = pmService()->merge(['surv', 'lose'], 'surv', []);

    $sites = collect($result->redirects)->pluck('site')->unique()->values()->all();

    // A redirect exists for both the default-site URL and the other-site URL.
    expect($sites)->toContain($default)
        ->and($sites)->toContain($other);

    // The loser (and its localization) is fully retired.
    expect(Entry::find('lose'))->toBeNull();
});

it('exposes each candidate row identity for the reconciliation header', function (): void {
    pmMakePerson('surv', 'jane-survivor', ['title' => 'Jane Survivor', 'bio' => 'A']);
    pmMakePerson('lose', 'jane-loser', ['title' => 'Jane Loser', 'bio' => 'B']);

    $reconciliation = pmService()->buildReconciliation(['surv', 'lose'], 'surv');

    /** @var array{id: string, title: string, slug: string|null, site: string, url: string|null, edit_url: string|null, is_survivor: bool} $survRow */
    $survRow = collect($reconciliation->candidates)->firstWhere('id', 'surv');
    /** @var array{id: string, title: string, slug: string|null, site: string, url: string|null, edit_url: string|null, is_survivor: bool} $loseRow */
    $loseRow = collect($reconciliation->candidates)->firstWhere('id', 'lose');

    expect($survRow['title'])->toBe('Jane Survivor')
        ->and($survRow['slug'])->toBe('jane-survivor')
        ->and($survRow['site'])->toBe(pmDefaultSiteHandle())
        ->and($survRow['url'])->toBe('/people/jane-survivor')
        ->and($survRow['is_survivor'])->toBeTrue()
        ->and($loseRow['is_survivor'])->toBeFalse();
});

it('renders per-candidate field values with type-aware display strings', function (): void {
    pmMakePerson('surv', 'survivor', ['bio' => 'A bio', 'active' => true, 'tags' => ['x', 'y']]);
    pmMakePerson('lose', 'loser', ['bio' => 'B bio', 'active' => false]);

    $reconciliation = pmService()->buildReconciliation(['surv', 'lose'], 'surv');
    $byHandle = collect($reconciliation->fields)->keyBy('handle');

    /** @var array{handle: string, display: string, type: string, conflict: bool, values: list<array{entry_id: string, value: mixed, display: string, has_value: bool}>, default_source: string} $bioField */
    $bioField = $byHandle['bio'];
    /** @var array{handle: string, display: string, type: string, conflict: bool, values: list<array{entry_id: string, value: mixed, display: string, has_value: bool}>, default_source: string} $activeField */
    $activeField = $byHandle['active'];
    /** @var array{handle: string, display: string, type: string, conflict: bool, values: list<array{entry_id: string, value: mixed, display: string, has_value: bool}>, default_source: string} $tagsField */
    $tagsField = $byHandle['tags'];

    // Scalar display is the value; booleans render true/false; arrays render JSON.
    /** @var array{entry_id: string, value: mixed, display: string, has_value: bool} $bioSurv */
    $bioSurv = collect($bioField['values'])->firstWhere('entry_id', 'surv');
    /** @var array{entry_id: string, value: mixed, display: string, has_value: bool} $activeSurv */
    $activeSurv = collect($activeField['values'])->firstWhere('entry_id', 'surv');
    /** @var array{entry_id: string, value: mixed, display: string, has_value: bool} $activeLose */
    $activeLose = collect($activeField['values'])->firstWhere('entry_id', 'lose');
    /** @var array{entry_id: string, value: mixed, display: string, has_value: bool} $tagsSurv */
    $tagsSurv = collect($tagsField['values'])->firstWhere('entry_id', 'surv');
    /** @var array{entry_id: string, value: mixed, display: string, has_value: bool} $tagsLose */
    $tagsLose = collect($tagsField['values'])->firstWhere('entry_id', 'lose');

    expect($bioSurv['display'])->toBe('A bio')
        ->and($bioSurv['has_value'])->toBeTrue()
        ->and($activeSurv['display'])->toBe('true')
        ->and($activeLose['display'])->toBe('false')
        ->and($tagsSurv['display'])->toContain('x')
        // The loser has no tags: the cell is marked empty, not rendered.
        ->and($tagsLose['has_value'])->toBeFalse()
        ->and($tagsLose['display'])->toBe('');
});

it('writes redirect rows as enabled 301 url redirects', function (): void {
    pmMakePerson('surv', 'jane-smith', ['bio' => 'A']);
    pmMakePerson('lose', 'jane-smith-2', ['bio' => 'B']);

    pmService()->merge(['surv', 'lose'], 'surv', []);

    /** @var Statamic\Globals\GlobalSet $redirectsGlobal */
    $redirectsGlobal = GlobalSet::findByHandle('redirects');
    /** @var Variables $redirectsVars */
    $redirectsVars = $redirectsGlobal->in(pmDefaultSiteHandle());
    /** @var list<array<string, mixed>> $redirects */
    $redirects = $redirectsVars->get('redirects');
    /** @var array<string, mixed> $row */
    $row = collect($redirects)->firstWhere('source', '/people/jane-smith-2');

    expect($row)->not->toBeNull()
        ->and($row['target_type'])->toBe('url')
        ->and($row['target_url'])->toBe('/people/jane-smith')
        ->and($row['response'])->toBe('301')
        ->and($row['enabled'])->toBeTrue();
});

it('maps the per-site footprint with slug, url, and survivor flag', function (): void {
    pmMakePerson('surv', 'jane-survivor', ['bio' => 'A']);
    pmMakePerson('lose', 'jane-loser', ['bio' => 'B']);

    $reconciliation = pmService()->buildReconciliation(['surv', 'lose'], 'surv');
    $default = pmDefaultSiteHandle();

    expect($reconciliation->sites)->toHaveKey($default);

    /** @var array{entry_id: string, slug: string|null, url: string|null, is_survivor: bool} $survRow */
    $survRow = collect($reconciliation->sites[$default])->firstWhere('entry_id', 'surv');

    expect($survRow['slug'])->toBe('jane-survivor')
        ->and($survRow['url'])->toBe('/people/jane-survivor')
        ->and($survRow['is_survivor'])->toBeTrue();
});

it('clears a survivor-only field when the operator explicitly selects an empty source', function (): void {
    pmMakePerson('surv', 'survivor', ['bio' => 'Important bio']);
    pmMakePerson('lose', 'loser', []);

    // Explicitly choosing the loser (which has no bio) clears the field — the
    // documented "empty selected value clears the field" contract.
    $result = pmService()->merge(['surv', 'lose'], 'surv', ['bio' => 'lose']);

    expect(pmData('surv'))->not->toHaveKey('bio')
        ->and($result->appliedFields)->toContain('bio');
});

it('does not append a duplicate redirect for a source that already redirects', function (): void {
    pmMakePerson('surv', 'jane', ['bio' => 'A']);
    pmMakePerson('lose', 'jane-dup', ['bio' => 'B']);

    pmService()->merge(['surv', 'lose'], 'surv', []);

    // A new duplicate reuses the freed slug, then merges into the same survivor.
    pmMakePerson('lose2', 'jane-dup', ['bio' => 'C']);
    $second = pmService()->merge(['surv', 'lose2'], 'surv', []);

    /** @var Statamic\Globals\GlobalSet $redirectsGlobal */
    $redirectsGlobal = GlobalSet::findByHandle('redirects');
    /** @var Variables $redirectsVars */
    $redirectsVars = $redirectsGlobal->in(pmDefaultSiteHandle());
    /** @var list<array<string, mixed>> $redirects */
    $redirects = $redirectsVars->get('redirects');
    $matching = collect($redirects)->where('source', '/people/jane-dup');

    // Only one redirect row exists for that source, and the second merge reported
    // no new redirect created.
    expect($matching)->toHaveCount(1)
        ->and($second->redirectsCreated)->toBe(0);
});

it('reports the applied fields taken from a non-survivor source', function (): void {
    pmMakePerson('surv', 'survivor', ['bio' => 'A', 'email' => 'keep@x.test']);
    pmMakePerson('lose', 'loser', ['bio' => 'B', 'nickname' => 'Nick']);

    // bio explicitly taken from loser; nickname auto-filled from loser; email stays survivor.
    $result = pmService()->preview(['surv', 'lose'], 'surv', ['bio' => 'lose']);

    expect($result->appliedFields)->toContain('bio')
        ->and($result->appliedFields)->toContain('nickname')
        ->and($result->appliedFields)->not->toContain('email');
});

it('carries every field of the repointer summary into the preview and the result', function (): void {
    pmMakePerson('surv', 'survivor', ['bio' => 'A']);
    pmMakePerson('lose', 'loser', ['bio' => 'B']);

    // A repointer that reports a distinct count for each field, so a swapped or dropped one shows
    $repointer = new class implements EntryReferenceRepointer
    {
        public function repoint(array $idMap): ReferenceRepointSummary
        {
            return new ReferenceRepointSummary(1, 2, 3, 9);
        }

        public function preview(array $idMap): ReferenceRepointSummary
        {
            return new ReferenceRepointSummary(4, 5, 6, 10);
        }
    };
    $service = new PeopleMergeService($repointer);

    expect($service->preview(['surv', 'lose'], 'surv', [])->toArray()['references'])
        ->toBe(['entries' => 4, 'globals' => 5, 'terms' => 6, 'occurrences' => 10, 'items_changed' => 15]);

    expect($service->merge(['surv', 'lose'], 'surv', [])->toArray()['references'])
        ->toBe(['entries' => 1, 'globals' => 2, 'terms' => 3, 'occurrences' => 9, 'items_changed' => 6]);
});
