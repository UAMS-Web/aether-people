<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Auth\UserGroup as StatamicUserGroup;
use Statamic\Contracts\Auth\RoleRepository as RoleRepositoryContract;
use Statamic\Contracts\Auth\UserGroupRepository as UserGroupRepositoryContract;
use Statamic\Contracts\Revisions\Revision;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\RevisionSaving;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Facades\UserGroup;
use Statamic\Fields\Blueprint as BlueprintItem;
use Statamic\Fields\Field;
use Statamic\Stache\Query\EntryQueryBuilder;
use UAMSWeb\Aether\People\Support\WorkdayManagedFields;
use UAMSWeb\Aether\People\Workday\MakeWorkdayFieldsReadOnly;
use UAMSWeb\Aether\People\Workday\RestoreWorkdayManagedFields;

/**
 * The Workday-managed `people` fields (UAMS-Web/uams-statamic-archive#3186, decided on UAMS-Web/uams-statamic-archive#3130), through the
 * Control Panel's own entry endpoints.
 *
 * A `people_editor` sees the Workday IDs, `uams_worker_query`, the two
 * subtypes and the legal name parts greyed out, and a save, or a working copy, keeps their stored values
 * whatever the request carries. A trainee status field is held the same way
 * only once the entry's `workday_supplied` list names it, and is an ordinary
 * field otherwise. Administrators, and saves with no signed-in user, are not
 * held. Gender and the nickname kept beside the legal name are not among the
 * fields and stay editable.
 *
 * The last cases call `RestoreWorkdayManagedFields::handleRevisionSaving()`
 * directly with a revision double, for the revisions it must leave alone. Its
 * accepted equivalents are annotated in the listener: the `instanceof Entry`
 * guard on a saving entry, which `governs()` repeats, and the `instanceof`
 * guard on `$entry->data()`, which is always a collection.
 *
 * @command  vendor/bin/pest tests/Integration/Workday/WorkdayManagedFieldsTest.php
 */
const WORKDAY_FIELDS_ORG = 'org-workday-fields';

/**
 * A `people_editor` in a group mapped to the test organization.
 */
function workdayFieldsEditor(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('nyota-workday-fields@example.test');
    $user->assignRole('people_editor');
    $user->addToGroup('workday_fields_editors');
    $user->save();

    return $user;
}

/**
 * A super user, standing for the administrators the decision exempts.
 */
function workdayFieldsAdministrator(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('christopher-workday-fields@example.test');
    $user->makeSuper();
    $user->save();

    return $user;
}

/**
 * Persist a `people` entry linked to the test organization, carrying stored
 * Workday values unless they are overridden.
 *
 * @param  array<string, mixed>  $data
 */
function workdayFieldsPerson(string $id, array $data = [], bool $published = false): EntryItem
{
    /** @var EntryItem $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale('default');
    $entry->id($id);
    $entry->slug($id);
    $entry->published($published);
    $entry->data([
        'title' => ucfirst($id),
        'service_line' => [WORKDAY_FIELDS_ORG],
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_employee_subtype' => 'regular',
        'uams_workday_id' => ['workday_universal_id' => 'WD-100', 'workday_employee_id' => 'E-100'],
        'gender' => 'female',
        'person_name' => ['first_name' => 'Nyota', 'last_name' => 'Uhura'],
        ...$data,
    ]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * The request body for a change that tries to rewrite every Workday-managed
 * field, the trainee fields and gender, and also changes the bio.
 *
 * @return array<string, mixed>
 */
function workdayFieldsChange(string $id): array
{
    return [
        'title' => ucfirst($id),
        'slug' => $id,
        'service_line' => [WORKDAY_FIELDS_ORG],
        'bio' => 'Changed bio',
        'uams_worker_query' => false,
        'uams_worker_subtype' => 'contingent_worker',
        'uams_employee_subtype' => 'temporary',
        'uams_workday_id' => ['workday_universal_id' => 'WD-999', 'workday_employee_id' => 'E-999'],
        'resident_query' => true,
        'fellow_query' => true,
        'gender' => 'male',
        'person_name' => ['first_name' => 'Changed', 'last_name' => 'Name'],
        'nickname' => 'Bones',
    ];
}

/**
 * The CP update route for a `people` entry.
 */
function workdayFieldsUpdateUrl(string $id): string
{
    $url = cp_route('collections.entries.update', ['people', $id]);
    Assert::assertIsString($url);

    return $url;
}

/**
 * A fresh read of a `people` entry.
 */
function workdayFieldsFind(string $id): EntryItem
{
    $entry = Entry::find($id);
    Assert::assertInstanceOf(EntryItem::class, $entry);

    return $entry;
}

/**
 * The visibility of a field on an entry's blueprint, as already resolved.
 */
function workdayFieldsFieldVisibility(EntryItem $entry, string $handle): string
{
    $blueprint = $entry->blueprint();
    Assert::assertInstanceOf(BlueprintItem::class, $blueprint);

    $field = $blueprint->field($handle);
    Assert::assertInstanceOf(Field::class, $field);

    $visibility = $field->visibility();
    Assert::assertIsString($visibility);

    return $visibility;
}

/**
 * The visibility of a field on an entry's blueprint, resolved afresh for the
 * signed-in user.
 */
function workdayFieldsVisibility(string $id, string $handle): string
{
    Blink::flush();

    return workdayFieldsFieldVisibility(workdayFieldsFind($id), $handle);
}

beforeEach(function (): void {
    test()->withoutMiddleware(PreventRequestForgery::class);
    test()->withoutVite();

    $directory = storage_path('framework/testing/workday-managed-fields/'.uniqid('', true));
    File::ensureDirectoryExists($directory);
    $this->workdayFieldsDirectory = $directory;

    config(['statamic.editions.pro' => true, 'app.debug' => true]);

    // Keep every saved collection, entry and user out of the tracked fixtures directory, in a directory of this test's own
    isolateStacheIn($directory.'/stache');

    // Save fixture blueprints into the throwaway directory, never over the add-on's shipped ones
    $fakeBlueprintDirectory = $directory.'/blueprints';
    File::ensureDirectoryExists($fakeBlueprintDirectory);
    Blueprint::setDirectory($fakeBlueprintDirectory);
    Blueprint::addNamespace('collections.people', $fakeBlueprintDirectory.'/collections/people');

    // Ship the tracked roles, so the role under test is the role as shipped
    File::copy(__DIR__.'/../../Fixtures/HostUsers/roles.yaml', $directory.'/roles.yaml');
    app(RoleRepositoryContract::class)->path($directory.'/roles.yaml');

    app(UserGroupRepositoryContract::class)->path($directory.'/groups.yaml');
    /** @var StatamicUserGroup $group */
    $group = UserGroup::make();
    $group->handle('workday_fields_editors');
    $group->title('Workday fields editors');
    $group->data(['organizations' => [WORKDAY_FIELDS_ORG]]);
    $group->save();

    config([
        'statamic.revisions.enabled' => true,
        'statamic.revisions.path' => $directory.'/revisions',
    ]);

    $organizations = CollectionFacade::make('organizations');
    $organizations->title('Organizations');
    $organizations->sites(['default']);
    $organizations->save();

    /** @var EntryItem $organization */
    $organization = Entry::make();
    $organization->collection('organizations');
    $organization->locale('default');
    $organization->id(WORKDAY_FIELDS_ORG);
    $organization->slug(WORKDAY_FIELDS_ORG);
    $organization->data(['title' => 'Cardiology']);
    $organization->saveQuietly();

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->sites(['default']);
    $people->routes('/people/{slug}');
    $people->revisionsEnabled(true);
    $people->save();

    $toggle = ['type' => 'toggle'];

    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'bio' => ['type' => 'text'],
        'gender' => ['type' => 'text'],
        'workday_gender' => ['type' => 'text'],
        'nickname' => ['type' => 'text'],
        'person_name' => ['type' => 'group', 'fields' => [
            ['handle' => 'first_name', 'field' => ['type' => 'text']],
            ['handle' => 'last_name', 'field' => ['type' => 'text']],
        ]],
        'service_line' => ['type' => 'entries', 'collections' => ['organizations']],
        'uams_worker_query' => $toggle,
        'uams_worker_subtype' => ['type' => 'radio', 'options' => ['employee' => 'Employee', 'contingent_worker' => 'Contingent Worker']],
        'uams_employee_subtype' => ['type' => 'radio', 'options' => ['regular' => 'Regular', 'temporary' => 'Temporary']],
        'uams_workday_id' => ['type' => 'group', 'fields' => [
            ['handle' => 'workday_universal_id', 'field' => ['type' => 'text']],
            ['handle' => 'workday_employee_id', 'field' => ['type' => 'text']],
        ]],
        'resident_query' => $toggle,
        'fellow_query' => $toggle,
    ]);
    $blueprint->setHandle('person');
    $blueprint->setNamespace('collections.people');
    $blueprint->save();
});

afterEach(function (): void {
    File::deleteDirectory((string) $this->workdayFieldsDirectory);
});

it('locks the always-Workday fields and each trainee field only once Workday supplied it', function (): void {
    $fields = app(WorkdayManagedFields::class);

    expect($fields->lockedHandles([]))->toBe(['person_name', 'uams_worker_query', 'uams_worker_subtype', 'uams_employee_subtype', 'uams_workday_id', 'workday_gender'])
        ->and($fields->isLocked('resident_query', []))->toBeFalse()
        ->and($fields->isLocked('resident_query', ['workday_supplied' => ['resident_query']]))->toBeTrue()
        ->and($fields->isLocked('fellow_query', ['workday_supplied' => ['resident_query']]))->toBeFalse()
        ->and($fields->isLocked('gender', ['workday_supplied' => ['gender']]))->toBeFalse()
        ->and($fields->isLocked('resident_query', ['workday_supplied' => 'resident_query']))->toBeFalse();
});

it('puts back a stored value, drops one never stored, and keeps the provenance list', function (): void {
    $restored = app(WorkdayManagedFields::class)->restore(
        ['uams_worker_query' => false, 'uams_worker_subtype' => 'employee', 'workday_supplied' => [], 'bio' => 'New'],
        ['uams_worker_query' => true, 'workday_supplied' => ['fellow_query']],
    );

    expect($restored)->toBe(['uams_worker_query' => true, 'workday_supplied' => ['fellow_query'], 'bio' => 'New']);
});

it('greys out the Workday fields for an editor and not for an administrator', function (): void {
    workdayFieldsPerson('nyota');

    test()->actingAs(workdayFieldsEditor());

    foreach (WorkdayManagedFields::ALWAYS_LOCKED as $handle) {
        expect(workdayFieldsVisibility('nyota', $handle))->toBe('read_only');
    }

    expect(workdayFieldsVisibility('nyota', 'resident_query'))->toBe('visible')
        ->and(workdayFieldsVisibility('nyota', 'gender'))->toBe('visible')
        ->and(workdayFieldsVisibility('nyota', 'nickname'))->toBe('visible')
        ->and(workdayFieldsVisibility('nyota', 'bio'))->toBe('visible');

    test()->actingAs(workdayFieldsAdministrator());

    expect(workdayFieldsVisibility('nyota', 'uams_workday_id'))->toBe('visible')
        ->and(workdayFieldsVisibility('nyota', 'uams_worker_query'))->toBe('visible')
        ->and(workdayFieldsVisibility('nyota', 'person_name'))->toBe('visible');
});

it('greys out a trainee field for an editor only on the entry Workday supplied it for', function (): void {
    workdayFieldsPerson('nyota', ['resident_query' => true, 'workday_supplied' => ['resident_query']]);
    workdayFieldsPerson('hikaru', ['resident_query' => false]);

    test()->actingAs(workdayFieldsEditor());

    expect(workdayFieldsVisibility('nyota', 'resident_query'))->toBe('read_only')
        ->and(workdayFieldsVisibility('nyota', 'fellow_query'))->toBe('visible');

    // One blueprint object serves every entry of a request, so resolve both in one
    Blink::flush();
    $nyota = workdayFieldsFieldVisibility(workdayFieldsFind('nyota'), 'resident_query');
    $hikaru = workdayFieldsFieldVisibility(workdayFieldsFind('hikaru'), 'resident_query');

    expect($nyota)->toBe('read_only')
        ->and($hikaru)->toBe('visible');
});

it('keeps the Workday fields of a draft when an editor saves it', function (): void {
    workdayFieldsPerson('nyota');

    test()->actingAs(workdayFieldsEditor());

    test()->patchJson(workdayFieldsUpdateUrl('nyota'), workdayFieldsChange('nyota'))->assertOk();

    $nyota = workdayFieldsFind('nyota');

    expect($nyota->get('bio'))->toBe('Changed bio')
        ->and($nyota->get('gender'))->toBe('male')
        ->and($nyota->get('nickname'))->toBe('Bones')
        ->and($nyota->get('person_name'))->toBe(['first_name' => 'Nyota', 'last_name' => 'Uhura'])
        ->and($nyota->get('uams_worker_query'))->toBeTrue()
        ->and($nyota->get('uams_worker_subtype'))->toBe('employee')
        ->and($nyota->get('uams_employee_subtype'))->toBe('regular')
        ->and($nyota->get('uams_workday_id'))->toBe(['workday_universal_id' => 'WD-100', 'workday_employee_id' => 'E-100']);
});

it('keeps the Workday fields of a published entry in the working copy an editor saves', function (): void {
    workdayFieldsPerson('nyota', published: true);

    test()->actingAs(workdayFieldsEditor());

    test()->patchJson(workdayFieldsUpdateUrl('nyota'), workdayFieldsChange('nyota'))->assertOk();

    $nyota = workdayFieldsFind('nyota');
    expect($nyota->hasWorkingCopy())->toBeTrue();

    $copy = $nyota->fromWorkingCopy();
    Assert::assertInstanceOf(EntryItem::class, $copy);

    expect($copy->get('bio'))->toBe('Changed bio')
        ->and($copy->get('gender'))->toBe('male')
        ->and($copy->get('person_name'))->toBe(['first_name' => 'Nyota', 'last_name' => 'Uhura'])
        ->and($copy->get('uams_worker_query'))->toBeTrue()
        ->and($copy->get('uams_workday_id'))->toBe(['workday_universal_id' => 'WD-100', 'workday_employee_id' => 'E-100']);
});

it('lets an editor answer a trainee field Workday did not supply and keeps one it did', function (): void {
    workdayFieldsPerson('nyota', ['resident_query' => false, 'fellow_query' => false, 'workday_supplied' => ['resident_query']]);

    test()->actingAs(workdayFieldsEditor());

    test()->patchJson(workdayFieldsUpdateUrl('nyota'), workdayFieldsChange('nyota'))->assertOk();

    $nyota = workdayFieldsFind('nyota');

    expect($nyota->get('resident_query'))->toBeFalse()
        ->and($nyota->get('fellow_query'))->toBeTrue()
        ->and($nyota->get('workday_supplied'))->toBe(['resident_query']);
});

it('stops an editor giving a Workday field its first value', function (): void {
    workdayFieldsPerson('nyota', ['uams_workday_id' => null]);

    /** @var EntryItem $nyota */
    $nyota = workdayFieldsFind('nyota');
    $nyota->remove('uams_workday_id');
    $nyota->saveQuietly();

    test()->actingAs(workdayFieldsEditor());

    test()->patchJson(workdayFieldsUpdateUrl('nyota'), workdayFieldsChange('nyota'))->assertOk();

    expect(workdayFieldsFind('nyota')->has('uams_workday_id'))->toBeFalse();
});

it('keeps the Workday fields of an entry an editor creates empty', function (): void {
    test()->actingAs(workdayFieldsEditor());

    $url = cp_route('collections.entries.store', ['people', 'default']);
    Assert::assertIsString($url);

    test()->postJson($url, workdayFieldsChange('montgomery'))->assertSuccessful();

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'people');
    $query->where('slug', 'montgomery');

    $created = $query->first();
    Assert::assertInstanceOf(EntryItem::class, $created);

    expect($created->get('bio'))->toBe('Changed bio')
        ->and($created->has('uams_workday_id'))->toBeFalse()
        ->and($created->has('uams_worker_query'))->toBeFalse()
        ->and($created->has('person_name'))->toBeFalse()
        ->and($created->get('resident_query'))->toBeTrue();
});

it('lets an administrator change the Workday fields', function (): void {
    workdayFieldsPerson('nyota', ['resident_query' => false, 'workday_supplied' => ['resident_query']]);

    test()->actingAs(workdayFieldsAdministrator());

    test()->patchJson(workdayFieldsUpdateUrl('nyota'), workdayFieldsChange('nyota'))->assertOk();

    $nyota = workdayFieldsFind('nyota');

    expect($nyota->get('uams_worker_query'))->toBeFalse()
        ->and($nyota->get('uams_workday_id'))->toBe(['workday_universal_id' => 'WD-999', 'workday_employee_id' => 'E-999'])
        ->and($nyota->get('person_name'))->toBe(['first_name' => 'Changed', 'last_name' => 'Name'])
        ->and($nyota->get('resident_query'))->toBeTrue();
});

it('holds nothing when no user is signed in, as for the Workday sync', function (): void {
    workdayFieldsPerson('nyota');

    $nyota = workdayFieldsFind('nyota');
    $nyota->set('uams_workday_id', ['workday_universal_id' => 'WD-200']);
    $nyota->set('workday_supplied', ['fellow_query']);
    $nyota->save();

    $nyota = workdayFieldsFind('nyota');

    expect($nyota->get('uams_workday_id'))->toBe(['workday_universal_id' => 'WD-200'])
        ->and($nyota->get('workday_supplied'))->toBe(['fellow_query']);
});

it('holds nothing on another collection', function (): void {
    $pages = CollectionFacade::make('pages');
    $pages->sites(['default']);
    $pages->save();

    $pageBlueprint = Blueprint::makeFromFields(['title' => ['type' => 'text'], 'uams_worker_query' => ['type' => 'toggle']]);
    $pageBlueprint->setHandle('page');
    $pageBlueprint->setNamespace('collections.pages');
    $pageBlueprint->save();

    test()->actingAs(workdayFieldsEditor());

    /** @var EntryItem $page */
    $page = Entry::make();
    $page->collection('pages');
    $page->locale('default');
    $page->slug('bridge');
    $page->data(['title' => 'Bridge', 'uams_worker_query' => true]);
    $page->save();

    $stored = Entry::find($page->id());
    Assert::assertInstanceOf(EntryItem::class, $stored);

    expect($stored->get('uams_worker_query'))->toBeTrue()
        ->and(workdayFieldsFieldVisibility($page, 'uams_worker_query'))->toBe('visible');
});

it('still locks the later Workday fields on a blueprint that lacks an earlier one', function (): void {
    // `person_name` is the first handle the listener walks, so a blueprint without
    // it is where stopping at the first missing field would leave every later
    // Workday field editable (UAMS-Web/uams-statamic-archive#3244).
    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'workday_gender' => ['type' => 'text'],
        'uams_workday_id' => ['type' => 'group', 'fields' => [
            ['handle' => 'workday_universal_id', 'field' => ['type' => 'text']],
        ]],
        'resident_query' => ['type' => 'toggle'],
    ]);
    $blueprint->setHandle('person');
    $blueprint->setNamespace('collections.people');
    $blueprint->save();

    workdayFieldsPerson('pavel', ['resident_query' => true, 'workday_supplied' => ['resident_query']]);

    test()->actingAs(workdayFieldsEditor());

    expect(workdayFieldsVisibility('pavel', 'uams_workday_id'))->toBe('read_only')
        ->and(workdayFieldsVisibility('pavel', 'workday_gender'))->toBe('read_only')
        ->and(workdayFieldsVisibility('pavel', 'resident_query'))->toBe('read_only');
});

it('leaves alone a blueprint that is not a Statamic blueprint, whatever its namespace', function (): void {
    // `EntryBlueprintFound::$blueprint` is untyped, so the listener checks the
    // class before it reads anything. A look-alike in the `people` namespace must
    // not have a single field read from it (UAMS-Web/uams-statamic-archive#3244).
    $lookAlike = new class
    {
        /** @var list<string> */
        public array $fieldsRead = [];

        public function namespace(): string
        {
            return 'collections.people';
        }

        public function field(string $handle): null
        {
            $this->fieldsRead[] = $handle;

            return null;
        }
    };

    test()->actingAs(workdayFieldsEditor());

    app(MakeWorkdayFieldsReadOnly::class)->handle(new EntryBlueprintFound($lookAlike));

    expect($lookAlike->fieldsRead)->toBe([]);
});

/**
 * A revision double with the given key and attributes, failing the test if its
 * attributes are ever written back.
 *
 * @param  mixed  $attributes  What `attributes()` returns
 */
function workdayFieldsRevision(mixed $key, mixed $attributes = []): Revision
{
    $revision = Mockery::mock(Revision::class);
    $revision->shouldReceive('key')->andReturn($key);
    $revision->shouldReceive('attributes')->withNoArgs()->andReturn($attributes);
    $revision->shouldReceive('attributes')->with(Mockery::any())->never();

    return mustBe(Revision::class, $revision, 'a revision');
}

it('ignores a revision event that carries no revision', function (): void {
    test()->actingAs(workdayFieldsEditor());

    // The event's revision is untyped; anything else is a no-op, not an error.
    expect(throwableFrom(fn () => app(RestoreWorkdayManagedFields::class)->handleRevisionSaving(new RevisionSaving(null))))
        ->toBeNull();
});

it('leaves alone the revision of an entry in another collection', function (): void {
    test()->actingAs(workdayFieldsEditor());
    Entry::shouldReceive('find')->never();

    $revision = workdayFieldsRevision('collections/pages/nyota', ['id' => 'nyota', 'data' => ['uams_worker_query' => false]]);

    app(RestoreWorkdayManagedFields::class)->handleRevisionSaving(new RevisionSaving($revision));
});

it('leaves alone the revision of a collection whose handle only begins with people', function (): void {
    test()->actingAs(workdayFieldsEditor());
    Entry::shouldReceive('find')->never();

    $revision = workdayFieldsRevision('collections/people_archive/nyota', ['id' => 'nyota', 'data' => ['uams_worker_query' => false]]);

    app(RestoreWorkdayManagedFields::class)->handleRevisionSaving(new RevisionSaving($revision));
});

it('leaves alone a people revision when no user is signed in', function (): void {
    Entry::shouldReceive('find')->never();

    $revision = workdayFieldsRevision('collections/people/nyota', ['id' => 'nyota', 'data' => ['uams_worker_query' => false]]);

    app(RestoreWorkdayManagedFields::class)->handleRevisionSaving(new RevisionSaving($revision));
});

it('leaves alone a people revision that carries no data', function (): void {
    test()->actingAs(workdayFieldsEditor());
    Entry::shouldReceive('find')->never();

    $revision = workdayFieldsRevision('collections/people/nyota', ['id' => 'nyota']);

    app(RestoreWorkdayManagedFields::class)->handleRevisionSaving(new RevisionSaving($revision));
});

it('leaves alone a people revision whose entry no longer exists', function (): void {
    test()->actingAs(workdayFieldsEditor());

    $revision = workdayFieldsRevision('collections/people/gone', ['id' => 'gone', 'data' => ['uams_worker_query' => false]]);

    expect(throwableFrom(fn () => app(RestoreWorkdayManagedFields::class)->handleRevisionSaving(new RevisionSaving($revision))))
        ->toBeNull();
});
