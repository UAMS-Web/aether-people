<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Stache\Repositories\EntryRepository;
use UAMSWeb\Aether\People\Duplicates\PeopleDuplicateDetector;
use UAMSWeb\Aether\People\Widgets\DuplicatePeople;

/**
 * CP surface coverage for the {@see DuplicatePeople} dashboard widget (UAMS-Web/uams-statamic-archive#839):
 * permission gating, cluster rendering, and the launch link into the UAMS-Web/uams-statamic-archive#838 merge
 * tool. The widget only suggests, it never merges.
 *
 * @command  vendor/bin/pest tests/Integration/Duplicates/DuplicatePeopleWidgetTest.php
 */

/**
 * Build a person_name structure from a first and last name.
 *
 * @return array<string, mixed>
 */
function dupWidgetName(string $first, string $last): array
{
    return ['first_name' => ['text' => $first], 'last_name' => ['text' => $last]];
}

/**
 * Persist a person entry, bypassing save listeners.
 *
 * @param  array<string, mixed>  $data
 */
function dupWidgetPerson(string $id, string $slug, array $data): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(PeopleDuplicateDetector::peopleCollection());
    $entry->id($id);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);
    $entry->saveQuietly();
}

/**
 * Render the widget and return its View, asserting it produced one.
 */
function dupWidgetView(DuplicatePeople $widget): View
{
    $result = $widget->html();
    Assert::assertInstanceOf(View::class, $result);

    /** @var View $result */
    return $result;
}

beforeEach(function (): void {
    isolateStacheAndBlueprintsIn('duplicates-widget');

    // A fresh cache so one test's detection scan never bleeds into the next.
    Cache::flush();

    /** @var Collection<string, Statamic\Sites\Site> $allSites */
    $allSites = Site::all();
    $siteHandles = $allSites->map(fn ($site) => $site->handle())->values()->all();

    $collection = CollectionFacade::make(PeopleDuplicateDetector::peopleCollection());
    $collection->title('People');
    $collection->sites($siteHandles);
    $collection->routes('/people/{slug}');
    $collection->save();
});

it('is hidden when no user is authenticated', function (): void {
    dupWidgetPerson('a', 'jane', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);
    dupWidgetPerson('b', 'jane-2', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);

    expect((new DuplicatePeople)->html())->toBe('');
});

it('is hidden for a user without the merge permission', function (): void {
    dupWidgetPerson('a', 'jane', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);
    dupWidgetPerson('b', 'jane-2', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);

    // A user with no roles holds no permissions.
    /** @var Statamic\Auth\User $restricted */
    $restricted = User::make();
    $restricted->email('restricted-dup@example.test');
    $restricted->save();

    test()->actingAs($restricted);

    expect((new DuplicatePeople)->html())->toBe('');
});

it('lists detected clusters with confidence and a merge link for a permitted user', function (): void {
    dupWidgetPerson('a', 'jane-smith', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);
    dupWidgetPerson('b', 'jane-smith-2', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);

    /** @var Statamic\Auth\User $super */
    $super = User::make();
    $super->email('super-dup@example.test');
    $super->makeSuper();
    $super->save();

    test()->actingAs($super);

    $view = dupWidgetView(new DuplicatePeople);

    /** @var list<array{confidence: string, members: list<array{title: string, edit_url: string}>, merge_url: string, count: int}> $clusters */
    $clusters = $view->getData()['clusters'];

    expect($clusters)->toHaveCount(1)
        ->and($clusters[0]['confidence'])->toBe('high')
        ->and($clusters[0]['count'])->toBe(2)
        ->and($clusters[0]['members'])->toHaveCount(2)
        // The launch URL targets the UAMS-Web/uams-statamic-archive#838 reconciliation screen with both ids.
        ->and($clusters[0]['merge_url'])->toContain('ids=')
        ->and($clusters[0]['merge_url'])->toContain('a')
        ->and($clusters[0]['merge_url'])->toContain('b');

    // The rendered panel exposes the launch affordance.
    $html = (string) $view->render();
    expect($html)->toContain('Review &amp; merge')
        ->and($html)->toContain($clusters[0]['merge_url']);
});

it('shows an empty state when no duplicates are detected', function (): void {
    dupWidgetPerson('a', 'jane-smith', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1111111111']);
    dupWidgetPerson('b', 'bob-jones', ['person_name' => dupWidgetName('Bob', 'Jones'), 'npi' => '2222222222']);

    /** @var Statamic\Auth\User $super */
    $super = User::make();
    $super->email('super-empty@example.test');
    $super->makeSuper();
    $super->save();

    test()->actingAs($super);

    $view = dupWidgetView(new DuplicatePeople);

    expect($view->getData()['clusters'])->toBe([]);
    expect((string) $view->render())->toContain('No likely-duplicate people detected.');
});

it('does not mutate entries when rendering', function (): void {
    dupWidgetPerson('a', 'jane-smith', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890', 'bio' => 'Original']);
    dupWidgetPerson('b', 'jane-smith-2', ['person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890', 'bio' => 'Second']);

    /** @var Statamic\Auth\User $super */
    $super = User::make();
    $super->email('super-nomutate@example.test');
    $super->makeSuper();
    $super->save();

    test()->actingAs($super);

    dupWidgetView(new DuplicatePeople);

    /** @var Statamic\Entries\Entry $entryA */
    $entryA = Entry::find('a');
    /** @var string $bioA */
    $bioA = $entryA->get('bio');

    /** @var Statamic\Entries\Entry $entryB */
    $entryB = Entry::find('b');
    /** @var string $bioB */
    $bioB = $entryB->get('bio');

    expect($bioA)->toBe('Original')
        ->and($bioB)->toBe('Second');
});

/**
 * Two clusters for a signed-in administrator: a high one (`a`, `b`) and a low one (`c`, `d`).
 */
function dupWidgetTwoClusters(): void
{
    dupWidgetPerson('a', 'jane-smith', ['title' => 'Jane Smith', 'person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);
    dupWidgetPerson('b', 'jane-smith-2', ['title' => 'Jane Smith', 'person_name' => dupWidgetName('Jane', 'Smith'), 'npi' => '1234567890']);
    dupWidgetPerson('c', 'bob-j', ['person_name' => dupWidgetName('Bob', 'Jones')]);
    dupWidgetPerson('d', 'robert-jones', ['person_name' => dupWidgetName('Bob', 'Jones')]);

    /** @var Statamic\Auth\User $super */
    $super = User::make();
    $super->email('super-clusters@example.test');
    $super->makeSuper();
    $super->save();

    test()->actingAs($super);
}

/**
 * The widget's rows, after setting its config.
 *
 * @param  array<string, mixed>  $config
 * @return list<array{title: string, member_ids: list<string>, members: list<array{title: string, edit_url: string}>}>
 */
function dupWidgetRows(array $config = []): array
{
    $widget = new DuplicatePeople;
    $widget->setConfig($config);

    /** @var list<array{title: string, member_ids: list<string>, members: list<array{title: string, edit_url: string}>}> $clusters */
    $clusters = dupWidgetView($widget)->getData()['clusters'];

    return $clusters;
}

it('lists the strongest cluster first and carries its member ids', function (): void {
    dupWidgetTwoClusters();

    $rows = dupWidgetRows();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['member_ids'])->toBe(['a', 'b'])
        ->and($rows[1]['member_ids'])->toBe(['c', 'd']);
});

it('shows each member with its title and edit link', function (): void {
    dupWidgetTwoClusters();

    $members = dupWidgetRows()[0]['members'];

    expect($members[0]['title'])->toBe('Jane Smith')
        ->and($members[0]['edit_url'])->toContain('/entries/a');
});

it('falls back to the slug, then the id, for a member with no title', function (): void {
    dupWidgetTwoClusters();

    $members = dupWidgetRows()[1]['members'];

    // `c` and `d` have no title, so their slugs stand in.
    expect($members[0]['title'])->toBe('bob-j')
        ->and($members[1]['title'])->toBe('robert-jones');

    // An entry saved with an empty slug is given its id as the slug, so its id is what shows.
    dupWidgetPerson('e', '', ['person_name' => dupWidgetName('Eve', 'Quill')]);
    dupWidgetPerson('f', '', ['person_name' => dupWidgetName('Eve', 'Quill')]);
    Cache::flush();

    $eve = array_values(array_filter(dupWidgetRows(), fn (array $row): bool => $row['member_ids'] === ['e', 'f']));
    expect($eve)->toHaveCount(1)
        ->and($eve[0]['members'][0]['title'])->toBe('e');
});

it('does not show an empty title', function (): void {
    dupWidgetPerson('t1', 'tee-one', ['title' => '', 'person_name' => dupWidgetName('Tee', 'Hill'), 'npi' => '8888888888']);
    dupWidgetPerson('t2', 'tee-two', ['title' => '', 'person_name' => dupWidgetName('Tee', 'Hill'), 'npi' => '8888888888']);

    /** @var Statamic\Auth\User $super */
    $super = User::make();
    $super->email('super-title@example.test');
    $super->makeSuper();
    $super->save();

    test()->actingAs($super);

    expect(dupWidgetRows()[0]['members'][0]['title'])->toBe('tee-one');
});

it('caps the list at the configured limit and keeps the strongest first', function (): void {
    dupWidgetTwoClusters();

    $rows = dupWidgetRows(['limit' => 1]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['member_ids'])->toBe(['a', 'b']);
});

it('reads a limit given as a string, and falls back to the default for a limit below 1 or not a number', function (mixed $limit, int $expected): void {
    dupWidgetTwoClusters();

    expect(dupWidgetRows(['limit' => $limit]))->toHaveCount($expected);
})->with([
    'a numeric string' => ['1', 1],
    'zero' => [0, 2],
    'negative' => [-1, 2],
    'a list' => [['x'], 2],
]);

it('caches the detection scan for the configured number of seconds', function (mixed $ttl, int $seconds): void {
    dupWidgetTwoClusters();

    $config = ['cache_ttl' => $ttl];
    expect(dupWidgetRows($config))->toHaveCount(2);

    // A third cluster appears after the scan was cached.
    dupWidgetPerson('g', 'gina-one', ['person_name' => dupWidgetName('Gina', 'Moss'), 'npi' => '7777777777']);
    dupWidgetPerson('h', 'gina-two', ['person_name' => dupWidgetName('Gina', 'Moss'), 'npi' => '7777777777']);

    $this->travel($seconds - 1)->seconds();
    $stillCached = \count(dupWidgetRows($config));

    $this->travel(2)->seconds();
    $rescanned = \count(dupWidgetRows($config));

    expect($stillCached)->toBe(2)
        ->and($rescanned)->toBe(3);
})->with([
    'an integer' => [120, 120],
    'a numeric string' => ['90', 90],
    'a list' => [['x'], 300],
]);

it('drops a member that no longer resolves, and a cluster left with fewer than two, and lists the rest', function (): void {
    foreach (['p1', 'p2', 'p3', 'p4'] as $id) {
        dupWidgetPerson($id, 'slug-'.$id, ['person_name' => dupWidgetName('Pia', 'Park'), 'npi' => '1111111111']);
    }

    foreach (['q1', 'q2', 'q3'] as $id) {
        dupWidgetPerson($id, 'slug-'.$id, ['person_name' => dupWidgetName('Quin', 'Reed'), 'npi' => '2222222222']);
    }

    /** @var Statamic\Auth\User $super */
    $super = User::make();
    $super->email('super-gone@example.test');
    $super->makeSuper();
    $super->save();

    test()->actingAs($super);

    // Three of the four in the larger cluster, which lists first, and the first of the smaller one were removed
    // after detection.
    $detected = (new PeopleDuplicateDetector)->detect();
    $larger = $detected[0]->entryIds;
    $smaller = $detected[1]->entryIds;
    $gone = [$larger[0], $larger[1], $larger[2], $smaller[0]];

    /** @var EntryRepository $real */
    $real = Entry::getFacadeRoot();
    $facade = Mockery::mock($real);
    $facade->shouldReceive('find')->andReturnUsing(
        fn (string $id): ?Statamic\Contracts\Entries\Entry => \in_array($id, $gone, true) ? null : $real->find($id),
    );
    Entry::swap($facade);

    $rows = dupWidgetRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['member_ids'])->toBe([$smaller[1], $smaller[2]]);
});
