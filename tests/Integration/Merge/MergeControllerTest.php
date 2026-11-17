<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\Role as StatamicRole;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Contracts\Auth\RoleRepository as RoleRepositoryContract;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Role;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Globals\Variables;
use UAMSWeb\Aether\People\Contracts\MergeRedirectWriter;
use UAMSWeb\Aether\People\Http\Controllers\MergeController;
use UAMSWeb\Aether\People\Merge\MergeResult;
use UAMSWeb\Aether\People\Merge\PeopleMergeService;
use UAMSWeb\Aether\People\Support\ReferenceRepointSummary;

covers(MergeController::class);

/**
 * HTTP coverage for {@see MergeController} (UAMS-Web/uams-statamic-archive#838):
 * the reconciliation screen renders, preview is non-destructive, commit performs
 * the merge, and every endpoint is gated by the merge permission.
 */
/**
 * Persist a super user (holds the merge permission) for an authorized request.
 */
function mergeControllerSuperUser(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('people-merge-controller-test@example.test');
    $user->makeSuper();
    $user->save();

    return $user;
}

/**
 * Persist a plain user that cannot access the CP merge tool.
 */
function mergeControllerPlainUser(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('people-merge-plain-test@example.test');
    $user->save();

    return $user;
}

/**
 * @param  array<string, mixed>  $data
 */
function mergeControllerPerson(string $id, string $slug, array $data): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(PeopleMergeService::peopleCollection());
    $entry->id($id);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);
    $entry->saveQuietly();
}

beforeEach(function (): void {
    isolateStacheAndBlueprintsIn('merge-merge-controller');

    test()->withoutMiddleware(PreventRequestForgery::class);

    // The CP reconciliation screen renders Statamic's CP layout, which @vites the
    // app's CP bundle (public/vendor/app/manifest.json). That bundle is built by
    // `npm run cp:build`, which the PHPUnit/Pest CI job does not run — so resolve
    // CP assets to stubs here (the controller's server response is what's under
    // test; the real CP bundle is exercised by the browser suite).
    test()->withoutVite();

    /** @var Collection<string, Statamic\Sites\Site> $sites */
    $sites = Site::all();
    $siteHandles = $sites->map(fn ($site) => $site->handle())->values()->all();

    $collection = CollectionFacade::make(PeopleMergeService::peopleCollection());
    $collection->title('People');
    $collection->sites($siteHandles);
    $collection->routes('/people/{slug}');
    $collection->save();

    $blueprint = Blueprint::makeFromFields([
        'bio' => ['type' => 'text'],
    ]);
    $blueprint->setHandle('person');
    $blueprint->setNamespace('collections.'.PeopleMergeService::peopleCollection());
    $blueprint->save();

    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->save();

    /** @var Statamic\Sites\Site $defaultSite */
    $defaultSite = Site::default();

    /** @var Variables $vars */
    $vars = $set->makeLocalization($defaultSite->handle());
    $vars->data(['redirects' => []]);
    $vars->save();
});

it('renders the reconciliation screen for the selected entries', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    test()->actingAs(mergeControllerSuperUser());

    /** @var string $url */
    $url = cp_route('people-merge.show', ['ids' => 'surv,lose']);

    test()->get($url)->assertOk();
});

it('preview is non-destructive and reports the plan', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    test()->actingAs(mergeControllerSuperUser());

    /** @var string $url */
    $url = cp_route('people-merge.preview');

    test()->postJson($url, [
        'ids' => ['surv', 'lose'],
        'survivor' => 'surv',
        'selections' => ['bio' => 'lose'],
    ])
        ->assertOk()
        ->assertJsonPath('dry_run', true);

    // Nothing was written.
    expect(Entry::find('lose'))->not->toBeNull();
});

it('commit performs the merge and returns the survivor edit url', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    test()->actingAs(mergeControllerSuperUser());

    /** @var string $url */
    $url = cp_route('people-merge.commit');

    test()->postJson($url, [
        'ids' => ['surv', 'lose'],
        'survivor' => 'surv',
        'selections' => ['bio' => 'lose'],
    ])
        ->assertOk()
        ->assertJsonPath('result.dry_run', false)
        ->assertJsonPath('result.retired_ids', ['lose']);

    /** @var Statamic\Entries\Entry $survivor */
    $survivor = Entry::find('surv');

    /** @var Collection<string, mixed> $survivorData */
    $survivorData = $survivor->data();

    expect($survivorData->all()['bio'])->toBe('B')
        ->and(Entry::find('lose'))->toBeNull();
});

it('returns 422 when fewer than two ids are submitted', function (): void {
    test()->actingAs(mergeControllerSuperUser());

    /** @var string $url */
    $url = cp_route('people-merge.commit');

    test()->postJson($url, [
        'ids' => ['surv'],
        'survivor' => 'surv',
    ])->assertStatus(422);
});

it('returns 422 when the survivor is not among the candidates', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    test()->actingAs(mergeControllerSuperUser());

    /** @var string $url */
    $url = cp_route('people-merge.commit');

    test()->postJson($url, [
        'ids' => ['surv', 'lose'],
        'survivor' => 'nope',
        'selections' => [],
    ])->assertStatus(422);
});

it('forbids users without the merge permission', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    test()->actingAs(mergeControllerPlainUser());

    /** @var string $url */
    $url = cp_route('people-merge.commit');

    test()->postJson($url, [
        'ids' => ['surv', 'lose'],
        'survivor' => 'surv',
        'selections' => [],
    ])->assertForbidden();

    // The merge did not run.
    expect(Entry::find('lose'))->not->toBeNull();
});

/**
 * A Control Panel route as a string, which `cp_route()` does not promise.
 *
 * @param  array<string, mixed>  $parameters
 */
function mergeControllerRoute(string $name, array $parameters = []): string
{
    $url = cp_route($name, $parameters);
    Assert::assertIsString($url);

    return $url;
}

/**
 * A user who may open the Control Panel but does not hold the merge permission.
 */
function mergeControllerCpOnlyUser(): FileStatamicUser
{
    $directory = storage_path('framework/testing/merge-controller/'.uniqid('', true));
    File::ensureDirectoryExists($directory);
    app(RoleRepositoryContract::class)->path($directory.'/roles.yaml');

    $role = Role::make('cp_only');
    Assert::assertInstanceOf(StatamicRole::class, $role);

    $role->title('CP only');
    $role->permissions(['access cp']);
    $role->save();

    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('people-merge-cp-only@example.test');
    $user->assignRole('cp_only');
    $user->save();

    return $user;
}

it('refuses a Control Panel user without the merge permission on every endpoint', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    test()->actingAs(mergeControllerCpOnlyUser());

    $body = ['ids' => ['surv', 'lose'], 'survivor' => 'surv', 'selections' => []];

    // A page request the Control Panel refuses is sent back to its dashboard; a JSON request is answered 403.
    test()->get(mergeControllerRoute('people-merge.show', ['ids' => 'surv,lose']))->assertRedirect('http://localhost/cp');
    test()->postJson(mergeControllerRoute('people-merge.preview'), $body)->assertForbidden();
    test()->postJson(mergeControllerRoute('people-merge.commit'), $body)->assertForbidden();

    expect(Entry::find('lose'))->not->toBeNull();
});

it('hands the screen the reconciliation, the two action urls and no error', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    test()->actingAs(mergeControllerSuperUser());

    test()->get(mergeControllerRoute('people-merge.show', ['ids' => 'surv,lose']), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('component', 'aether-people::PeopleMerge')
        ->assertJsonPath('props.error', null)
        ->assertJsonPath('props.previewUrl', mergeControllerRoute('people-merge.preview'))
        ->assertJsonPath('props.commitUrl', mergeControllerRoute('people-merge.commit'))
        ->assertJsonPath('props.reconciliation.survivor_id', 'surv')
        ->assertJsonPath('props.reconciliation.candidates.0.id', 'surv')
        ->assertJsonPath('props.reconciliation.candidates.1.id', 'lose');
});

it('trims the ids, drops blanks and repeats, and keeps the first as the survivor', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    test()->actingAs(mergeControllerSuperUser());

    $response = test()->get(mergeControllerRoute('people-merge.show', ['ids' => ' surv, lose ,,surv']), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.error', null)
        ->assertJsonPath('props.reconciliation.survivor_id', 'surv');

    /** @var list<array{id: string}> $candidates */
    $candidates = $response->json('props.reconciliation.candidates');

    expect(array_column($candidates, 'id'))->toBe(['surv', 'lose']);
});

it('puts the reason on the screen instead of a reconciliation when too few ids are selected', function (string $ids): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);

    test()->actingAs(mergeControllerSuperUser());

    test()->get(mergeControllerRoute('people-merge.show', ['ids' => $ids]), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props.reconciliation', null)
        ->assertJsonPath('props.error', 'Select at least two people entries to merge.');
})->with(['one id' => 'surv', 'blank' => '', 'only separators' => ' , ,']);

it('treats a missing or non-text ids value as no selection', function (): void {
    test()->actingAs(mergeControllerSuperUser());

    foreach ([mergeControllerRoute('people-merge.show'), mergeControllerRoute('people-merge.show', ['ids' => ['surv', 'lose']])] as $url) {
        test()->get($url, ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.reconciliation', null)
            ->assertJsonPath('props.error', 'Select at least two people entries to merge.');
    }
});

it('rejects a merge request whose body breaks a validation rule', function (array $body, string $field): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    test()->actingAs(mergeControllerSuperUser());

    foreach ([mergeControllerRoute('people-merge.preview'), mergeControllerRoute('people-merge.commit')] as $url) {
        test()->postJson($url, $body)->assertStatus(422)->assertJsonValidationErrors([$field]);
    }

    expect(Entry::find('lose'))->not->toBeNull();
})->with([
    'ids missing' => [['survivor' => 'surv'], 'ids'],
    'ids not a list' => [['ids' => 'surv,lose', 'survivor' => 'surv'], 'ids'],
    'ids a single entry' => [['ids' => ['surv'], 'survivor' => 'surv'], 'ids'],
    'an id that is not text' => [['ids' => ['surv', ['lose']], 'survivor' => 'surv'], 'ids.1'],
    'an id that is null' => [['ids' => ['surv', null], 'survivor' => 'surv'], 'ids.1'],
    'an id left blank' => [['ids' => ['surv', ''], 'survivor' => 'surv'], 'ids.1'],
    'survivor missing' => [['ids' => ['surv', 'lose']], 'survivor'],
    'survivor blank' => [['ids' => ['surv', 'lose'], 'survivor' => ''], 'survivor'],
    'survivor not text' => [['ids' => ['surv', 'lose'], 'survivor' => ['surv']], 'survivor'],
    'selections not a map' => [['ids' => ['surv', 'lose'], 'survivor' => 'surv', 'selections' => 'bio'], 'selections'],
    'a selection that is not text' => [['ids' => ['surv', 'lose'], 'survivor' => 'surv', 'selections' => ['bio' => ['lose']]], 'selections.bio'],
]);

it('treats a request with no selections as keeping every survivor value', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    test()->actingAs(mergeControllerSuperUser());

    test()->postJson(mergeControllerRoute('people-merge.preview'), ['ids' => ['surv', 'lose'], 'survivor' => 'surv'])
        ->assertOk()
        ->assertJsonPath('applied_fields', [])
        ->assertJsonPath('retired_ids', ['lose']);
});

it('returns the survivor edit url with the committed result', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    test()->actingAs(mergeControllerSuperUser());

    test()->postJson(mergeControllerRoute('people-merge.commit'), ['ids' => ['surv', 'lose'], 'survivor' => 'surv'])
        ->assertOk()
        ->assertJsonPath('survivor_edit_url', 'http://localhost/cp/collections/people/entries/surv')
        ->assertJsonPath('result.survivor_id', 'surv');
});

it('returns no edit url when the survivor can no longer be found after the merge', function (): void {
    /** @var MockInterface&PeopleMergeService $service */
    $service = Mockery::mock(PeopleMergeService::class);
    $service->shouldReceive('merge')->once()->andReturn(
        new MergeResult('ghost', false, [], new ReferenceRepointSummary(0, 0, 0, 0), 0, [], ['lose']),
    );
    app()->instance(PeopleMergeService::class, $service);

    test()->actingAs(mergeControllerSuperUser());

    test()->postJson(mergeControllerRoute('people-merge.commit'), ['ids' => ['ghost', 'lose'], 'survivor' => 'ghost'])
        ->assertOk()
        ->assertJsonPath('survivor_edit_url', null)
        ->assertJsonPath('result.survivor_id', 'ghost');
});

it('turns a merge the service refuses into a 422 carrying its message', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);

    test()->actingAs(mergeControllerSuperUser());

    test()->postJson(mergeControllerRoute('people-merge.commit'), ['ids' => ['surv', 'nope'], 'survivor' => 'surv'])
        ->assertStatus(422)
        ->assertJsonStructure(['message']);

    test()->postJson(mergeControllerRoute('people-merge.preview'), ['ids' => ['surv', 'nope'], 'survivor' => 'surv'])
        ->assertStatus(422)
        ->assertJsonStructure(['message']);
});

it('refuses a preview and a commit with a 422 naming the binding when the host has bound no redirect writer', function (): void {
    mergeControllerPerson('surv', 'survivor', ['bio' => 'A']);
    mergeControllerPerson('lose', 'loser', ['bio' => 'B']);

    // The test case binds a writer the way a host does; take the binding away
    unset(app()[MergeRedirectWriter::class]);

    test()->actingAs(mergeControllerSuperUser());

    foreach (['people-merge.preview', 'people-merge.commit'] as $route) {
        test()->postJson(mergeControllerRoute($route), ['ids' => ['surv', 'lose'], 'survivor' => 'surv'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, MergeRedirectWriter::class));
    }

    expect(Entry::find('lose'))->not->toBeNull();
});
