<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Globals\Variables;
use UAMSWeb\Aether\People\Http\Controllers\MergeController;
use UAMSWeb\Aether\People\Merge\PeopleMergeService;

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
