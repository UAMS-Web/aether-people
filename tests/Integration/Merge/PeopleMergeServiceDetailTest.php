<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Events\EntryDeleted;
use Statamic\Events\GlobalVariablesSaved;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use UAMSWeb\Aether\People\Merge\MergeReconciliation;
use UAMSWeb\Aether\People\Merge\PeopleMergeService;
use UAMSWeb\Aether\People\Merge\RedirectsGlobalWriter;
use UAMSWeb\Aether\People\Tests\Support\HostFakes;

uses(MockeryPHPUnitIntegration::class);

covers(PeopleMergeService::class);

/**
 * The finer behavior of {@see PeopleMergeService}: each default, fallback, ordering and guard that the
 * flow tests in PeopleMergeServiceTest do not pin on their own. Private helpers are reached by
 * reflection where no public input can reach their branch (a stub entry with no blueprint, a loser
 * that vanished between planning and retiring).
 *
 * @command vendor/bin/pest tests/Integration/Merge/PeopleMergeServiceDetailTest.php
 */

/**
 * Persist a collection of people routed on the given sites, a person blueprint and the redirects global.
 *
 * @param  array<string, string>  $routes  Site handle => route; a site left out has no URL.
 */
function pmdBoot(array $routes = ['default' => '/people/{slug}', 'health' => '/people/{slug}', 'extra' => '/people/{slug}']): void
{
    isolateStacheAndBlueprintsIn('merge-people-merge-service-detail');

    config(['statamic.system.multisite' => true, 'statamic.editions.pro' => true]);
    Site::setSites([
        'default' => ['name' => 'Default', 'locale' => 'en_US', 'url' => '/'],
        'health' => ['name' => 'Health', 'locale' => 'en_US', 'url' => '/health/'],
        'extra' => ['name' => 'Extra', 'locale' => 'en_US', 'url' => '/extra/'],
    ]);

    $people = CollectionFacade::make(PeopleMergeService::peopleCollection());
    $people->title('People');
    $people->sites(['default', 'health', 'extra']);
    $people->routes($routes);
    $people->save();

    $blueprint = Blueprint::makeFromFields([
        'bio' => ['type' => 'text', 'display' => 'Biography'],
        'email' => ['type' => 'text'],
        'nickname' => ['type' => 'text'],
        'computed_field' => ['type' => 'text', 'visibility' => 'computed'],
    ]);
    $blueprint->setHandle('person');
    $blueprint->setNamespace('collections.'.PeopleMergeService::peopleCollection());
    $blueprint->save();

    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->sites(['default' => null, 'health' => null, 'extra' => null]);
    $set->save();

    /** @var Variables $vars */
    $vars = $set->makeLocalization('default');
    $vars->data(['redirects' => []]);
    $vars->save();
}

/**
 * Persist a person entry, optionally rooted in a site, bypassing save listeners.
 *
 * @param  array<string, mixed>  $data
 */
function pmdPerson(string $id, string $slug, array $data = [], string $site = 'default'): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(PeopleMergeService::peopleCollection());
    $entry->id($id);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);
    $entry->locale($site);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Add a localization of an entry in another site.
 */
function pmdLocalize(Statamic\Entries\Entry $origin, string $site, string $slug): void
{
    /** @var Statamic\Entries\Entry $localization */
    $localization = $origin->makeLocalization($site);
    $localization->slug($slug);
    $localization->published(true);
    $localization->saveQuietly();
}

/**
 * The reconciliation's field rows keyed by handle.
 *
 * @return array<string, array{handle: string, display: string, type: string, conflict: bool, values: list<array{entry_id: string, value: mixed, display: string, has_value: bool}>, default_source: string}>
 */
function pmdFields(MergeReconciliation $reconciliation): array
{
    $fields = [];

    foreach ($reconciliation->fields as $field) {
        $fields[$field['handle']] = $field;
    }

    return $fields;
}

/**
 * The reconciliation's candidate rows keyed by entry id.
 *
 * @return array<string, array{id: string, title: string, slug: ?string, site: string, url: ?string, edit_url: ?string, is_survivor: bool}>
 */
function pmdCandidates(MergeReconciliation $reconciliation): array
{
    $rows = [];

    foreach ($reconciliation->candidates as $row) {
        $rows[$row['id']] = $row;
    }

    return $rows;
}

function pmdService(): PeopleMergeService
{
    return new PeopleMergeService(HostFakes::entryReferenceRepointer(), new RedirectsGlobalWriter);
}

/**
 * Call a private method of the service.
 */
function pmdCall(string $method, mixed ...$arguments): mixed
{
    return new ReflectionMethod(PeopleMergeService::class, $method)->invoke(pmdService(), ...$arguments);
}

/**
 * The redirect rows now stored on one site's redirects localization.
 *
 * @return list<array<string, mixed>>
 */
function pmdRedirectsOn(string $site): array
{
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');
    /** @var Variables|null $variables */
    $variables = $set->in($site);

    /** @var list<array<string, mixed>> $rows */
    $rows = $variables?->get('redirects', []) ?? [];

    return $rows;
}

beforeEach(function (): void {
    pmdBoot();
});

it('offers no identity or system handle for reconciliation, however it got into the data', function (): void {
    $system = ['id' => 'stored-id', 'slug' => 'stored-slug', 'title' => 'T', 'blueprint' => 'person', 'date' => '2026-01-01', 'published' => true, 'updated_at' => 1, 'updated_by' => 'u'];

    pmdPerson('surv', 'survivor', ['bio' => 'A', ...$system]);
    pmdPerson('lose', 'loser', ['bio' => 'B', ...$system]);

    $handles = array_column(pmdService()->buildReconciliation(['surv', 'lose'])->fields, 'handle');

    expect($handles)->toBe(['bio', 'email', 'nickname']);
});

it('lists a handle once even when the blueprint and several entries all carry it', function (): void {
    pmdPerson('surv', 'survivor', ['bio' => 'A', 'extra' => 'x']);
    pmdPerson('lose', 'loser', ['bio' => 'B', 'extra' => 'y', 'other' => 'z']);

    $handles = array_column(pmdService()->buildReconciliation(['surv', 'lose'])->fields, 'handle');

    expect($handles)->toBe(['bio', 'email', 'nickname', 'extra', 'other']);
});

it('labels a field from the blueprint, and from its handle when the blueprint has none', function (): void {
    pmdPerson('surv', 'survivor', ['bio' => 'A', 'first_name' => 'Jane', 'last-name' => 'Doe']);
    pmdPerson('lose', 'loser', ['bio' => 'B']);

    $fields = pmdFields(pmdService()->buildReconciliation(['surv', 'lose']));

    expect($fields['bio']['display'])->toBe('Biography')
        ->and($fields['bio']['type'])->toBe('text')
        ->and($fields['email']['display'])->toBe('Email')
        ->and($fields['first_name']['display'])->toBe('First Name')
        ->and($fields['first_name']['type'])->toBe('unknown')
        ->and($fields['last-name']['display'])->toBe('Last Name');
});

it('keeps each candidate value beside its display string and presence flag', function (): void {
    pmdPerson('surv', 'survivor', ['bio' => 'A', 'email' => '']);
    pmdPerson('lose', 'loser', ['bio' => ['x' => 1]]);

    $fields = pmdFields(pmdService()->buildReconciliation(['surv', 'lose']));

    expect($fields['bio']['values'])->toBe([
        ['entry_id' => 'surv', 'value' => 'A', 'display' => 'A', 'has_value' => true],
        ['entry_id' => 'lose', 'value' => ['x' => 1], 'display' => '{"x":1}', 'has_value' => true],
    ])
        ->and($fields['email']['values'][0])->toBe(['entry_id' => 'surv', 'value' => '', 'display' => '', 'has_value' => false])
        ->and($fields['email']['conflict'])->toBeFalse();
});

it('carries each candidate edit url, title and id into the header rows', function (): void {
    pmdPerson('surv', 'survivor', ['title' => 'Jane Doe']);
    pmdPerson('lose', 'loser', ['title' => '']);
    pmdPerson('bare', 'bare');

    $rows = pmdCandidates(pmdService()->buildReconciliation(['surv', 'lose', 'bare']));

    expect($rows['surv']['title'])->toBe('Jane Doe')
        ->and($rows['lose']['title'])->toBe('lose')
        ->and($rows['bare']['title'])->toBe('bare')
        ->and($rows['surv']['edit_url'])->toBe('http://localhost/cp/collections/people/entries/surv')
        ->and($rows['surv']['site'])->toBe('default');
});

it('takes the first candidate that holds a value when the survivor has none', function (): void {
    pmdPerson('first', 'first', ['bio' => 'one', 'nickname' => 'N1']);
    pmdPerson('second', 'second', ['bio' => 'two', 'nickname' => 'N2']);
    pmdPerson('surv', 'survivor', ['bio' => 'A']);

    $fields = pmdFields(pmdService()->buildReconciliation(['first', 'second', 'surv'], 'surv'));

    // Survivor has a value: it wins even though two earlier candidates hold one.
    expect($fields['bio']['default_source'])->toBe('surv')
        // Survivor has none: the first candidate holding one wins, not the last.
        ->and($fields['nickname']['default_source'])->toBe('first');
});

it('looks past a candidate without a value to a later one that has it', function (): void {
    pmdPerson('empty', 'empty');
    pmdPerson('holder', 'holder', ['nickname' => 'Nick']);
    pmdPerson('surv', 'survivor');

    $fields = pmdFields(pmdService()->buildReconciliation(['empty', 'holder', 'surv'], 'surv'));

    expect($fields['nickname']['default_source'])->toBe('holder')
        ->and($fields['bio']['default_source'])->toBe('surv');
});

it('skips ids that no longer resolve and keeps reading the rest', function (): void {
    pmdPerson('surv', 'survivor');
    pmdPerson('lose', 'loser');

    $reconciliation = pmdService()->buildReconciliation(['ghost', '', 'surv', 'surv', 'lose']);

    expect(array_column($reconciliation->candidates, 'id'))->toBe(['surv', 'lose']);
});

it('resolves the requested survivor when it is a candidate and the first otherwise', function (): void {
    pmdPerson('surv', 'survivor');
    pmdPerson('lose', 'loser');

    expect(pmdService()->buildReconciliation(['surv', 'lose'], 'lose')->survivorId)->toBe('lose')
        ->and(pmdService()->buildReconciliation(['surv', 'lose'], 'nope')->survivorId)->toBe('surv')
        ->and(pmdService()->buildReconciliation(['surv', 'lose'])->survivorId)->toBe('surv');
});

it('stores the survivor on disk with the merged values', function (): void {
    $survivor = pmdPerson('surv', 'survivor', ['bio' => 'A']);
    pmdPerson('lose', 'loser', ['bio' => 'B']);

    pmdService()->merge(['surv', 'lose'], 'surv', ['bio' => 'lose']);

    /** @var string $path */
    $path = $survivor->path();

    expect(file_get_contents($path))->toContain('bio: B');
});

it('names the merged entry in the refusal, with its id only when the id adds something', function (): void {
    $titled = pmdPerson('titled-id', 'titled', ['title' => 'Jane Doe']);
    $plain = pmdPerson('same', 'same', ['title' => 'same']);

    expect(pmdCall('entryLabel', $titled))->toBe('Jane Doe [titled-id]')
        ->and(pmdCall('entryLabel', $plain))->toBe('[same]');
});

it('renders a value for the comparison table by its kind', function (): void {
    $long = str_repeat('a', 200);
    $longJson = json_encode(['k' => str_repeat('b', 200)]);

    expect(pmdCall('displayValue', null))->toBe('')
        ->and(pmdCall('displayValue', ''))->toBe('')
        ->and(pmdCall('displayValue', []))->toBe('')
        ->and(pmdCall('displayValue', true))->toBe('true')
        ->and(pmdCall('displayValue', false))->toBe('false')
        ->and(pmdCall('displayValue', 42))->toBe('42')
        ->and(pmdCall('displayValue', 'short'))->toBe('short')
        ->and(pmdCall('displayValue', $long))->toBe(str_repeat('a', 120).'...')
        ->and(pmdCall('displayValue', ['k' => str_repeat('b', 200)]))->toBe(substr((string) $longJson, 0, 120).'...')
        ->and(pmdCall('displayValue', ['a' => 1]))->toBe('{"a":1}')
        ->and(pmdCall('displayValue', "\xB1\x31"))->toBe("\xB1\x31");
});

it('renders a value that cannot be encoded as empty rather than failing', function (): void {
    expect(pmdCall('displayValue', ['bad' => "\xB1\x31"]))->toBe('')
        ->and(pmdCall('normalize', ['bad' => "\xB1\x31"]))->toBe('')
        ->and(pmdCall('normalize', ['a' => 1]))->toBe('{"a":1}');
});

it('counts a value as present unless it is null, an empty string or an empty array', function (): void {
    expect(pmdCall('hasValue', null))->toBeFalse()
        ->and(pmdCall('hasValue', ''))->toBeFalse()
        ->and(pmdCall('hasValue', []))->toBeFalse()
        ->and(pmdCall('hasValue', '0'))->toBeTrue()
        ->and(pmdCall('hasValue', 0))->toBeTrue()
        ->and(pmdCall('hasValue', false))->toBeTrue()
        ->and(pmdCall('hasValue', ['a']))->toBeTrue();
});

it('narrows a value to a string or null', function (): void {
    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'as-text';
        }
    };

    expect(pmdCall('stringOrNull', null))->toBeNull()
        ->and(pmdCall('stringOrNull', 'text'))->toBe('text')
        ->and(pmdCall('stringOrNull', $stringable))->toBe('as-text')
        ->and(pmdCall('stringOrNull', new stdClass))->toBeNull()
        ->and(pmdCall('stringOrNull', 5))->toBeNull()
        ->and(pmdCall('stringOrNull', ['a']))->toBeNull();
});

it('falls back to the id, or nothing, for an entry without a usable title', function (): void {
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();

    expect(pmdCall('entryTitle', $entry))->toBe('');

    $entry->id('known');
    $entry->data(['title' => '']);

    expect(pmdCall('entryTitle', $entry))->toBe('known');
});

it('reads no site for an entry whose locale is not a string', function (): void {
    /** @var MockInterface&Statamic\Entries\Entry $entry */
    $entry = Mockery::mock(Statamic\Entries\Entry::class);
    $entry->shouldReceive('locale')->andReturn(null);

    expect(pmdCall('localeString', $entry))->toBe('');
});

it('reads no handles from an entry whose blueprint cannot be resolved', function (): void {
    /** @var MockInterface&Statamic\Entries\Entry $entry */
    $entry = Mockery::mock(Statamic\Entries\Entry::class);
    $entry->shouldReceive('blueprint')->andReturn(null);

    expect(pmdCall('computedHandles', $entry))->toBe([])
        ->and(pmdCall('blueprintFieldMeta', $entry))->toBe([]);
});

it('names the computed handles of the survivor blueprint', function (): void {
    $survivor = pmdPerson('surv', 'survivor');

    expect(pmdCall('computedHandles', $survivor))->toBe(['computed_field'])
        ->and(array_keys((array) pmdCall('blueprintFieldMeta', $survivor)))->toBe(['title', 'bio', 'email', 'nickname', 'slug']);
});

it('plans a redirect from each retired localization to the survivor in the same site', function (): void {
    $survivor = pmdPerson('surv', 'jane');
    pmdLocalize($survivor, 'health', 'jane-health');

    $loser = pmdPerson('lose', 'old-jane');
    pmdLocalize($loser, 'health', 'old-jane-health');

    $result = pmdService()->preview(['surv', 'lose'], 'surv', []);

    expect($result->redirects)->toBe([
        ['site' => 'default', 'source' => '/people/old-jane', 'target' => '/people/jane', 'response' => '301'],
        ['site' => 'health', 'source' => '/health/people/old-jane-health', 'target' => '/health/people/jane-health', 'response' => '301'],
    ])
        ->and($result->redirectsCreated)->toBe(2);
});

it('points a site the survivor is absent from at the survivor canonical url', function (): void {
    pmdPerson('surv', 'jane');
    $loser = pmdPerson('lose', 'old-jane');
    pmdLocalize($loser, 'health', 'old-jane-health');

    $result = pmdService()->preview(['surv', 'lose'], 'surv', []);

    expect(array_column($result->redirects, 'target'))->toBe(['/people/jane', '/people/jane']);
});

it('keeps planning after a retired id that no longer resolves', function (): void {
    $survivor = pmdPerson('surv', 'jane');
    pmdPerson('lose', 'old-jane');

    $plan = pmdCall('planRedirects', $survivor, ['ghost', 'lose']);

    expect(array_column((array) $plan, 'source'))->toBe(['/people/old-jane']);
});

it('skips a retired localization that has no url and still plans the ones after it', function (): void {
    // The health site has no route, so a person rooted there has no URL; its default localization does.
    pmdBoot(['default' => '/people/{slug}', 'extra' => '/people/{slug}']);

    $survivor = pmdPerson('surv', 'jane');
    $loser = pmdPerson('lose', 'old-jane', [], 'health');
    pmdLocalize($loser, 'default', 'old-jane');

    $plan = pmdCall('planRedirects', $survivor, ['lose']);

    expect(array_column((array) $plan, 'site'))->toBe(['default']);
});

it('skips a redirect whose source and target are the same path and plans the rest', function (): void {
    $survivor = pmdPerson('surv', 'jane');
    $loser = pmdPerson('lose', 'jane');
    pmdLocalize($loser, 'health', 'old-jane-health');

    $plan = pmdCall('planRedirects', $survivor, ['lose']);

    expect(array_column((array) $plan, 'source'))->toBe(['/health/people/old-jane-health']);
});

it('skips a retired localization when the survivor has no url to point at and plans the rest', function (): void {
    // The survivor is rooted in health, which has no route, and localized in default, which does.
    pmdBoot(['default' => '/people/{slug}', 'extra' => '/people/{slug}']);

    $survivor = pmdPerson('surv', 'jane', [], 'health');
    pmdLocalize($survivor, 'default', 'jane');

    // The loser is rooted in extra (the survivor is not there and has no canonical url) then default.
    $loser = pmdPerson('lose', 'old-jane-extra', [], 'extra');
    pmdLocalize($loser, 'default', 'old-jane');

    $plan = pmdCall('planRedirects', $survivor, ['lose']);

    expect(array_column((array) $plan, 'site'))->toBe(['default']);
});

it('writes each redirect row with an id, a type, the 301 response, its order and enabled', function (): void {
    pmdPerson('surv', 'jane');
    pmdPerson('lose', 'old-jane');

    pmdService()->merge(['surv', 'lose'], 'surv', []);

    $rows = pmdRedirectsOn('default');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBeString()->toMatch('/^[0-9a-f-]{36}$/')
        ->and($rows[0]['type'])->toBe('redirect')
        ->and($rows[0]['target_type'])->toBe('url')
        ->and($rows[0]['source'])->toBe('/people/old-jane')
        ->and($rows[0]['target_url'])->toBe('/people/jane')
        ->and($rows[0]['response'])->toBe('301')
        ->and($rows[0]['order'])->toBe(30)
        ->and($rows[0]['enabled'])->toBeTrue();
});

it('creates the redirects localization of a site that has none yet and saves it', function (): void {
    $survivor = pmdPerson('surv', 'jane');
    pmdLocalize($survivor, 'health', 'jane-health');
    pmdPerson('lose', 'old-jane', [], 'health');

    $result = pmdService()->merge(['surv', 'lose'], 'surv', []);

    expect($result->redirectsCreated)->toBe(1)
        ->and(pmdRedirectsOn('health'))->toHaveCount(1)
        ->and(pmdRedirectsOn('health')[0]['source'])->toBe('/health/people/old-jane')
        ->and(pmdRedirectsOn('default'))->toBe([]);

    // The localization is on disk, not only on the instance the merge wrote to
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');
    /** @var Variables $variables */
    $variables = $set->in('health');

    /** @var string $variablesPath */
    $variablesPath = $variables->path();

    expect(file_get_contents($variablesPath))->toContain('/health/people/old-jane');
});

it('points a redirect at the survivor localization in the same site, not its canonical url', function (): void {
    $survivor = pmdPerson('surv', 'jane');
    pmdLocalize($survivor, 'health', 'jane-health');
    $loser = pmdPerson('lose', 'old-jane');
    pmdLocalize($loser, 'health', 'old-jane-health');

    pmdService()->merge(['surv', 'lose'], 'surv', []);

    expect(pmdRedirectsOn('health')[0]['target_url'])->toBe('/health/people/jane-health')
        ->and(pmdRedirectsOn('default')[0]['target_url'])->toBe('/people/jane');
});

it('ignores malformed rows already in the redirects when it checks for an existing source', function (): void {
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');
    /** @var Variables $variables */
    $variables = $set->in('default');
    $variables->set('redirects', ['junk', ['type' => 'redirect'], ['source' => 5], ['source' => '/elsewhere']]);
    $variables->save();

    pmdPerson('surv', 'jane');
    pmdPerson('lose', 'old-jane');

    $result = pmdService()->merge(['surv', 'lose'], 'surv', []);

    expect($result->redirectsCreated)->toBe(1)
        ->and(array_column(pmdRedirectsOn('default'), 'source'))->toContain('/people/old-jane', '/elsewhere');
});

it('appends the redirects that are new and skips the ones that already exist', function (): void {
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');
    /** @var Variables $variables */
    $variables = $set->in('default');
    $variables->set('redirects', [['source' => '/people/old-a', 'target_type' => 'url', 'target_url' => '/x']]);
    $variables->save();

    pmdPerson('surv', 'jane');
    pmdPerson('a', 'old-a');
    pmdPerson('b', 'old-b');

    $result = pmdService()->merge(['surv', 'a', 'b'], 'surv', []);

    expect($result->redirectsCreated)->toBe(1)
        ->and(array_column(pmdRedirectsOn('default'), 'source'))->toBe(['/people/old-a', '/people/old-b']);
});

it('does not save the redirects when every planned source already redirects', function (): void {
    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');
    /** @var Variables $variables */
    $variables = $set->in('default');
    $variables->set('redirects', [['source' => '/people/old-a', 'target_type' => 'url', 'target_url' => '/x']]);
    $variables->save();

    pmdPerson('surv', 'jane');
    pmdPerson('a', 'old-a');

    Event::fake([GlobalVariablesSaved::class]);

    $result = pmdService()->merge(['surv', 'a'], 'surv', []);

    expect($result->redirectsCreated)->toBe(0);
    Event::assertNotDispatched(GlobalVariablesSaved::class);
});

it('plans no redirect and writes none when no retired entry has a url', function (): void {
    pmdBoot([]);

    pmdPerson('surv', 'jane');
    pmdPerson('lose', 'old-jane');

    $result = pmdService()->merge(['surv', 'lose'], 'surv', []);

    expect($result->redirectsCreated)->toBe(0)
        ->and($result->redirects)->toBe([]);
});

it('logs and skips the redirects when the redirects global is missing, and still retires the loser', function (): void {
    // The shared fixture already persisted the global; take it away
    /** @var Statamic\Globals\GlobalSet|null $redirects */
    $redirects = GlobalSet::findByHandle('redirects');
    $redirects?->delete();

    pmdPerson('surv', 'jane');
    pmdPerson('lose', 'old-jane');

    Log::partialMock()
        ->shouldReceive('warning')
        ->once()
        ->with('People merge could not create redirects: redirects global set is missing.', ['planned' => 1]);

    $result = pmdService()->merge(['surv', 'lose'], 'surv', []);

    expect($result->redirectsCreated)->toBe(0)
        ->and($result->redirects)->toHaveCount(1)
        ->and(Entry::find('lose'))->toBeNull();
});

it('retires every localization of a loser without firing delete events', function (): void {
    pmdPerson('surv', 'jane');
    $loser = pmdPerson('lose', 'old-jane');
    pmdLocalize($loser, 'health', 'old-jane-health');

    Event::fake([EntryDeleted::class]);

    pmdService()->merge(['surv', 'lose'], 'surv', []);

    expect(Entry::find('lose'))->toBeNull()
        ->and(Entry::all()->count())->toBe(1);
    Event::assertNotDispatched(EntryDeleted::class);
});

it('keeps retiring after a loser that has already gone', function (): void {
    pmdPerson('lose', 'old-jane');

    pmdCall('retireLosers', ['ghost', 'lose']);

    expect(Entry::find('lose'))->toBeNull();
});

it('applies the selections that name a reconcilable field and ignores the ones that name a system field', function (): void {
    pmdPerson('surv', 'survivor', ['bio' => 'A', 'nickname' => 'N-surv']);
    pmdPerson('lose', 'loser', ['bio' => 'B', 'nickname' => 'N-lose']);

    // The system handle comes first, so a selection loop that stopped at it would never reach the others
    $result = pmdService()->merge(['surv', 'lose'], 'surv', ['title' => 'lose', 'slug' => 'lose', 'bio' => 'lose', 'nickname' => 'lose']);

    /** @var Statamic\Entries\Entry $survivor */
    $survivor = Entry::find('surv');

    expect($result->appliedFields)->toBe(['bio', 'nickname'])
        ->and($survivor->slug())->toBe('survivor')
        ->and($survivor->get('bio'))->toBe('B')
        ->and($survivor->get('nickname'))->toBe('N-lose');
});

it('writes no warning when there is nothing to redirect, even without a redirects global', function (): void {
    pmdBoot([]);

    // Booting again does not clear the global an earlier boot stored; take it away
    /** @var Statamic\Globals\GlobalSet|null $redirects */
    $redirects = GlobalSet::findByHandle('redirects');
    $redirects?->delete();

    pmdPerson('surv', 'jane');
    pmdPerson('lose', 'old-jane');

    Log::partialMock()->shouldReceive('warning')->never();

    expect(pmdService()->merge(['surv', 'lose'], 'surv', [])->redirectsCreated)->toBe(0);
});

it('reads numeric ids as the strings they are', function (): void {
    pmdPerson('101', 'one');
    pmdPerson('102', 'two');

    $reconciliation = pmdService()->buildReconciliation(['101', '102']);

    expect($reconciliation->survivorId)->toBe('101');
});
