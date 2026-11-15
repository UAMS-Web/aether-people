<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\Role as StatamicRole;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Auth\UserGroup as StatamicUserGroup;
use Statamic\Contracts\Auth\RoleRepository as RoleRepositoryContract;
use Statamic\Contracts\Auth\UserGroupRepository as UserGroupRepositoryContract;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Blink as BlinkFacade;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Facades\UserGroup;
use Statamic\Facades\YAML;
use Statamic\Policies\EntryPolicy;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use UAMSWeb\Aether\People\EditingScope\EnforcePeopleEditingScope;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;

/**
 * Scoped `people` editing (UAMS-Web/uams-statamic-archive#3182, decided on UAMS-Web/uams-statamic-archive#3128) and `people` working copies
 * (UAMS-Web/uams-statamic-archive#3184, decided on UAMS-Web/uams-statamic-archive#3127), through the Control Panel's own entry endpoints.
 *
 * A `people_editor` in a group mapped to one organization may open, create and
 * change only the people linked to it, sees only those in the listing and its
 * search, and cannot publish. Administrators are unaffected. With revisions on,
 * a change to a published `people` entry waits as a working copy, while a draft
 * and an entry in another collection save as before.
 *
 * The role's permissions are read from the tracked `resources/users/roles.yaml`,
 * so the test exercises the role as shipped; the role and group files are
 * repointed at a throwaway directory so nothing tracked is written.
 *
 * Linux, PHP 8.4.27, PCOV: EnforcePeopleEditingScope has all 13 statements
 * covered and its 15 remaining covered mutants killed.
 * The listener's InstanceOfToTrue mutant is equivalent: governs() also rejects
 * non-entry values, so removing the earlier type check changes no outcome.
 * Exactly that mutant is annotated; every killable mutant is dead.
 *
 * @see EnforcePeopleEditingScope
 *
 * @command  vendor/bin/pest tests/Integration/EditingScope/ScopedPeopleEditingTest.php
 */
const SCOPED_PEOPLE_ORG_IN = 'org-cardiology';

const SCOPED_PEOPLE_ORG_OUT = 'org-dermatology';

/**
 * A `people_editor` in a group mapped to the in-scope organization.
 */
function scopedPeopleEditor(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('kirk-scoped-people@example.test');
    $user->assignRole('people_editor');
    $user->addToGroup('cardiology_editors');
    $user->save();

    return $user;
}

/**
 * A super user, standing for the administrators the decision leaves unchanged.
 */
function scopedPeopleAdministrator(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('spock-scoped-people@example.test');
    $user->makeSuper();
    $user->save();

    return $user;
}

/**
 * Persist a `people` entry.
 *
 * @param  array<string, mixed>  $data
 */
function scopedPeoplePerson(string $id, array $data, bool $published = true): EntryItem
{
    /** @var EntryItem $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale('default');
    $entry->id($id);
    $entry->slug($id);
    $entry->published($published);
    $entry->data(['title' => ucfirst($id), ...$data]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * A `job_title` replicator value holding one faculty role in a department.
 *
 * @return list<array<string, mixed>>
 */
function scopedPeopleRole(string $organizationId): array
{
    return [[
        'id' => 'role-'.$organizationId,
        'type' => 'faculty_role_title',
        'enabled' => true,
        'department' => [$organizationId],
    ]];
}

/**
 * The CP route for an entry action.
 *
 * @param  array<int, string>  $parameters
 */
function scopedPeopleRoute(string $name, array $parameters): string
{
    $url = cp_route($name, $parameters);
    Assert::assertIsString($url);

    return $url;
}

/**
 * The ids the CP `people` listing returns.
 *
 * @return list<string>
 */
function scopedPeopleListedIds(string $query = ''): array
{
    $url = scopedPeopleRoute('collections.entries.index', ['people']).$query;

    /** @var list<array{id: string}> $rows */
    $rows = test()->getJson($url)->assertOk()->json('data');

    $ids = array_map(fn (array $row): string => $row['id'], $rows);
    sort($ids);

    return $ids;
}

/**
 * The ids an `entries` picker offers, through the CP relationship index.
 *
 * @param  list<string>  $collections  The collections the picker targets
 * @param  string  $search  What the editor typed, if anything
 * @return list<string>
 */
function scopedPeoplePickerIds(array $collections, string $search = ''): array
{
    $config = base64_encode((string) json_encode(['type' => 'entries', 'collections' => $collections]));
    $parameters = ['config' => $config, 'site' => 'default', 'paginate' => false];

    if ($search !== '') {
        $parameters['search'] = $search;
    }

    $url = scopedPeopleRoute('relationship.index', []).'?'.http_build_query($parameters);

    /** @var list<array{id: string}> $rows */
    $rows = test()->getJson($url)->assertOk()->json('data');

    $ids = array_map(fn (array $row): string => $row['id'], $rows);
    sort($ids);

    return $ids;
}

/**
 * Let a user also view `pages` entries, through a role in the throwaway roles file.
 */
function scopedPeopleCanViewPages(FileStatamicUser $user): void
{
    $role = Role::make('page_viewer');
    Assert::assertInstanceOf(StatamicRole::class, $role);

    $role->title('Page viewer');
    $role->permissions(['view pages entries']);
    $role->save();

    $user->assignRole('page_viewer');
    $user->save();
}

/**
 * Persist a `pages` entry.
 */
function scopedPeoplePage(string $id): void
{
    /** @var EntryItem $page */
    $page = Entry::make();
    $page->collection('pages');
    $page->locale('default');
    $page->id($id);
    $page->slug($id);
    $page->data(['title' => ucfirst($id)]);
    $page->saveQuietly();
}

/**
 * The `people` entry with a slug, or null.
 */
function scopedPeopleBySlug(string $slug): ?EntryItem
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'people');
    $query->where('slug', $slug);

    $entry = $query->first();

    return $entry instanceof EntryItem ? $entry : null;
}

/**
 * An entry as its working copy holds it.
 */
function scopedPeopleWorkingCopy(EntryItem $entry): EntryItem
{
    $copy = $entry->fromWorkingCopy();
    Assert::assertInstanceOf(EntryItem::class, $copy);

    return $copy;
}

/**
 * Persist an `organizations` entry.
 */
function scopedPeopleOrganization(string $id, string $title): void
{
    /** @var EntryItem $organization */
    $organization = Entry::make();
    $organization->collection('organizations');
    $organization->locale('default');
    $organization->id($id);
    $organization->slug($id);
    $organization->data(['title' => $title]);
    $organization->saveQuietly();
}

/**
 * Make `organizations` a structured collection holding a tree.
 *
 * @param  list<array<string, mixed>>  $tree
 */
function scopedPeopleOrganizationTree(array $tree): void
{
    $collection = CollectionFacade::findByHandle('organizations');
    Assert::assertInstanceOf(StatamicCollection::class, $collection);

    $collection->structureContents(['root' => false]);
    $collection->save();

    $structure = $collection->structure();
    Assert::assertInstanceOf(CollectionStructure::class, $structure);

    $siteTree = $structure->in('default');
    Assert::assertInstanceOf(Tree::class, $siteTree);

    $siteTree->tree($tree);
    $siteTree->save();
}

/**
 * A brand above a college above the in-scope department above a division, and
 * the out-of-scope department beside it under the same college.
 */
function scopedPeopleHierarchy(): void
{
    scopedPeopleOrganization('org-uams', 'UAMS Health');
    scopedPeopleOrganization('org-medicine', 'College of Medicine');
    scopedPeopleOrganization('org-electrophysiology', 'Electrophysiology');

    scopedPeopleOrganizationTree([
        ['entry' => 'org-uams', 'children' => [
            ['entry' => 'org-medicine', 'children' => [
                ['entry' => SCOPED_PEOPLE_ORG_IN, 'children' => [
                    ['entry' => 'org-electrophysiology'],
                ]],
                ['entry' => SCOPED_PEOPLE_ORG_OUT],
            ]],
        ]],
    ]);
}

/**
 * Point the editor's group at different organizations.
 *
 * @param  list<string>  $organizations
 */
function scopedPeopleGroupOrganizations(array $organizations): void
{
    $group = UserGroup::find('cardiology_editors');
    Assert::assertInstanceOf(StatamicUserGroup::class, $group);

    $group->data(['organizations' => $organizations]);
    $group->save();
}

beforeEach(function (): void {
    test()->withoutMiddleware(PreventRequestForgery::class);
    test()->withoutVite();
    config(['statamic.editions.pro' => true, 'app.debug' => true]);

    $directory = storage_path('framework/testing/scoped-people-editing/'.uniqid('', true));
    File::ensureDirectoryExists($directory);
    $this->scopedPeopleDirectory = $directory;

    // Keep every saved collection, entry and user out of the tracked fixtures directory, in a directory of this test's own
    isolateStacheIn($directory.'/stache');

    // Save fixture blueprints into the throwaway directory, never over the add-on's shipped ones
    $this->fakeBlueprintDirectory = $directory.'/blueprints';
    File::ensureDirectoryExists($this->fakeBlueprintDirectory);
    Blueprint::setDirectory($this->fakeBlueprintDirectory);
    foreach (['people', 'pages'] as $handle) {
        Blueprint::addNamespace("collections.{$handle}", $this->fakeBlueprintDirectory."/collections/{$handle}");
    }

    // Ship the tracked roles, so the role under test is the role as shipped
    File::copy(__DIR__.'/../../Fixtures/HostUsers/roles.yaml', $directory.'/roles.yaml');
    app(RoleRepositoryContract::class)->path($directory.'/roles.yaml');

    // Saving through the repository also drops the groups it read at boot
    app(UserGroupRepositoryContract::class)->path($directory.'/groups.yaml');
    /** @var StatamicUserGroup $group */
    $group = UserGroup::make();
    $group->handle('cardiology_editors');
    $group->title('Cardiology editors');
    $group->data(['organizations' => [SCOPED_PEOPLE_ORG_IN]]);
    $group->save();

    config([
        'statamic.revisions.enabled' => true,
        'statamic.revisions.path' => $directory.'/revisions',
        'statamic.search.indexes.people_scope_test' => [
            'driver' => 'local',
            'searchables' => ['collection:people'],
            'fields' => ['title'],
        ],
        'statamic.search.drivers.local.path' => $directory.'/search',
    ]);

    $organizations = CollectionFacade::make('organizations');
    $organizations->title('Organizations');
    $organizations->sites(['default']);
    $organizations->save();

    foreach ([SCOPED_PEOPLE_ORG_IN => 'Cardiology', SCOPED_PEOPLE_ORG_OUT => 'Dermatology'] as $id => $title) {
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
    $people->revisionsEnabled(true);
    $people->searchIndex('people_scope_test');
    $people->save();

    $organizationsField = ['type' => 'entries', 'collections' => ['organizations']];

    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'bio' => ['type' => 'text'],
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
                                ['handle' => 'department_note', 'field' => ['type' => 'text']],
                            ],
                        ],
                    ],
                ],
                'clinical_roles' => [
                    'display' => 'Clinical roles',
                    'sets' => [
                        'clinical_role' => [
                            'display' => 'Clinical',
                            'fields' => [
                                ['handle' => 'clinic', 'field' => $organizationsField],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'affiliations' => [
            'type' => 'bard',
            'sets' => [
                'affiliation' => [
                    'display' => 'Affiliation',
                    'fields' => [
                        ['handle' => 'organization', 'field' => $organizationsField],
                    ],
                ],
            ],
        ],
        'resident_academic_department' => $organizationsField,
        'related_pages' => ['type' => 'entries', 'collections' => ['pages']],
    ]);
    $blueprint->setHandle('person');
    $blueprint->setNamespace('collections.people');
    $blueprint->save();

    $pages = CollectionFacade::make('pages');
    $pages->title('Pages');
    $pages->sites(['default']);
    $pages->routes('/{slug}');
    $pages->save();

    $pageBlueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'bio' => ['type' => 'text'],
        'service_line' => $organizationsField,
    ]);
    $pageBlueprint->setHandle('page');
    $pageBlueprint->setNamespace('collections.pages');
    $pageBlueprint->save();

    // In scope through a role's department, in scope through a top-level
    // service line, and out of scope
    scopedPeoplePerson('uhura', ['job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_IN)]);
    scopedPeoplePerson('sulu', ['service_line' => [SCOPED_PEOPLE_ORG_IN]]);
    scopedPeoplePerson('chekov', ['job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_OUT)]);
});

afterEach(function (): void {
    File::deleteDirectory((string) $this->scopedPeopleDirectory);
});

it('reads an entry organizations from a role set and a top-level field', function (): void {
    $scope = app(PeopleEditingScope::class);

    /** @var EntryItem $uhura */
    $uhura = Entry::find('uhura');
    /** @var EntryItem $sulu */
    $sulu = Entry::find('sulu');
    /** @var EntryItem $chekov */
    $chekov = Entry::find('chekov');

    expect($scope->entryOrganizationIds($uhura))->toBe([SCOPED_PEOPLE_ORG_IN])
        ->and($scope->entryOrganizationIds($sulu))->toBe([SCOPED_PEOPLE_ORG_IN])
        ->and($scope->entryOrganizationIds($chekov))->toBe([SCOPED_PEOPLE_ORG_OUT]);
});

it('reads an entry organizations from a Bard set', function (): void {
    scopedPeoplePerson('rand', ['affiliations' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Yeoman']]],
        ['type' => 'set', 'attrs' => ['id' => 'set-1', 'values' => [
            'type' => 'affiliation',
            'organization' => [SCOPED_PEOPLE_ORG_IN],
        ]]],
    ]]);

    /** @var EntryItem $rand */
    $rand = Entry::find('rand');

    expect(app(PeopleEditingScope::class)->entryOrganizationIds($rand))->toBe([SCOPED_PEOPLE_ORG_IN]);
});

it('reads every organizations field once, in blueprint order, and nothing else', function (): void {
    scopedPeoplePerson('mccoy', [
        'service_line' => 'org-scalar',
        'job_title' => [
            [
                'id' => 'role-faculty',
                'type' => 'faculty_role_title',
                'enabled' => true,
                'department' => [SCOPED_PEOPLE_ORG_IN, '', 42, SCOPED_PEOPLE_ORG_IN, 'org-a'],
                'department_note' => 'note-text',
            ],
            [
                'id' => 'role-clinical',
                'type' => 'clinical_role',
                'enabled' => true,
                'clinic' => ['org-b'],
            ],
        ],
        'resident_academic_department' => ['org-c'],
        'related_pages' => ['page-one'],
    ]);

    /** @var EntryItem $mccoy */
    $mccoy = Entry::find('mccoy');

    // A scalar id, a second set group, a later top-level field; never an empty
    // or non-string id, a duplicate, a text field in a set, or a link to pages
    expect(app(PeopleEditingScope::class)->entryOrganizationIds($mccoy))
        ->toBe(['org-scalar', SCOPED_PEOPLE_ORG_IN, 'org-a', 'org-b', 'org-c']);
});

it('reads each people blueprint by its own fields', function (): void {
    $clinician = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'clinic_home' => ['type' => 'entries', 'collections' => ['organizations']],
    ]);
    $clinician->setHandle('clinician');
    $clinician->setNamespace('collections.people');
    $clinician->save();

    // The people collection read its blueprints when the beforeEach entries were saved
    BlinkFacade::flush();

    scopedPeoplePerson('riley', ['blueprint' => 'clinician', 'clinic_home' => [SCOPED_PEOPLE_ORG_OUT]]);
    scopedPeoplePerson('rand', ['blueprint' => 'person', 'job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_IN)]);

    $scope = app(PeopleEditingScope::class);

    /** @var EntryItem $rand */
    $rand = Entry::find('rand');
    /** @var EntryItem $riley */
    $riley = Entry::find('riley');

    // Both are read in one request, so the second must not reuse the first's paths
    expect($scope->entryOrganizationIds($rand))->toBe([SCOPED_PEOPLE_ORG_IN])
        ->and($scope->entryOrganizationIds($riley))->toBe([SCOPED_PEOPLE_ORG_OUT]);
});

it('covers the organizations of every group a user is in', function (): void {
    scopedPeopleGroupOrganizations([SCOPED_PEOPLE_ORG_IN, 'org-outside']);

    /** @var StatamicUserGroup $second */
    $second = UserGroup::make();
    $second->handle('medicine_editors');
    $second->title('Medicine editors');
    $second->data(['organizations' => ['org-outside', 'org-elsewhere']]);
    $second->save();

    $editor = scopedPeopleEditor();
    $editor->addToGroup('medicine_editors');
    $editor->save();

    // Organizations outside the tree cover themselves, once each
    expect(app(PeopleEditingScope::class)->userOrganizationIds($editor))
        ->toEqualCanonicalizing([SCOPED_PEOPLE_ORG_IN, 'org-outside', 'org-elsewhere']);
});

it('covers a named organization when the organizations collection is gone', function (): void {
    $editor = scopedPeopleEditor();

    $organizations = CollectionFacade::findByHandle('organizations');
    Assert::assertInstanceOf(StatamicCollection::class, $organizations);
    $organizations->delete();

    expect(app(PeopleEditingScope::class)->userOrganizationIds($editor))->toBe([SCOPED_PEOPLE_ORG_IN]);
});

it('never scopes a request with no user', function (): void {
    expect(app(PeopleEditingScope::class)->appliesTo(null))->toBeFalse();
});

it('lets a scoped editor open an entry in scope and refuses one out of scope', function (): void {
    test()->actingAs(scopedPeopleEditor());

    test()->get(scopedPeopleRoute('collections.entries.edit', ['people', 'uhura']))->assertOk();
    test()->get(scopedPeopleRoute('collections.entries.edit', ['people', 'sulu']))->assertOk();

    test()->getJson(scopedPeopleRoute('collections.entries.edit', ['people', 'chekov']))->assertForbidden();
});

it('lets a scoped editor change a draft in scope and refuses one out of scope', function (): void {
    scopedPeoplePerson('rand', ['job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_IN)], published: false);
    scopedPeoplePerson('chapel', ['job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_OUT)], published: false);

    test()->actingAs(scopedPeopleEditor());

    test()->patchJson(scopedPeopleRoute('collections.entries.update', ['people', 'rand']), [
        'title' => 'Rand',
        'slug' => 'rand',
        'bio' => 'Yeoman',
        'job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_IN),
    ])->assertOk();

    test()->patchJson(scopedPeopleRoute('collections.entries.update', ['people', 'chapel']), [
        'title' => 'Chapel',
        'slug' => 'chapel',
        'bio' => 'Nurse',
        'job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_OUT),
    ])->assertForbidden();

    /** @var EntryItem $rand */
    $rand = Entry::find('rand');
    /** @var EntryItem $chapel */
    $chapel = Entry::find('chapel');

    expect($rand->get('bio'))->toBe('Yeoman')
        ->and($rand->published())->toBeFalse()
        ->and($chapel->get('bio'))->toBeNull();
});

it('refuses a scoped editor change that removes the organization in scope', function (): void {
    scopedPeoplePerson('rand', ['job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_IN)], published: false);

    test()->actingAs(scopedPeopleEditor());

    test()->patchJson(scopedPeopleRoute('collections.entries.update', ['people', 'rand']), [
        'title' => 'Rand',
        'slug' => 'rand',
        'job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_OUT),
    ])->assertServerError()
        ->assertJsonPath(
            'message',
            'This person must stay linked to an organization you edit for. '
            .'Add one of your organizations to a role or service line before saving.'
        );

    /** @var EntryItem $rand */
    $rand = Entry::find('rand');

    expect(app(PeopleEditingScope::class)->entryOrganizationIds($rand))->toBe([SCOPED_PEOPLE_ORG_IN]);
});

it('lets a scoped editor create an entry in scope, as a draft, and refuses one out of scope', function (): void {
    test()->actingAs(scopedPeopleEditor());

    test()->postJson(scopedPeopleRoute('collections.entries.store', ['people', 'default']), [
        'title' => 'Scott',
        'slug' => 'scott',
        'published' => true,
        'job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_IN),
    ])->assertSuccessful();

    test()->postJson(scopedPeopleRoute('collections.entries.store', ['people', 'default']), [
        'title' => 'McCoy',
        'slug' => 'mccoy',
        'job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_OUT),
    ])->assertServerError()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'must stay linked to an organization you edit for'));

    expect(scopedPeopleBySlug('scott'))->toBeInstanceOf(EntryItem::class)
        ->and(scopedPeopleBySlug('scott')?->published())->toBeFalse()
        ->and(scopedPeopleBySlug('mccoy'))->toBeNull();
});

it('lists only the entries in scope to a scoped editor, in the listing and in search', function (): void {
    test()->actingAs(scopedPeopleEditor());

    expect(scopedPeopleListedIds())->toBe(['sulu', 'uhura'])
        ->and(scopedPeopleListedIds('?search=chekov'))->toBe([])
        ->and(scopedPeopleListedIds('?search=uhura'))->toBe(['uhura']);
});

it('leaves an administrator access unchanged', function (): void {
    test()->actingAs(scopedPeopleAdministrator());

    expect(scopedPeopleListedIds())->toBe(['chekov', 'sulu', 'uhura'])
        ->and(scopedPeopleListedIds('?search=chekov'))->toBe(['chekov']);

    test()->get(scopedPeopleRoute('collections.entries.edit', ['people', 'chekov']))->assertOk();
});

it('refuses publishing to a scoped editor and allows it to an administrator', function (): void {
    scopedPeoplePerson('rand', ['job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_IN)], published: false);

    test()->actingAs(scopedPeopleEditor());

    test()->postJson(scopedPeopleRoute('collections.entries.published.store', ['people', 'rand']), ['message' => ''])
        ->assertForbidden();

    /** @var EntryItem $rand */
    $rand = Entry::find('rand');
    expect($rand->published())->toBeFalse();

    test()->actingAs(scopedPeopleAdministrator());

    test()->postJson(scopedPeopleRoute('collections.entries.published.store', ['people', 'rand']), ['message' => ''])
        ->assertOk();

    /** @var EntryItem $rand */
    $rand = Entry::find('rand');
    expect($rand->published())->toBeTrue();
});

it('saves a scoped editor change to a published entry as a working copy', function (): void {
    test()->actingAs(scopedPeopleEditor());

    test()->patchJson(scopedPeopleRoute('collections.entries.update', ['people', 'uhura']), [
        'title' => 'Uhura',
        'slug' => 'uhura',
        'bio' => 'Communications officer',
        'job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_IN),
    ])->assertOk();

    /** @var EntryItem $uhura */
    $uhura = Entry::find('uhura');

    expect($uhura->get('bio'))->toBeNull()
        ->and($uhura->hasWorkingCopy())->toBeTrue()
        ->and(scopedPeopleWorkingCopy($uhura)->get('bio'))->toBe('Communications officer');

    // Publishing the working copy is the administrator's step
    test()->actingAs(scopedPeopleAdministrator());

    test()->postJson(scopedPeopleRoute('collections.entries.published.store', ['people', 'uhura']), ['message' => ''])
        ->assertOk();

    /** @var EntryItem $uhura */
    $uhura = Entry::find('uhura');

    expect($uhura->get('bio'))->toBe('Communications officer')
        ->and($uhura->hasWorkingCopy())->toBeFalse();
});

it('saves a change to a published entry in another collection directly', function (): void {
    /** @var EntryItem $page */
    $page = Entry::make();
    $page->collection('pages');
    $page->locale('default');
    $page->id('bridge');
    $page->slug('bridge');
    $page->published(true);
    $page->data(['title' => 'Bridge']);
    $page->saveQuietly();

    test()->actingAs(scopedPeopleAdministrator());

    test()->patchJson(scopedPeopleRoute('collections.entries.update', ['pages', 'bridge']), [
        'title' => 'Bridge',
        'slug' => 'bridge',
        'bio' => 'Deck one',
        'published' => true,
    ])->assertOk();

    /** @var EntryItem $page */
    $page = Entry::find('bridge');

    expect($page->get('bio'))->toBe('Deck one')
        ->and($page->hasWorkingCopy())->toBeFalse();
});

it('does not enforce people organizations on another collection saved while an editor is signed in', function (): void {
    // Keep the actor scoped while the entry belongs to a different collection.
    scopedPeoplePage('bridge');
    test()->actingAs(scopedPeopleEditor());
    $page = Entry::find('bridge');
    Assert::assertInstanceOf(EntryItem::class, $page);

    // Save outside the user's organizations through the real event listeners.
    $page->set('service_line', [SCOPED_PEOPLE_ORG_OUT]);
    $page->set('bio', 'Deck two');

    expect($page->save())->toBeTrue()
        ->and($page->get('bio'))->toBe('Deck two');
});

it('covers every organization below one a group names', function (): void {
    scopedPeopleHierarchy();
    scopedPeopleGroupOrganizations(['org-medicine']);
    scopedPeoplePerson('chapel', ['job_title' => scopedPeopleRole('org-electrophysiology')]);

    test()->actingAs(scopedPeopleEditor());

    // The division is a grandchild of the college the group names
    test()->getJson(scopedPeopleRoute('collections.entries.edit', ['people', 'chapel']))->assertOk();

    expect(scopedPeopleListedIds())->toBe(['chapel', 'chekov', 'sulu', 'uhura']);
});

it('never covers an organization above one a group names', function (): void {
    scopedPeopleHierarchy();
    scopedPeoplePerson('pike', ['service_line' => ['org-medicine']]);

    test()->actingAs(scopedPeopleEditor());

    test()->getJson(scopedPeopleRoute('collections.entries.edit', ['people', 'pike']))->assertForbidden();

    expect(scopedPeopleListedIds())->toBe(['sulu', 'uhura']);
});

it('takes a move in the organizations tree into account on the next request', function (): void {
    scopedPeopleHierarchy();
    scopedPeoplePerson('chapel', ['job_title' => scopedPeopleRole('org-electrophysiology')]);

    test()->actingAs(scopedPeopleEditor());

    test()->getJson(scopedPeopleRoute('collections.entries.edit', ['people', 'chapel']))->assertOk();

    // The division moves from the in-scope department to the out-of-scope one
    scopedPeopleOrganizationTree([
        ['entry' => 'org-uams', 'children' => [
            ['entry' => 'org-medicine', 'children' => [
                ['entry' => SCOPED_PEOPLE_ORG_IN],
                ['entry' => SCOPED_PEOPLE_ORG_OUT, 'children' => [
                    ['entry' => 'org-electrophysiology'],
                ]],
            ]],
        ]],
    ]);

    test()->getJson(scopedPeopleRoute('collections.entries.edit', ['people', 'chapel']))->assertForbidden();
});

it('grants nothing through a top-level organization a group names', function (): void {
    scopedPeopleHierarchy();
    scopedPeopleGroupOrganizations(['org-uams']);

    test()->actingAs(scopedPeopleEditor());

    test()->getJson(scopedPeopleRoute('collections.entries.edit', ['people', 'uhura']))->assertForbidden();

    expect(scopedPeopleListedIds())->toBe([]);
});

it('refuses a top-level organization on a group and accepts one below it', function (): void {
    scopedPeopleHierarchy();

    // The isolated blueprint root starts empty; use the group blueprint as shipped
    File::copy(__DIR__.'/../../Fixtures/HostUsers/user_group.yaml', $this->fakeBlueprintDirectory.'/user_group.yaml');
    expect(UserGroup::blueprint()->hasField('organizations'))->toBeTrue();

    test()->actingAs(scopedPeopleAdministrator());

    $url = scopedPeopleRoute('user-groups.update', ['cardiology_editors']);

    test()->patchJson($url, [
        'title' => 'Cardiology editors',
        'handle' => 'cardiology_editors',
        'roles' => [],
        'organizations' => ['org-uams'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['organizations' => 'UAMS Health is at the top of the organizations tree']);

    test()->patchJson($url, [
        'title' => 'Cardiology editors',
        'handle' => 'cardiology_editors',
        'roles' => [],
        'organizations' => ['org-medicine'],
    ])->assertOk();

    $group = UserGroup::find('cardiology_editors');
    Assert::assertInstanceOf(StatamicUserGroup::class, $group);

    expect($group->get('organizations'))->toBe(['org-medicine']);
});

it('scopes publishing for a role that may publish', function (): void {
    /** @var StatamicRole $role */
    $role = Role::make();
    $role->handle('people_publisher');
    $role->title('People publisher');
    $role->permissions(['publish people entries']);
    $role->save();

    scopedPeoplePerson('rand', ['job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_IN)], published: false);
    scopedPeoplePerson('chapel', ['job_title' => scopedPeopleRole(SCOPED_PEOPLE_ORG_OUT)], published: false);

    $editor = scopedPeopleEditor();
    $editor->assignRole('people_publisher');
    $editor->save();

    test()->actingAs($editor);

    test()->postJson(scopedPeopleRoute('collections.entries.published.store', ['people', 'chapel']), ['message' => ''])
        ->assertForbidden();
    test()->postJson(scopedPeopleRoute('collections.entries.published.store', ['people', 'rand']), ['message' => ''])
        ->assertOk();
});

it('governs only people entries', function (): void {
    /** @var EntryItem $page */
    $page = Entry::make();
    $page->collection('pages');
    $page->locale('default');
    $page->id('sickbay');
    $page->slug('sickbay');
    $page->data(['title' => 'Sickbay', 'service_line' => [SCOPED_PEOPLE_ORG_IN]]);
    $page->saveQuietly();

    $scope = app(PeopleEditingScope::class);

    expect($scope->governs($page))->toBeFalse()
        ->and($scope->governs(Entry::find('uhura')))->toBeTrue()
        ->and($scope->governs(null))->toBeFalse()
        ->and($scope->editableEntryIds(scopedPeopleEditor()))->toEqualCanonicalizing(['sulu', 'uhura']);
});

it('lists the people entries a scoped user may not act on, and no other entry', function (): void {
    scopedPeoplePage('sickbay');

    $scope = app(PeopleEditingScope::class);

    expect($scope->outOfScopeEntryIds(scopedPeopleEditor()))->toBe(['chekov']);

    scopedPeopleGroupOrganizations([]);

    // A group that names nothing covers nothing, so every person is out of scope
    expect($scope->outOfScopeEntryIds(scopedPeopleEditor()))->toEqualCanonicalizing(['chekov', 'sulu', 'uhura']);
});

it('lists no entry when the people collection is gone', function (): void {
    $editor = scopedPeopleEditor();

    $people = CollectionFacade::findByHandle('people');
    Assert::assertInstanceOf(StatamicCollection::class, $people);
    $people->delete();

    $scope = app(PeopleEditingScope::class);

    expect($scope->outOfScopeEntryIds($editor))->toBe([])
        ->and($scope->editableEntryIds($editor))->toBe([]);
});

it('covers nothing for a user in no group', function (): void {
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('rand-ungrouped@example.test');
    $user->save();

    expect(app(PeopleEditingScope::class)->userOrganizationIds($user))->toBe([]);
});

it('skips a malformed role row and goes on to the rows after it', function (): void {
    scopedPeoplePerson('rand', ['job_title' => [
        'not-a-row',
        ['id' => 'role-untyped', 'type' => 7, 'department' => [SCOPED_PEOPLE_ORG_OUT]],
        ...scopedPeopleRole(SCOPED_PEOPLE_ORG_IN),
    ]]);

    /** @var EntryItem $rand */
    $rand = Entry::find('rand');

    expect(app(PeopleEditingScope::class)->entryOrganizationIds($rand))->toBe([SCOPED_PEOPLE_ORG_IN]);
});

it('skips a malformed set definition and goes on to the sets after it', function (): void {
    $organizationsField = ['type' => 'entries', 'collections' => ['organizations']];

    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'no_sets' => ['type' => 'replicator'],
        'job_title' => [
            'type' => 'replicator',
            'sets' => [
                'broken' => 'not-a-set',
                'clinical_role' => [
                    'display' => 'Clinical',
                    'fields' => [
                        ['handle' => 'clinic', 'field' => $organizationsField],
                    ],
                ],
            ],
        ],
    ]);
    $blueprint->setHandle('malformed');
    $blueprint->setNamespace('collections.people');
    $blueprint->save();

    BlinkFacade::flush();

    scopedPeoplePerson('riley', ['blueprint' => 'malformed', 'job_title' => [
        ['id' => 'role-clinical', 'type' => 'clinical_role', 'enabled' => true, 'clinic' => [SCOPED_PEOPLE_ORG_IN]],
    ]]);

    /** @var EntryItem $riley */
    $riley = Entry::find('riley');

    expect(app(PeopleEditingScope::class)->entryOrganizationIds($riley))->toBe([SCOPED_PEOPLE_ORG_IN]);
});

it('scopes who may delete a people entry', function (): void {
    /** @var StatamicRole $role */
    $role = Role::make();
    $role->handle('people_deleter');
    $role->title('People deleter');
    $role->permissions(['delete people entries']);
    $role->save();

    $editor = scopedPeopleEditor();
    $editor->assignRole('people_deleter');
    $editor->save();

    $policy = app(EntryPolicy::class);

    expect($policy->delete($editor, Entry::find('uhura')))->toBeTrue()
        ->and($policy->delete($editor, Entry::find('chekov')))->toBeFalse();
});

it('leaves a user the scope does not apply to unrestricted in the policy', function (): void {
    /** @var StatamicRole $role */
    $role = Role::make();
    $role->handle('collection_manager');
    $role->title('Collection manager');
    $role->permissions(['configure collections', 'edit people entries', 'delete people entries']);
    $role->save();

    $manager = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $manager);

    $manager->id((string) Str::uuid());
    $manager->email('scott-manager@example.test');
    $manager->assignRole('collection_manager');
    $manager->addToGroup('cardiology_editors');
    $manager->save();

    $policy = app(EntryPolicy::class);

    // In a group that covers only Cardiology, yet not limited to it: the scope applies to neither
    expect($policy->edit($manager, Entry::find('chekov')))->toBeTrue()
        ->and($policy->delete($manager, Entry::find('chekov')))->toBeTrue();
});
