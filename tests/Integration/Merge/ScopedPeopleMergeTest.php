<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\Role as StatamicRole;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Auth\UserGroup as StatamicUserGroup;
use Statamic\Contracts\Auth\RoleRepository as RoleRepositoryContract;
use Statamic\Contracts\Auth\UserGroupRepository as UserGroupRepositoryContract;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Facades\UserGroup;
use UAMSWeb\Aether\People\Actions\MergePeople;

/**
 * The people editing scope (UAMS-Web/uams-statamic-archive#3182) applied to the people merge feature (UAMS-Web/uams-statamic-archive#3197).
 *
 * A scoped user who holds `merge people entries` may open, preview and commit a
 * merge only when every entry in it is in their scope, and only when the merged
 * survivor stays in it, and is offered the merge action only on entries in scope.
 * Administrators are unaffected. The duplicate-people widget's own scoping moves with the
 * duplicate detection (UAMS-Web/aether-people#86).
 *
 * The scoped role is the shipped `people_editor` plus a throwaway role carrying
 * the merge permission, since no shipped role holds it; the role and group files
 * are repointed at a throwaway directory so nothing tracked is written.
 *
 * @command  vendor/bin/pest tests/Integration/Merge/ScopedPeopleMergeTest.php
 */
const SCOPED_MERGE_ORG_IN = 'org-merge-cardiology';

const SCOPED_MERGE_ORG_OUT = 'org-merge-dermatology';

/**
 * A `people_editor` who may merge, in a group mapped to the in-scope organization.
 */
function scopedMergeEditor(): FileStatamicUser
{
    $role = Role::make('people_merger');
    Assert::assertInstanceOf(StatamicRole::class, $role);

    $role->title('People merger');
    $role->permissions(['merge people entries']);
    $role->save();

    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('scoped-merge-editor@example.test');
    $user->assignRole('people_editor');
    $user->assignRole('people_merger');
    $user->addToGroup('merge_cardiology_editors');
    $user->save();

    return $user;
}

/**
 * A super user, standing for the administrators the scope leaves unchanged.
 */
function scopedMergeAdministrator(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('scoped-merge-admin@example.test');
    $user->makeSuper();
    $user->save();

    return $user;
}

/**
 * Persist a `people` entry, bypassing save listeners.
 *
 * @param  array<string, mixed>  $data
 */
function scopedMergePerson(string $id, array $data): EntryItem
{
    /** @var EntryItem $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale('default');
    $entry->id($id);
    $entry->slug($id);
    $entry->published(true);
    $entry->data(['title' => ucfirst($id), ...$data]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * A `job_title` replicator value holding one faculty role in a department.
 *
 * @return list<array<string, mixed>>
 */
function scopedMergeRole(string $organizationId): array
{
    return [[
        'id' => 'role-'.$organizationId,
        'type' => 'faculty_role_title',
        'enabled' => true,
        'department' => [$organizationId],
    ]];
}

/**
 * A CP route as a string.
 *
 * @param  array<string, string>  $parameters
 */
function scopedMergeRoute(string $name, array $parameters = []): string
{
    $url = cp_route($name, $parameters);
    Assert::assertIsString($url);

    return $url;
}

beforeEach(function (): void {
    isolateStacheAndBlueprintsIn('merge-scoped-people-merge');

    test()->withoutMiddleware(PreventRequestForgery::class);
    test()->withoutVite();
    Cache::flush();

    $directory = storage_path('framework/testing/scoped-people-merge/'.uniqid('', true));
    File::ensureDirectoryExists($directory);
    $this->scopedMergeDirectory = $directory;

    // Ship the tracked roles, so the scoped role under test is the role as shipped
    File::copy(__DIR__.'/../../Fixtures/HostUsers/roles.yaml', $directory.'/roles.yaml');
    app(RoleRepositoryContract::class)->path($directory.'/roles.yaml');

    app(UserGroupRepositoryContract::class)->path($directory.'/groups.yaml');
    /** @var StatamicUserGroup $group */
    $group = UserGroup::make();
    $group->handle('merge_cardiology_editors');
    $group->title('Cardiology editors');
    $group->data(['organizations' => [SCOPED_MERGE_ORG_IN]]);
    $group->save();

    $organizations = CollectionFacade::make('organizations');
    $organizations->title('Organizations');
    $organizations->sites(['default']);
    $organizations->save();

    foreach ([SCOPED_MERGE_ORG_IN => 'Cardiology', SCOPED_MERGE_ORG_OUT => 'Dermatology'] as $id => $title) {
        /** @var EntryItem $organization */
        $organization = Entry::make();
        $organization->collection('organizations');
        $organization->locale('default');
        $organization->id($id);
        $organization->slug($id);
        $organization->data(['title' => $title]);
        $organization->saveQuietly();
    }

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->sites(['default']);
    $people->routes('/people/{slug}');
    $people->save();

    $organizationsField = ['type' => 'entries', 'collections' => ['organizations']];

    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'bio' => ['type' => 'text'],
        'npi' => ['type' => 'text'],
        'service_line' => $organizationsField,
        'job_title' => [
            'type' => 'replicator',
            'sets' => [
                'academic_roles' => [
                    'display' => 'Academic roles',
                    'sets' => [
                        'faculty_role_title' => [
                            'display' => 'Faculty',
                            'fields' => [
                                ['handle' => 'department', 'field' => $organizationsField],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    $blueprint->setHandle('person');
    $blueprint->setNamespace('collections.people');
    $blueprint->save();

    // Two in scope (through a role's department and a service line), two out of scope
    scopedMergePerson('uhura', ['job_title' => scopedMergeRole(SCOPED_MERGE_ORG_IN), 'npi' => '1000000001']);
    scopedMergePerson('nyota', ['service_line' => [SCOPED_MERGE_ORG_IN], 'npi' => '1000000001']);
    scopedMergePerson('chekov', ['job_title' => scopedMergeRole(SCOPED_MERGE_ORG_OUT), 'npi' => '1000000002']);
    scopedMergePerson('pavel', ['service_line' => [SCOPED_MERGE_ORG_OUT], 'npi' => '1000000002']);
});

afterEach(function (): void {
    File::deleteDirectory((string) $this->scopedMergeDirectory);
});

it('lets a scoped user open, preview and commit a merge of two people in scope', function (): void {
    test()->actingAs(scopedMergeEditor());

    test()->get(scopedMergeRoute('people-merge.show', ['ids' => 'uhura,nyota']))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('aether-people::PeopleMerge', false)
                ->where('error', null)
                ->where('reconciliation.survivor_id', 'uhura')
        );

    $payload = ['ids' => ['uhura', 'nyota'], 'survivor' => 'uhura', 'selections' => ['bio' => 'nyota']];

    test()->postJson(scopedMergeRoute('people-merge.preview'), $payload)
        ->assertOk()
        ->assertJsonPath('dry_run', true);

    test()->postJson(scopedMergeRoute('people-merge.commit'), $payload)
        ->assertOk()
        ->assertJsonPath('result.retired_ids', ['nyota']);

    expect(Entry::find('nyota'))->toBeNull()
        ->and(Entry::find('uhura'))->not->toBeNull();
});

it('refuses a scoped user the reconciliation screen when an entry is out of scope, naming it', function (): void {
    test()->actingAs(scopedMergeEditor());

    test()->get(scopedMergeRoute('people-merge.show', ['ids' => 'uhura,chekov']))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('aether-people::PeopleMerge', false)
                ->where('reconciliation', null)
                ->where('error', 'You cannot merge Chekov [chekov]: they are not linked to an organization you edit for.')
        );
});

it('refuses a scoped user a preview that includes an entry out of scope, naming it', function (): void {
    test()->actingAs(scopedMergeEditor());

    test()->postJson(scopedMergeRoute('people-merge.preview'), [
        'ids' => ['uhura', 'chekov'],
        'survivor' => 'uhura',
        'selections' => [],
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'You cannot merge Chekov [chekov]: they are not linked to an organization you edit for.');
});

it('refuses a scoped user a commit that includes an entry out of scope, naming it, and writes nothing', function (): void {
    test()->actingAs(scopedMergeEditor());

    // The out-of-scope entry is the survivor here, so neither position is exempt
    test()->postJson(scopedMergeRoute('people-merge.commit'), [
        'ids' => ['chekov', 'uhura'],
        'survivor' => 'chekov',
        'selections' => [],
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'You cannot merge Chekov [chekov]: they are not linked to an organization you edit for.');

    expect(Entry::find('chekov'))->not->toBeNull()
        ->and(Entry::find('uhura'))->not->toBeNull();
});

it('refuses a scoped user a merge whose survivor would name none of their organizations', function (): void {
    test()->actingAs(scopedMergeEditor());

    // Take the empty role list from nyota and the empty service line from uhura,
    // so the survivor would keep no organization at all
    $payload = [
        'ids' => ['uhura', 'nyota'],
        'survivor' => 'uhura',
        'selections' => ['job_title' => 'nyota', 'service_line' => 'uhura'],
    ];
    $message = 'The merged Uhura [uhura] must stay linked to an organization you edit for. '
        .'Keep one of your organizations on a role or service line.';

    test()->postJson(scopedMergeRoute('people-merge.preview'), $payload)
        ->assertStatus(422)
        ->assertJsonPath('message', $message);

    test()->postJson(scopedMergeRoute('people-merge.commit'), $payload)
        ->assertStatus(422)
        ->assertJsonPath('message', $message);

    // Neither the refused preview nor the refused commit changed anything
    $survivor = Entry::find('uhura');
    Assert::assertInstanceOf(EntryItem::class, $survivor);

    expect($survivor->get('job_title'))->toBe(scopedMergeRole(SCOPED_MERGE_ORG_IN))
        ->and(Entry::find('nyota'))->not->toBeNull();
});

it('lets an administrator merge people outside every scope', function (): void {
    test()->actingAs(scopedMergeAdministrator());

    test()->get(scopedMergeRoute('people-merge.show', ['ids' => 'chekov,pavel']))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('aether-people::PeopleMerge', false)
                ->where('error', null)
        );

    // The survivor may end with no organization at all for an administrator
    test()->postJson(scopedMergeRoute('people-merge.commit'), [
        'ids' => ['chekov', 'pavel'],
        'survivor' => 'chekov',
        'selections' => ['job_title' => 'pavel', 'service_line' => 'chekov'],
    ])
        ->assertOk()
        ->assertJsonPath('result.retired_ids', ['pavel']);

    expect(Entry::find('pavel'))->toBeNull();
});

it('offers the merge action to a scoped user only on entries in scope', function (): void {
    $action = new MergePeople;
    $editor = scopedMergeEditor();
    $administrator = scopedMergeAdministrator();

    $uhura = Entry::find('uhura');
    $chekov = Entry::find('chekov');

    expect($action->authorize($editor, $uhura))->toBeTrue()
        ->and($action->authorize($editor, $chekov))->toBeFalse()
        ->and($action->authorize($administrator, $chekov))->toBeTrue();
});
