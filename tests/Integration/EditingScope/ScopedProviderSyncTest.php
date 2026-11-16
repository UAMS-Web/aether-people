<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\Role as StatamicRole;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Auth\UserGroup as StatamicUserGroup;
use Statamic\Contracts\Auth\RoleRepository as RoleRepositoryContract;
use Statamic\Contracts\Auth\UserGroupRepository as UserGroupRepositoryContract;
use Statamic\Contracts\Entries\EntryRepository as EntryRepositoryContract;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Facades\UserGroup;
use Statamic\Facades\YAML;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;
use Symfony\Component\HttpFoundation\Response;
use UAMSWeb\Aether\People\EditingScope\EnforcePeopleScopeOnProviderSync;
use UAMSWeb\Aether\People\Tests\Support\FreshEntryRepository;

/**
 * A scoped editor's save that would change a `people` entry outside their
 * organizations through a relationship synced into `people` (UAMS-Web/uams-statamic-archive#3223), through
 * the Control Panel's own entry update endpoint.
 *
 * The Stillat relationships addon writes each person added to or removed from a
 * synced field (a location's `physician_locations`, `clinical_providers` on
 * expertise, conditions, services and resources, and a recognition list's
 * `members`) with `saveQuietly()`, so the `people` save guard never sees it. A scoped editor may add or remove only people in scope;
 * people already listed and left alone do not block the save. Administrators are
 * unaffected.
 *
 * The scoped user holds the shipped `people_editor` role plus a throwaway role
 * that may edit the synced collections, since no shipped role does; the role and group files
 * are repointed at a throwaway directory so nothing tracked is written.
 */
const SCOPED_SYNC_ORG_IN = 'org-sync-cardiology';

const SCOPED_SYNC_ORG_OUT = 'org-sync-dermatology';

const SCOPED_SYNC_REFUSAL = 'they are not linked to an organization you edit for';

/**
 * A `people_editor` who may also edit locations, in a group mapped to the
 * in-scope organization.
 */
function scopedSyncEditor(): FileStatamicUser
{
    $role = Role::make('location_editor');
    Assert::assertInstanceOf(StatamicRole::class, $role);

    $role->title('Location editor');
    $role->permissions(array_merge(...array_map(
        fn (string $collection): array => ["view {$collection} entries", "edit {$collection} entries"],
        array_keys(EnforcePeopleScopeOnProviderSync::syncedFields()),
    )));
    $role->save();

    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('kirk-scoped-sync@example.test');
    $user->assignRole('people_editor');
    $user->assignRole('location_editor');
    $user->addToGroup('sync_cardiology_editors');
    $user->save();

    return $user;
}

/**
 * A super user, standing for the administrators the decision leaves unchanged.
 */
function scopedSyncAdministrator(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('spock-scoped-sync@example.test');
    $user->makeSuper();
    $user->save();

    return $user;
}

/**
 * Persist an entry without firing save events.
 *
 * @param  array<string, mixed>  $data
 */
function scopedSyncEntry(string $collection, string $id, array $data): EntryItem
{
    /** @var EntryItem $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->locale('default');
    $entry->id($id);
    $entry->slug($id);
    $entry->data(['title' => ucfirst($id), ...$data]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * Save the `cl-main` location through the CP with the given providers.
 *
 * @param  list<string>  $providers
 * @return TestResponse<Response>
 */
function scopedSyncSaveLocation(array $providers, string $phone = '501-555-0100'): TestResponse
{
    return scopedSyncSave('clinical_locations', 'cl-main', [
        'phone' => $phone,
        'physician_locations' => $providers,
    ]);
}

/**
 * Save an entry through the CP's entry update endpoint.
 *
 * @param  array<string, mixed>  $data
 * @return TestResponse<Response>
 */
function scopedSyncSave(string $collection, string $id, array $data): TestResponse
{
    $url = cp_route('collections.entries.update', [$collection, $id]);
    Assert::assertIsString($url);

    return test()->patchJson($url, ['title' => ucfirst($id), 'slug' => $id, ...$data]);
}

/**
 * The stored value of a list field on an entry.
 *
 * @return list<string>
 */
function scopedSyncIds(string $id, string $field): array
{
    /** @var EntryItem $entry */
    $entry = Entry::find($id);

    /** @var list<string>|null $value */
    $value = $entry->get($field);

    return $value ?? [];
}

beforeEach(function (): void {
    test()->withoutMiddleware(PreventRequestForgery::class);
    test()->withoutVite();
    config(['statamic.editions.pro' => true, 'app.debug' => true]);

    $directory = storage_path('framework/testing/scoped-provider-sync/'.uniqid('', true));
    File::ensureDirectoryExists($directory);
    $this->scopedSyncDirectory = $directory;

    // Keep every saved collection, entry and user out of the tracked fixtures directory, in a directory of this test's own
    isolateStacheIn($directory.'/stache');

    // Hand back a fresh copy from find(), as the site's repository does, so a save is compared with the stored entry
    app()->singleton(EntryRepositoryContract::class, fn (): FreshEntryRepository => new FreshEntryRepository(app('stache')));
    Entry::clearResolvedInstance(EntryRepositoryContract::class);

    // Save fixture blueprints into the throwaway directory, never over the add-on's shipped ones
    Blueprint::setDirectory($directory.'/blueprints');
    Blueprint::addNamespace('collections.people', $directory.'/blueprints/collections/people');

    // Ship the tracked roles, so the scoped role under test is the role as shipped
    File::copy(__DIR__.'/../../Fixtures/HostUsers/roles.yaml', $directory.'/roles.yaml');
    app(RoleRepositoryContract::class)->path($directory.'/roles.yaml');

    app(UserGroupRepositoryContract::class)->path($directory.'/groups.yaml');
    /** @var StatamicUserGroup $group */
    $group = UserGroup::make();
    $group->handle('sync_cardiology_editors');
    $group->title('Cardiology editors');
    $group->data(['organizations' => [SCOPED_SYNC_ORG_IN]]);
    $group->save();

    $organizations = CollectionFacade::make('organizations');
    $organizations->title('Organizations');
    $organizations->sites(['default']);
    $organizations->save();

    foreach ([SCOPED_SYNC_ORG_IN => 'Cardiology', SCOPED_SYNC_ORG_OUT => 'Dermatology'] as $id => $title) {
        scopedSyncEntry('organizations', $id, ['title' => $title]);
    }

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->sites(['default']);
    $people->save();

    $peopleBlueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'service_line' => ['type' => 'entries', 'collections' => ['organizations']],
        'clinical_locations' => ['type' => 'entries', 'collections' => ['clinical_locations']],
        'expertise' => ['type' => 'entries', 'collections' => ['clinical_expertise']],
        'conditions' => ['type' => 'entries', 'collections' => ['clinical_conditions']],
        'treatments' => ['type' => 'entries', 'collections' => ['clinical_services']],
        'clinical_resources' => ['type' => 'entries', 'collections' => ['clinical_resources']],
        'recognition_lists' => ['type' => 'entries', 'collections' => ['recognition_lists']],
    ]);
    $peopleBlueprint->setHandle('person');
    $peopleBlueprint->setNamespace('collections.people');
    $peopleBlueprint->save();

    $locations = CollectionFacade::make('clinical_locations');
    $locations->title('Locations');
    $locations->sites(['default']);
    $locations->save();

    $locationBlueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'phone' => ['type' => 'text'],
        'physician_locations' => ['type' => 'entries', 'collections' => ['people']],
    ]);
    $locationBlueprint->setHandle('location');
    $locationBlueprint->setNamespace('collections.clinical_locations');
    $locationBlueprint->save();

    // The other collections whose people field syncs into people
    foreach (EnforcePeopleScopeOnProviderSync::syncedFields() as $handle => $field) {
        if ($handle === 'clinical_locations') {
            continue;
        }

        $collection = CollectionFacade::make($handle);
        $collection->title(ucfirst($handle));
        $collection->sites(['default']);
        $collection->save();

        $blueprint = Blueprint::makeFromFields([
            'title' => ['type' => 'text'],
            'summary' => ['type' => 'text'],
            $field => ['type' => 'entries', 'collections' => ['people']],
        ]);
        $blueprint->setHandle('sync_item');
        $blueprint->setNamespace('collections.'.$handle);
        $blueprint->save();
    }

    // Two people in scope and two out of scope; chekov is already listed at the
    // location, as another organization's doctor at a shared clinic would be
    scopedSyncEntry('people', 'uhura', ['service_line' => [SCOPED_SYNC_ORG_IN]]);
    scopedSyncEntry('people', 'sulu', ['service_line' => [SCOPED_SYNC_ORG_IN], 'clinical_locations' => ['cl-main']]);
    scopedSyncEntry('people', 'chekov', ['service_line' => [SCOPED_SYNC_ORG_OUT], 'clinical_locations' => ['cl-main']]);
    scopedSyncEntry('people', 'pavel', ['service_line' => [SCOPED_SYNC_ORG_OUT]]);
    scopedSyncEntry('clinical_locations', 'cl-main', [
        'phone' => '501-555-0100',
        'physician_locations' => ['sulu', 'chekov'],
    ]);
});

afterEach(function (): void {
    File::deleteDirectory((string) $this->scopedSyncDirectory);
});

it('refuses a scoped editor adding an out-of-scope person, naming them, and writes nothing', function (): void {
    test()->actingAs(scopedSyncEditor());

    scopedSyncSaveLocation(['sulu', 'chekov', 'pavel'], '501-555-0199')
        ->assertServerError()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Pavel [pavel]')
            && str_contains($message, SCOPED_SYNC_REFUSAL));

    /** @var EntryItem $location */
    $location = Entry::find('cl-main');

    expect(scopedSyncIds('cl-main', 'physician_locations'))->toBe(['sulu', 'chekov'])
        ->and($location->get('phone'))->toBe('501-555-0100');

    // A refused save leaves the next save in the process working
    scopedSyncSaveLocation(['sulu', 'chekov', 'uhura'])->assertOk();
});

it('refuses a scoped editor removing an out-of-scope person, naming them, and writes nothing', function (): void {
    test()->actingAs(scopedSyncEditor());

    scopedSyncSaveLocation(['sulu'], '501-555-0199')
        ->assertServerError()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Chekov [chekov]')
            && str_contains($message, SCOPED_SYNC_REFUSAL));

    /** @var EntryItem $location */
    $location = Entry::find('cl-main');

    expect(scopedSyncIds('cl-main', 'physician_locations'))->toBe(['sulu', 'chekov'])
        ->and($location->get('phone'))->toBe('501-555-0100');
});

it('lets a scoped editor add and remove people in scope, writing each person as before', function (): void {
    test()->actingAs(scopedSyncEditor());

    scopedSyncSaveLocation(['chekov', 'uhura'])->assertOk();

    expect(scopedSyncIds('cl-main', 'physician_locations'))->toBe(['chekov', 'uhura']);
});

it('lets a scoped editor save a location that keeps an out-of-scope person listed', function (): void {
    test()->actingAs(scopedSyncEditor());

    scopedSyncSaveLocation(['sulu', 'chekov'], '501-555-0199')->assertOk();

    /** @var EntryItem $location */
    $location = Entry::find('cl-main');

    expect($location->get('phone'))->toBe('501-555-0199')
        ->and(scopedSyncIds('cl-main', 'physician_locations'))->toBe(['sulu', 'chekov']);
});

it('leaves an administrator free to add and remove out-of-scope people', function (): void {
    test()->actingAs(scopedSyncAdministrator());

    scopedSyncSaveLocation(['sulu', 'pavel'])->assertOk();

    expect(scopedSyncIds('cl-main', 'physician_locations'))->toBe(['sulu', 'pavel']);
});

dataset('scoped sync other collections', [
    'expertise' => ['clinical_expertise', 'clinical_providers', 'expertise'],
    'conditions' => ['clinical_conditions', 'clinical_providers', 'conditions'],
    'services' => ['clinical_services', 'clinical_providers', 'treatments'],
    'resources' => ['clinical_resources', 'clinical_providers', 'clinical_resources'],
    'recognition lists' => ['recognition_lists', 'members', 'recognition_lists'],
]);

it('guards the people field of the other synced collections the same way', function (string $collection, string $field, string $reciprocal): void {
    scopedSyncEntry($collection, 'item', ['summary' => 'Before', $field => ['chekov']]);
    scopedSyncEntry('people', 'chekov', ['service_line' => [SCOPED_SYNC_ORG_OUT], $reciprocal => ['item']]);

    test()->actingAs(scopedSyncEditor());

    // Adding an out-of-scope person is refused, naming them, and writes nothing
    scopedSyncSave($collection, 'item', ['summary' => 'After', $field => ['chekov', 'pavel']])
        ->assertServerError()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Pavel [pavel]')
            && str_contains($message, SCOPED_SYNC_REFUSAL));

    expect(scopedSyncIds('item', $field))->toBe(['chekov']);

    // Removing one is refused too
    scopedSyncSave($collection, 'item', ['summary' => 'After', $field => []])
        ->assertServerError()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Chekov [chekov]'));

    expect(scopedSyncIds('item', $field))->toBe(['chekov']);

    // Adding a person in scope while keeping the listed one is written through
    scopedSyncSave($collection, 'item', ['summary' => 'After', $field => ['chekov', 'uhura']])
        ->assertOk();

    /** @var EntryItem $item */
    $item = Entry::find('item');

    expect($item->get('summary'))->toBe('After')
        ->and(scopedSyncIds('item', $field))->toBe(['chekov', 'uhura']);
})->with('scoped sync other collections');

it('guards the field each synced collection writes into people', function (): void {
    expect(EnforcePeopleScopeOnProviderSync::syncedFields())->toBe([
        'clinical_locations' => 'physician_locations',
        'clinical_expertise' => 'clinical_providers',
        'clinical_conditions' => 'clinical_providers',
        'clinical_services' => 'clinical_providers',
        'clinical_resources' => 'clinical_providers',
        'recognition_lists' => 'members',
    ]);
});

it('lets a scoped editor keep an id that names no entry listed', function (): void {
    test()->actingAs(scopedSyncEditor());

    scopedSyncSaveLocation(['sulu', 'chekov', 'no-such-person'])->assertOk();

    expect(scopedSyncIds('cl-main', 'physician_locations'))->toBe(['sulu', 'chekov', 'no-such-person']);

    // An id that names no entry does not end the check of the people after it
    scopedSyncSaveLocation(['sulu', 'chekov', 'no-such-person', 'other-missing', 'pavel'])
        ->assertServerError()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Pavel [pavel]'));
});

it('names a person whose title is empty as Untitled', function (): void {
    scopedSyncEntry('people', 'pavel', ['title' => '', 'service_line' => [SCOPED_SYNC_ORG_OUT]]);

    test()->actingAs(scopedSyncEditor());

    scopedSyncSaveLocation(['sulu', 'chekov', 'pavel'])
        ->assertServerError()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Untitled [pavel]'));
});

it('reads a stored single id as one listed person', function (): void {
    scopedSyncEntry('clinical_locations', 'cl-main', ['phone' => '501-555-0100', 'physician_locations' => 'chekov']);

    test()->actingAs(scopedSyncEditor());

    scopedSyncSaveLocation([])
        ->assertServerError()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Chekov [chekov]'));
});

it('checks every listed person on a new entry given an id, through the event dispatcher', function (): void {
    test()->actingAs(scopedSyncEditor());

    /** @var EntryItem $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->locale('default');
    $location->id('cl-new');
    $location->slug('cl-new');
    $location->data(['title' => 'New clinic', 'physician_locations' => ['pavel']]);

    // The id is set before the save, so the repository holds no stored copy
    expect(fn () => $location->save())->toThrow(Exception::class, SCOPED_SYNC_REFUSAL)
        ->and(Entry::find('cl-new'))->toBeNull();

    $location->set('physician_locations', ['uhura']);
    expect(throwableFrom(fn () => $location->save()))->toBeNull();
});

it('ignores a listed value that is not an id', function (): void {
    test()->actingAs(scopedSyncEditor());

    /** @var EntryItem $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->locale('default');
    $location->id('cl-main');
    $location->data(['title' => 'Cl-main', 'physician_locations' => ['sulu', 'chekov', ['nested']]]);

    $listener = app(EnforcePeopleScopeOnProviderSync::class);

    expect(throwableFrom(fn () => $listener->handle(new EntrySaving($location))))->toBeNull()
        ->and(scopedSyncIds('cl-main', 'physician_locations'))->toBe(['sulu', 'chekov']);
});

it('skips an event that carries something other than an entry', function (): void {
    test()->actingAs(scopedSyncEditor());

    $listener = app(EnforcePeopleScopeOnProviderSync::class);

    expect(throwableFrom(fn () => $listener->handle(new EntrySaving(new stdClass))))->toBeNull()
        ->and(scopedSyncIds('cl-main', 'physician_locations'))->toBe(['sulu', 'chekov']);
});

it('leaves alone an entry in a collection that syncs nothing into people', function (): void {
    test()->actingAs(scopedSyncEditor());

    // `organizations` is set up here and syncs no field into people
    /** @var EntryItem $page */
    $page = Entry::make();
    $page->collection('organizations');
    $page->locale('default');
    $page->id('org-main');
    $page->data(['title' => 'Organization', 'physician_locations' => ['pavel']]);

    $listener = app(EnforcePeopleScopeOnProviderSync::class);

    // An unsynced collection names no field, so nothing is read or refused
    expect(throwableFrom(fn () => $listener->handle(new EntrySaving($page))))->toBeNull();
});

it('looks nothing up for the stored copy of an entry with no id', function (): void {
    test()->actingAs(scopedSyncEditor());

    // A lookup of a null id would also find nothing, so only the missing call shows
    // that an entry with no id is treated as new without one
    Entry::partialMock()->shouldReceive('find')->with(null)->never();

    /** @var EntryItem $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->locale('default');
    $location->data(['title' => 'Unsaved clinic', 'physician_locations' => ['pavel']]);

    $listener = app(EnforcePeopleScopeOnProviderSync::class);

    expect(fn () => $listener->handle(new EntrySaving($location)))->toThrow(Exception::class, SCOPED_SYNC_REFUSAL);
});

/**
 * Put Statamic's own entry repository back, whose `find()` returns the stored instance itself
 * rather than a copy, so a save that merged new values into the entry has already changed what
 * `find()` returns.
 */
function scopedSyncUsePlainRepository(): void
{
    app()->singleton(EntryRepositoryContract::class, fn (): StacheEntryRepository => new StacheEntryRepository(app('stache')));
    Entry::clearResolvedInstance(EntryRepositoryContract::class);
}

/**
 * The list a field holds in the entry's stored file, which a refused save must leave as it was.
 *
 * Under Statamic's own repository the stored instance is the one a save merged new values into, so
 * `find()` still shows them after a refusal; the file is what was written.
 *
 * @return list<string>
 */
function scopedSyncFileIds(string $id, string $field): array
{
    /** @var EntryItem $entry */
    $entry = Entry::find($id);
    $path = $entry->initialPath();
    Assert::assertIsString($path);

    /** @var list<string>|null $value */
    $value = YAML::file($path)->parse()[$field] ?? null;

    return $value ?? [];
}

it('lets a scoped editor keep an out-of-scope person listed under a repository that returns the stored instance', function (): void {
    scopedSyncUsePlainRepository();
    test()->actingAs(scopedSyncEditor());

    scopedSyncSaveLocation(['sulu', 'chekov'], '501-555-0199')->assertOk();

    /** @var EntryItem $location */
    $location = Entry::find('cl-main');

    expect($location->get('phone'))->toBe('501-555-0199')
        ->and(scopedSyncIds('cl-main', 'physician_locations'))->toBe(['sulu', 'chekov']);
});

it('refuses a scoped editor adding an out-of-scope person under a repository that returns the stored instance', function (): void {
    scopedSyncUsePlainRepository();
    test()->actingAs(scopedSyncEditor());

    scopedSyncSaveLocation(['sulu', 'chekov', 'pavel'], '501-555-0199')
        ->assertServerError()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Pavel [pavel]')
            && ! str_contains($message, 'Chekov'));

    expect(scopedSyncFileIds('cl-main', 'physician_locations'))->toBe(['sulu', 'chekov']);
});

it('refuses a scoped editor removing an out-of-scope person under a repository that returns the stored instance', function (): void {
    scopedSyncUsePlainRepository();
    test()->actingAs(scopedSyncEditor());

    scopedSyncSaveLocation(['sulu'], '501-555-0199')
        ->assertServerError()
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Chekov [chekov]'));

    expect(scopedSyncFileIds('cl-main', 'physician_locations'))->toBe(['sulu', 'chekov']);
});
