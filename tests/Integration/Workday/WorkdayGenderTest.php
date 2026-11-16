<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PHPUnit\Framework\Assert;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Auth\UserGroup as StatamicUserGroup;
use Statamic\Contracts\Auth\RoleRepository as RoleRepositoryContract;
use Statamic\Contracts\Auth\UserGroupRepository as UserGroupRepositoryContract;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Facades\UserGroup;
use Statamic\Facades\YAML;
use Statamic\Fields\Blueprint as BlueprintItem;
use Statamic\Fields\Field;
use UAMSWeb\Aether\People\Support\WorkdayGender;
use UAMSWeb\Aether\People\Widgets\WorkdayGenderOverrides;
use UAMSWeb\Aether\People\Workday\MakeWorkdayFieldsReadOnly;

/**
 * A person's gender override beside the read-only `workday_gender` (UAMS-Web/uams-statamic-archive#3187,
 * decided on UAMS-Web/uams-statamic-archive#3131, UAMS-Web/uams-statamic-archive#3132 and UAMS-Web/uams-statamic-archive#3133).
 *
 * The sync rule copies Workday's value into `gender` only while `gender` is
 * empty or still equals the previous Workday value, and otherwise keeps the
 * override and flags the entry. The flag is listed on a dashboard widget and
 * cleared by confirming the override or taking Workday's value. `workday_gender`
 * is read-only and carries an "Overridden" marker when the two differ.
 *
 * @command  vendor/bin/pest tests/Integration/Workday/WorkdayGenderTest.php
 */
const WORKDAY_GENDER_ORG = 'org-workday-gender';

const WORKDAY_GENDER_OTHER_ORG = 'org-workday-gender-other';

/**
 * A `people_editor` in a group mapped to the test organization.
 */
function workdayGenderEditor(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('nyota-workday-gender@example.test');
    $user->assignRole('people_editor');
    $user->addToGroup('workday_gender_editors');
    $user->save();

    return $user;
}

/**
 * A super user, standing for the administrators the scope leaves unscoped.
 */
function workdayGenderAdministrator(): FileStatamicUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);

    $user->id((string) Str::uuid());
    $user->email('christopher-workday-gender@example.test');
    $user->makeSuper();
    $user->save();

    return $user;
}

/**
 * Persist a `people` entry linked to an organization.
 *
 * @param  array<string, mixed>  $data
 */
function workdayGenderPerson(string $id, array $data = [], string $organization = WORKDAY_GENDER_ORG): EntryItem
{
    /** @var EntryItem $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale('default');
    $entry->id($id);
    $entry->slug($id);
    $entry->data([
        'title' => ucfirst($id),
        'service_line' => [$organization],
        ...$data,
    ]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * A fresh read of a `people` entry.
 */
function workdayGenderFind(string $id): EntryItem
{
    $entry = Entry::find($id);
    Assert::assertInstanceOf(EntryItem::class, $entry);

    return $entry;
}

/**
 * A `people` entry in memory, with the data given, for the sync rule alone.
 *
 * @param  array<string, mixed>  $data
 */
function workdayGenderEntry(array $data): EntryItem
{
    /** @var EntryItem $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->data($data);

    return $entry;
}

/**
 * The config of `workday_gender` on an entry's blueprint, resolved afresh for
 * the signed-in user.
 *
 * @return array<array-key, mixed>
 */
function workdayGenderFieldConfig(string $id): array
{
    Blink::flush();

    $blueprint = workdayGenderFind($id)->blueprint();
    Assert::assertInstanceOf(BlueprintItem::class, $blueprint);

    $field = $blueprint->field(WorkdayGender::WORKDAY_FIELD);
    Assert::assertInstanceOf(Field::class, $field);

    return $field->config();
}

/**
 * Render the widget and return its view data.
 *
 * @return array<string, mixed>
 */
function workdayGenderWidgetData(): array
{
    $view = (new WorkdayGenderOverrides)->html();
    Assert::assertInstanceOf(View::class, $view);

    /** @var array<string, mixed> $data */
    $data = $view->getData();

    return $data;
}

/**
 * The ids of the rows the widget lists for the signed-in user.
 *
 * @return list<string>
 */
function workdayGenderWidgetIds(): array
{
    $rows = workdayGenderWidgetData()['rows'];
    Assert::assertIsArray($rows);

    return array_values(array_map(static function (mixed $row): string {
        Assert::assertIsArray($row);
        Assert::assertIsString($row['id']);

        return $row['id'];
    }, $rows));
}

/**
 * A Control Panel route for clearing a flag.
 */
function workdayGenderActionUrl(string $action, string $id): string
{
    $url = cp_route('people-workday-gender.'.$action, ['id' => $id]);
    Assert::assertIsString($url);

    return $url;
}

beforeEach(function (): void {
    test()->withoutMiddleware(PreventRequestForgery::class);
    test()->withoutVite();

    $directory = storage_path('framework/testing/workday-gender/'.uniqid('', true));
    File::ensureDirectoryExists($directory);
    $this->workdayGenderDirectory = $directory;

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
    $group->handle('workday_gender_editors');
    $group->title('Workday gender editors');
    $group->data(['organizations' => [WORKDAY_GENDER_ORG]]);
    $group->save();

    $organizations = CollectionFacade::make('organizations');
    $organizations->title('Organizations');
    $organizations->sites(['default']);
    $organizations->save();

    foreach ([WORKDAY_GENDER_ORG => 'Cardiology', WORKDAY_GENDER_OTHER_ORG => 'Neurology'] as $id => $title) {
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

    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'bio' => ['type' => 'text'],
        'gender' => ['type' => 'text'],
        'workday_gender' => ['type' => 'text', 'visibility' => 'read_only', 'instructions' => 'The gender Workday last sent.'],
        'service_line' => ['type' => 'entries', 'collections' => ['organizations']],
    ]);
    $blueprint->setHandle('person');
    $blueprint->setNamespace('collections.people');
    $blueprint->save();
});

afterEach(function (): void {
    File::deleteDirectory((string) $this->workdayGenderDirectory);
});

it('ships workday_gender read-only on the person blueprint, directly below gender', function (): void {
    $fieldset = YAML::file(__DIR__.'/../../../resources/fieldsets/uamsweb_common_people.yaml')->parse();
    Assert::assertIsArray($fieldset['fields']);
    $definitions = array_column($fieldset['fields'], 'field', 'handle');

    expect($definitions['workday_gender'])->toMatchArray(['type' => 'text', 'visibility' => 'read_only', 'localizable' => false]);

    // In the two-column Personal Details row, workday_gender follows pronouns, so it sits under gender
    $blueprint = YAML::file(__DIR__.'/../../../resources/collection-blueprints/people/person.yaml')->parse();
    $handles = [];
    array_walk_recursive($blueprint, static function (mixed $value, string|int $key) use (&$handles): void {
        if ($key === 'handle' && \in_array($value, ['gender', 'pronouns', 'workday_gender', 'language'], true)) {
            $handles[] = $value;
        }
    });

    expect($handles)->toBe(['gender', 'pronouns', 'workday_gender', 'language']);
});

it('copies a Workday value into an empty gender', function (): void {
    $entry = workdayGenderEntry([]);

    app(WorkdayGender::class)->apply($entry, 'Female');

    expect($entry->get('gender'))->toBe('Female')
        ->and($entry->get('workday_gender'))->toBe('Female')
        ->and($entry->has('workday_gender_review'))->toBeFalse();
});

it('follows Workday while gender still equals its previous value', function (): void {
    $entry = workdayGenderEntry(['gender' => 'Female', 'workday_gender' => 'Female', 'workday_gender_review' => true]);

    app(WorkdayGender::class)->apply($entry, 'Male');

    expect($entry->get('gender'))->toBe('Male')
        ->and($entry->get('workday_gender'))->toBe('Male')
        ->and($entry->has('workday_gender_review'))->toBeFalse();
});

it('keeps an override and flags the entry when Workday sends a new value', function (): void {
    $entry = workdayGenderEntry(['gender' => 'Other', 'workday_gender' => 'Female']);

    app(WorkdayGender::class)->apply($entry, 'Male');

    expect($entry->get('gender'))->toBe('Other')
        ->and($entry->get('workday_gender'))->toBe('Male')
        ->and($entry->get('workday_gender_review'))->toBeTrue();
});

it('flags nothing when the new Workday value equals the override', function (): void {
    $entry = workdayGenderEntry(['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => true]);

    app(WorkdayGender::class)->apply($entry, 'Other');

    expect($entry->get('gender'))->toBe('Other')
        ->and($entry->get('workday_gender'))->toBe('Other')
        ->and($entry->has('workday_gender_review'))->toBeFalse();
});

it('keeps an existing flag when Workday sends the same value again', function (): void {
    $entry = workdayGenderEntry(['gender' => 'Other', 'workday_gender' => 'Male', 'workday_gender_review' => true]);

    app(WorkdayGender::class)->apply($entry, 'Male');

    expect($entry->get('gender'))->toBe('Other')
        ->and($entry->get('workday_gender_review'))->toBeTrue();

    // An override already confirmed stays unflagged when nothing changed
    $confirmed = workdayGenderEntry(['gender' => 'Other', 'workday_gender' => 'Male']);

    app(WorkdayGender::class)->apply($confirmed, 'Male');

    expect($confirmed->has('workday_gender_review'))->toBeFalse();
});

it('keeps a differing gender and flags the entry on its first sync', function (): void {
    $entry = workdayGenderEntry(['gender' => 'Other']);

    app(WorkdayGender::class)->apply($entry, 'Female');

    expect($entry->get('gender'))->toBe('Other')
        ->and($entry->get('workday_gender'))->toBe('Female')
        ->and($entry->get('workday_gender_review'))->toBeTrue();
});

it('flags nothing on a first sync that agrees with gender', function (): void {
    $entry = workdayGenderEntry(['gender' => 'Female']);

    app(WorkdayGender::class)->apply($entry, 'Female');

    expect($entry->get('gender'))->toBe('Female')
        ->and($entry->get('workday_gender'))->toBe('Female')
        ->and($entry->has('workday_gender_review'))->toBeFalse();
});

it('changes nothing for an empty Workday value', function (?string $value): void {
    $entry = workdayGenderEntry(['gender' => 'Other', 'workday_gender' => 'Female']);

    app(WorkdayGender::class)->apply($entry, $value);

    expect($entry->get('gender'))->toBe('Other')
        ->and($entry->get('workday_gender'))->toBe('Female')
        ->and($entry->has('workday_gender_review'))->toBeFalse();
})->with([
    'null' => [null],
    'empty string' => [''],
]);

it('treats an empty-string gender as empty', function (): void {
    $entry = workdayGenderEntry(['gender' => '', 'workday_gender' => 'Female']);

    app(WorkdayGender::class)->apply($entry, 'Male');

    expect($entry->get('gender'))->toBe('Male')
        ->and($entry->has('workday_gender_review'))->toBeFalse();
});

it('reports an entry overridden only when it holds a differing Workday value', function (): void {
    $gender = app(WorkdayGender::class);

    expect($gender->isOverridden(workdayGenderEntry(['gender' => 'Other', 'workday_gender' => 'Female'])))->toBeTrue()
        ->and($gender->isOverridden(workdayGenderEntry(['workday_gender' => 'Female'])))->toBeTrue()
        ->and($gender->isOverridden(workdayGenderEntry(['gender' => 'Female', 'workday_gender' => 'Female'])))->toBeFalse()
        ->and($gender->isOverridden(workdayGenderEntry(['gender' => 'Other'])))->toBeFalse()
        ->and($gender->isOverridden(workdayGenderEntry(['gender' => 'Other', 'workday_gender' => ''])))->toBeFalse();
});

it('reports an entry flagged only while it is flagged and still overridden', function (): void {
    $gender = app(WorkdayGender::class);

    expect($gender->isFlagged(workdayGenderEntry(['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => true])))->toBeTrue()
        ->and($gender->isFlagged(workdayGenderEntry(['gender' => 'Other', 'workday_gender' => 'Female'])))->toBeFalse()
        ->and($gender->isFlagged(workdayGenderEntry(['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => 'yes'])))->toBeFalse()
        ->and($gender->isFlagged(workdayGenderEntry(['gender' => 'Female', 'workday_gender' => 'Female', 'workday_gender_review' => true])))->toBeFalse();
});

it('clears the flag and keeps gender when the override is confirmed', function (): void {
    $entry = workdayGenderEntry(['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => true]);

    app(WorkdayGender::class)->confirmOverride($entry);

    expect($entry->get('gender'))->toBe('Other')
        ->and($entry->has('workday_gender_review'))->toBeFalse();
});

it('takes the Workday value and clears the flag', function (): void {
    $entry = workdayGenderEntry(['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => true]);

    app(WorkdayGender::class)->takeWorkdayValue($entry);

    expect($entry->get('gender'))->toBe('Female')
        ->and($entry->has('workday_gender_review'))->toBeFalse();

    // With no Workday value, gender is kept
    $unsynced = workdayGenderEntry(['gender' => 'Other', 'workday_gender_review' => true]);

    app(WorkdayGender::class)->takeWorkdayValue($unsynced);

    expect($unsynced->get('gender'))->toBe('Other')
        ->and($unsynced->has('workday_gender_review'))->toBeFalse();
});

it('marks workday_gender overridden only when the two differ, for an editor and an administrator', function (): void {
    workdayGenderPerson('nyota', ['gender' => 'Other', 'workday_gender' => 'Female']);
    workdayGenderPerson('hikaru', ['gender' => 'Male', 'workday_gender' => 'Male']);

    foreach ([workdayGenderEditor(), workdayGenderAdministrator()] as $user) {
        test()->actingAs($user);

        $overridden = workdayGenderFieldConfig('nyota');
        $agreeing = workdayGenderFieldConfig('hikaru');

        expect($overridden['instructions'])->toBe(MakeWorkdayFieldsReadOnly::OVERRIDDEN_MARKER.' The gender Workday last sent.')
            ->and($overridden['visibility'])->toBe('read_only')
            ->and($agreeing['instructions'])->toBe('The gender Workday last sent.')
            ->and($agreeing['visibility'])->toBe('read_only');
    }
});

it('takes the marker away again for the next entry sharing the blueprint', function (): void {
    workdayGenderPerson('nyota', ['gender' => 'Other', 'workday_gender' => 'Female']);
    workdayGenderPerson('hikaru', ['gender' => 'Male', 'workday_gender' => 'Male']);

    test()->actingAs(workdayGenderEditor());

    // One blueprint object serves every entry of a request, so resolve both in one
    Blink::flush();
    $nyota = workdayGenderFind('nyota')->blueprint();
    Assert::assertInstanceOf(BlueprintItem::class, $nyota);
    $nyotaField = $nyota->field('workday_gender');
    Assert::assertInstanceOf(Field::class, $nyotaField);
    $marked = $nyotaField->config()['instructions'] ?? null;

    $hikaru = workdayGenderFind('hikaru')->blueprint();
    Assert::assertInstanceOf(BlueprintItem::class, $hikaru);
    $hikaruField = $hikaru->field('workday_gender');
    Assert::assertInstanceOf(Field::class, $hikaruField);
    $unmarked = $hikaruField->config()['instructions'] ?? null;

    expect($marked)->toBe(MakeWorkdayFieldsReadOnly::OVERRIDDEN_MARKER.' The gender Workday last sent.')
        ->and($unmarked)->toBe('The gender Workday last sent.');
});

it('shows the marker alone when workday_gender has no instructions of its own', function (): void {
    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'gender' => ['type' => 'text'],
        'workday_gender' => ['type' => 'text', 'visibility' => 'read_only'],
        'service_line' => ['type' => 'entries', 'collections' => ['organizations']],
    ]);
    $blueprint->setHandle('variant');
    $blueprint->setNamespace('collections.people');
    $blueprint->save();

    // The people collection has read its blueprints already
    Blink::flush();

    workdayGenderPerson('nyota', ['blueprint' => 'variant', 'gender' => 'Other', 'workday_gender' => 'Female']);
    workdayGenderPerson('hikaru', ['blueprint' => 'variant', 'gender' => 'Male', 'workday_gender' => 'Male']);

    test()->actingAs(workdayGenderEditor());

    expect(workdayGenderFieldConfig('nyota')['instructions'])->toBe(MakeWorkdayFieldsReadOnly::OVERRIDDEN_MARKER)
        ->and(workdayGenderFieldConfig('hikaru')['instructions'])->toBe('');
});

it('locks workday_gender on a blueprint that carries none of the other Workday fields', function (): void {
    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'gender' => ['type' => 'text'],
        'workday_gender' => ['type' => 'text'],
        'service_line' => ['type' => 'entries', 'collections' => ['organizations']],
    ]);
    $blueprint->setHandle('variant');
    $blueprint->setNamespace('collections.people');
    $blueprint->save();

    // The people collection has read its blueprints already
    Blink::flush();

    workdayGenderPerson('nyota', ['blueprint' => 'variant', 'gender' => 'Other', 'workday_gender' => 'Female']);

    test()->actingAs(workdayGenderEditor());

    expect(workdayGenderFieldConfig('nyota')['visibility'] ?? null)->toBe('read_only');
});

it('leaves a people blueprint without workday_gender alone', function (): void {
    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'gender' => ['type' => 'text'],
        'service_line' => ['type' => 'entries', 'collections' => ['organizations']],
    ]);
    $blueprint->setHandle('variant');
    $blueprint->setNamespace('collections.people');
    $blueprint->save();

    // The people collection has read its blueprints already
    Blink::flush();

    workdayGenderPerson('nyota', ['blueprint' => 'variant', 'gender' => 'Other', 'workday_gender' => 'Female']);

    test()->actingAs(workdayGenderEditor());
    Blink::flush();

    $resolved = workdayGenderFind('nyota')->blueprint();
    Assert::assertInstanceOf(BlueprintItem::class, $resolved);

    expect($resolved->hasField(WorkdayGender::WORKDAY_FIELD))->toBeFalse()
        ->and($resolved->hasField('gender'))->toBeTrue();
});

it('keeps workday_gender and saves gender when an editor changes both', function (): void {
    workdayGenderPerson('nyota', ['gender' => 'Female', 'workday_gender' => 'Female']);

    test()->actingAs(workdayGenderEditor());

    $url = cp_route('collections.entries.update', ['people', 'nyota']);
    Assert::assertIsString($url);

    test()->patchJson($url, [
        'title' => 'Nyota',
        'slug' => 'nyota',
        'service_line' => [WORKDAY_GENDER_ORG],
        'gender' => 'Other',
        'workday_gender' => 'Male',
    ])->assertOk();

    $nyota = workdayGenderFind('nyota');

    expect($nyota->get('gender'))->toBe('Other')
        ->and($nyota->get('workday_gender'))->toBe('Female');
});

it("lists flagged entries in the viewer's scope, and every flagged entry to an administrator", function (): void {
    workdayGenderPerson('nyota', ['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => true]);
    workdayGenderPerson('hikaru', ['gender' => 'Other', 'workday_gender' => 'Male']);
    workdayGenderPerson('pavel', ['gender' => 'Male', 'workday_gender' => 'Male', 'workday_gender_review' => true]);
    workdayGenderPerson('leonard', ['gender' => 'Other', 'workday_gender' => 'Male', 'workday_gender_review' => true], WORKDAY_GENDER_OTHER_ORG);

    test()->actingAs(workdayGenderEditor());

    expect(workdayGenderWidgetIds())->toBe(['nyota']);

    test()->actingAs(workdayGenderAdministrator());

    expect(workdayGenderWidgetIds())->toBe(['leonard', 'nyota']);

    $rows = workdayGenderWidgetData()['rows'];
    Assert::assertIsArray($rows);

    expect(workdayGenderWidgetData()['title'])->toBe('Gender Overrides to Review')
        ->and($rows[1])->toMatchArray([
            'id' => 'nyota',
            'title' => 'Nyota',
            'edit_url' => workdayGenderFind('nyota')->editUrl(),
            'gender' => 'Other',
            'workday_gender' => 'Female',
            'confirm_url' => workdayGenderActionUrl('confirm', 'nyota'),
            'take_url' => workdayGenderActionUrl('take', 'nyota'),
        ]);
});

it('caps the widget at its configured limit', function (): void {
    workdayGenderPerson('nyota', ['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => true]);
    workdayGenderPerson('leonard', ['gender' => 'Other', 'workday_gender' => 'Male', 'workday_gender_review' => true]);

    test()->actingAs(workdayGenderAdministrator());

    $widget = new WorkdayGenderOverrides;
    $widget->setConfig(['limit' => 1]);

    $view = $widget->html();
    Assert::assertInstanceOf(View::class, $view);

    expect($view->getData()['rows'])->toHaveCount(1);
});

it('reads a limit given as a string, and falls back to the default for one below 1', function (mixed $limit, int $expected): void {
    workdayGenderPerson('nyota', ['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => true]);
    workdayGenderPerson('leonard', ['gender' => 'Other', 'workday_gender' => 'Male', 'workday_gender_review' => true]);

    test()->actingAs(workdayGenderAdministrator());

    $widget = new WorkdayGenderOverrides;
    $widget->setConfig(['limit' => $limit, 'title' => 'Overrides']);

    $view = $widget->html();
    Assert::assertInstanceOf(View::class, $view);

    expect($view->getData()['rows'])->toHaveCount($expected)
        ->and($view->getData()['title'])->toBe('Overrides');
})->with([
    'string' => ['1', 1],
    'zero' => [0, 2],
]);

it('lists only people entries, whatever else carries the fields', function (): void {
    workdayGenderPerson('nyota', ['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => true]);

    $organization = Entry::find(WORKDAY_GENDER_ORG);
    Assert::assertInstanceOf(EntryItem::class, $organization);
    $organization->merge(['gender' => 'Other', 'workday_gender' => 'Male', 'workday_gender_review' => true]);
    $organization->saveQuietly();

    test()->actingAs(workdayGenderAdministrator());

    expect(workdayGenderWidgetIds())->toBe(['nyota']);
});

it('titles a row by its slug when the entry has no title, and shows a cleared gender as empty', function (mixed $title): void {
    $nyota = workdayGenderPerson('nyota', ['title' => $title, 'gender' => null, 'workday_gender' => 'Female', 'workday_gender_review' => true]);
    $nyota->slug('nyota-uhura');
    $nyota->saveQuietly();

    test()->actingAs(workdayGenderAdministrator());

    $rows = workdayGenderWidgetData()['rows'];
    Assert::assertIsArray($rows);

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray(['title' => 'nyota-uhura', 'gender' => '', 'workday_gender' => 'Female']);
})->with([
    'no title' => [null],
    'empty title' => [''],
]);

it('hides the widget from a user who cannot edit people', function (): void {
    workdayGenderPerson('nyota', ['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => true]);

    expect((new WorkdayGenderOverrides)->html())->toBe('');

    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);
    $user->email('restricted-workday-gender@example.test');
    $user->save();

    test()->actingAs($user);

    expect((new WorkdayGenderOverrides)->html())->toBe('');
});

it('clears a flag by confirming the override', function (): void {
    workdayGenderPerson('nyota', ['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => true]);

    test()->actingAs(workdayGenderEditor());

    test()->post(workdayGenderActionUrl('confirm', 'nyota'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Gender override confirmed.');

    $nyota = workdayGenderFind('nyota');
    $stored = YAML::file($nyota->path())->parse();

    expect($nyota->get('gender'))->toBe('Other')
        ->and($nyota->get('workday_gender'))->toBe('Female')
        ->and($nyota->has('workday_gender_review'))->toBeFalse()
        ->and($stored)->not->toHaveKey('workday_gender_review')
        ->and($stored['gender'])->toBe('Other')
        ->and(workdayGenderWidgetIds())->toBe([]);
});

it('clears a flag by taking the Workday value', function (): void {
    workdayGenderPerson('nyota', ['gender' => 'Other', 'workday_gender' => 'Female', 'workday_gender_review' => true]);

    test()->actingAs(workdayGenderEditor());

    test()->post(workdayGenderActionUrl('take', 'nyota'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Gender set to the value in Workday.');

    $nyota = workdayGenderFind('nyota');
    $stored = YAML::file($nyota->path())->parse();

    expect($nyota->get('gender'))->toBe('Female')
        ->and($nyota->has('workday_gender_review'))->toBeFalse()
        ->and($stored)->not->toHaveKey('workday_gender_review')
        ->and($stored['gender'])->toBe('Female')
        ->and(workdayGenderWidgetIds())->toBe([]);
});

it("refuses to clear a flag on an entry outside the editor's scope", function (string $action): void {
    workdayGenderPerson('leonard', ['gender' => 'Other', 'workday_gender' => 'Male', 'workday_gender_review' => true], WORKDAY_GENDER_OTHER_ORG);

    test()->actingAs(workdayGenderEditor());

    test()->postJson(workdayGenderActionUrl($action, 'leonard'))->assertForbidden();

    $leonard = workdayGenderFind('leonard');

    expect($leonard->get('gender'))->toBe('Other')
        ->and($leonard->get('workday_gender_review'))->toBeTrue();
})->with(['confirm', 'take']);

it('answers 404 for an entry that is not a person', function (string $action): void {
    test()->actingAs(workdayGenderAdministrator());

    test()->postJson(workdayGenderActionUrl($action, WORKDAY_GENDER_ORG))->assertNotFound();
    test()->postJson(workdayGenderActionUrl($action, 'missing'))->assertNotFound();
})->with(['confirm', 'take']);

it('sends no email when an entry is flagged or cleared', function (): void {
    Mail::fake();

    $nyota = workdayGenderPerson('nyota', ['gender' => 'Other', 'workday_gender' => 'Female']);
    app(WorkdayGender::class)->apply($nyota, 'Male');
    $nyota->save();

    expect(workdayGenderFind('nyota')->get('workday_gender_review'))->toBeTrue();

    test()->actingAs(workdayGenderEditor());
    test()->post(workdayGenderActionUrl('confirm', 'nyota'))->assertRedirect();

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});
