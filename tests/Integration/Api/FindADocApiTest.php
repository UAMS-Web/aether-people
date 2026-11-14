<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Fields\FieldsetRepository;
use UAMSWeb\Aether\People\Api\FindADoc\FindADocProviderRepository;
use UAMSWeb\Aether\People\Api\FindADoc\ProviderPayloadTransformer;
use UAMSWeb\Aether\People\Http\Controllers\Api\V1\FindADoc\ProviderIndexController;
use UAMSWeb\Aether\People\Http\Controllers\Api\V1\FindADoc\ProviderShowController;

covers(
    FindADocProviderRepository::class,
    ProviderPayloadTransformer::class,
    ProviderIndexController::class,
    ProviderShowController::class,
);

/**
 * The Find-a-Doc read API (UAMS-Web/uams-statamic-archive#1043): the versioned, read-only provider
 * endpoints the UAMS Health app consumes.
 *
 * The payload must stay byte-identical to the site's, so the response body is compared with a
 * golden fixture in `tests/Fixtures/FindADoc/`. The two load-bearing guarantees are that only
 * eligible, searchable providers are exposed and that the payload is an allow-list, so no
 * `people` private field leaks.
 *
 * @command vendor/bin/pest tests/Integration/Api/FindADocApiTest.php
 */
beforeEach(function (): void {
    Cache::flush();

    // Save the entries under a directory of this test's own, so no worker reads another's
    $directory = fadApiStacheDirectory(fresh: true);
    Stache::stores()->each(fn ($store) => $store->directory($directory.'/'.$store->key()));

    // Read the host's fieldsets, which the person blueprint imports, from the stand-ins
    app(FieldsetRepository::class)->setDirectory(__DIR__.'/../../Fixtures/HostFieldsets');

    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    // A structured collection since UAMS-Web/uams-statamic-archive#2596; people store its entry ids
    $specializations = CollectionFacade::make('clinical_specializations');
    $specializations->title('Clinical Specializations');
    $specializations->sites(['default']);
    $specializations->structureContents(['root' => false]);
    $specializations->save();
});

afterEach(function (): void {
    app('files')->deleteDirectory(fadApiStacheDirectory());
});

/**
 * The directory this test saves entries under, made new when asked.
 */
function fadApiStacheDirectory(bool $fresh = false): string
{
    static $directory = '';

    if ($fresh || $directory === '') {
        $directory = sys_get_temp_dir().'/fad-api-'.uniqid('', true);
    }

    return $directory;
}

/**
 * Persist a `clinical_specializations` entry and return its id.
 */
function fadApiSpecialization(string $slug, string $title, ?string $id = null): string
{
    $entry = new EntryInstance;
    $entry->collection('clinical_specializations');
    $entry->locale('default');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => $title]);

    if ($id !== null) {
        $entry->id($id);
    }

    $entry->saveQuietly();

    return mustBeString($entry->id(), 'the specialization id');
}

/**
 * The id of a published practice location, which every eligible person links to.
 */
function fadApiLocationId(): string
{
    if (CollectionFacade::find('clinical_locations') === null) {
        $collection = CollectionFacade::make('clinical_locations');
        $collection->title('Clinical Locations');
        $collection->save();
    }

    $location = new EntryInstance;
    $location->collection('clinical_locations');
    $location->id('loc-published');
    $location->slug('eligibility-published-location');
    $location->published(true);
    $location->data(['title' => 'Eligibility Published Location']);
    $location->saveQuietly();

    return 'loc-published';
}

/**
 * The people fields where every clinical provider eligibility base rule passes.
 *
 * @return array<string, mixed>
 */
function fadApiBaseFields(): array
{
    return [
        'uams_worker_query' => true,
        'uams_worker_subtype' => 'employee',
        'uams_employee_subtype' => 'regular',
        'resident_query' => false,
        'fellow_query' => false,
        'clinical_role_query' => true,
        'direct_patient_care_query' => true,
        'clinical_locations' => [fadApiLocationId()],
    ];
}

/**
 * Persist a people entry that is eligible by default, with a structured name.
 *
 * @param  array<string, mixed>  $fields  Overrides and extra fields.
 */
function fadApiPerson(string $id, string $slug, string $first, string $last, array $fields = []): EntryInstance
{
    $entry = new EntryInstance;
    $entry->collection('people');
    $entry->id($id);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data([
        ...fadApiBaseFields(),
        'title' => $first.' '.$last,
        'credentials' => ['md'],
        'person_name' => [
            'first_name' => ['text' => $first, 'phonetic_text' => ''],
            'no_middle_name' => true,
            'middle_name' => ['text' => '', 'phonetic_text' => ''],
            'no_nickname' => true,
            'nickname' => ['text' => '', 'phonetic_text' => ''],
            'last_name' => ['text' => $last, 'phonetic_text' => ''],
            'no_generational_suffix' => true,
            'generational_suffix' => null,
            'display_format' => null,
        ],
        ...$fields,
    ]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * The standard cast: an eligible provider carrying private and clinical fields, an ineligible
 * resident, and an actively separated (not searchable) provider.
 */
function fadApiCast(): void
{
    fadApiPerson('prov-mccoy', 'leonard-mccoy', 'Leonard', 'McCoy', [
        'clinical_specializations' => [fadApiSpecialization('cardiology', 'Cardiology', 'spec-cardiology')],
        'service_line' => ['heart'],
        'pronouns' => 'he-him',
        'accepting_patients_query' => true,
        'referral_required' => false,
        'portrait' => 'portraits/mccoy-3x4.jpg',
        'portrait_wide' => 'portraits/mccoy-16x9.jpg',
        // Private fields that must never leave the API
        'npi' => '1234567890',
        'epic_ser_id' => 'EPIC-42',
        'uams_workday_id' => ['workday_universal_id' => 'WD-7'],
        'email' => 'mccoy@uams.edu',
        'telephone' => '501-555-0100',
        'gender' => 'Male',
    ]);

    fadApiPerson('prov-chekov', 'pavel-chekov', 'Pavel', 'Chekov', ['resident_query' => true]);

    fadApiPerson('prov-sulu', 'hikaru-sulu', 'Hikaru', 'Sulu', [
        'clinical_separated_query' => true,
        'separated_start_date' => Carbon::yesterday()->toDateString(),
        'separated_end_date' => Carbon::tomorrow()->toDateString(),
    ]);
}

/**
 * The response body of a GET, as the bytes the app receives.
 */
function fadApiBody(string $uri): string
{
    return mustBeString(test()->getJson($uri)->assertOk()->getContent(), 'the response body');
}

it('lists only eligible, searchable providers, versioned', function (): void {
    fadApiCast();

    $response = $this->getJson('/api/v1/find-a-doc/providers')->assertOk();

    expect($response->json('version'))->toBe('v1')
        ->and(collect(mustBeArray($response->json('data'), 'data'))->pluck('id')->all())->toBe(['prov-mccoy']);
});

it('serves the index and show payloads byte for byte as the golden fixtures', function (): void {
    fadApiCast();
    $fixtures = dirname(__DIR__, 2).'/Fixtures/FindADoc';

    expect(fadApiBody('/api/v1/find-a-doc/providers'))->toBe(trim((string) file_get_contents($fixtures.'/index.json')))
        ->and(fadApiBody('/api/v1/find-a-doc/providers/prov-mccoy'))->toBe(trim((string) file_get_contents($fixtures.'/show.json')));
});

it('emits exactly the allow-listed keys and none of the private fields', function (): void {
    fadApiCast();

    $body = fadApiBody('/api/v1/find-a-doc/providers');
    $data = mustBeArray(json_decode($body, true, flags: JSON_THROW_ON_ERROR), 'the body');
    $provider = mustBeArray(mustBeArray($data['data'] ?? null, 'data')[0] ?? null, 'the first provider');

    expect(array_keys($provider))->toBe([
        'id', 'slug', 'url', 'name', 'credentials', 'clinical_title',
        'specialties', 'service_line', 'pronouns', 'appointments', 'is_resident', 'portrait',
    ])
        ->and(array_keys(mustBeArray($provider['name'], 'name')))->toBe(['full', 'full_with_credentials', 'short_with_prefix', 'prefix'])
        ->and(array_keys(mustBeArray($provider['appointments'], 'appointments')))->toBe(['accepting_new_patients', 'referral_required'])
        ->and(array_keys(mustBeArray($provider['portrait'], 'portrait')))->toBe(['headshot', 'wide'])
        ->and($provider)->not->toHaveKeys(['npi', 'epic_ser_id', 'uams_workday_id', 'email', 'telephone', 'gender']);

    foreach (['1234567890', 'EPIC-42', 'WD-7', 'mccoy@uams.edu', '501-555-0100', 'Male'] as $private) {
        expect($body)->not->toContain($private);
    }
});

it('serves one eligible provider by id with the same payload as the index', function (): void {
    fadApiCast();

    $index = mustBeArray($this->getJson('/api/v1/find-a-doc/providers')->json('data'), 'data');
    $show = $this->getJson('/api/v1/find-a-doc/providers/prov-mccoy')->assertOk();

    expect($show->json('version'))->toBe('v1')
        ->and($show->json('data'))->toBe($index[0])
        ->and($show->json('data.name.full'))->toBe('Leonard McCoy');
});

it('404s show for an ineligible, a not searchable, an unpublished, a non-person and an unknown provider', function (): void {
    fadApiCast();
    $draft = fadApiPerson('prov-draft', 'draft-person', 'Draft', 'Person');
    $draft->published(false);
    $draft->saveQuietly();

    foreach (['prov-chekov', 'prov-sulu', 'prov-draft', 'spec-cardiology', 'loc-published', 'does-not-exist'] as $id) {
        $this->getJson('/api/v1/find-a-doc/providers/'.$id)->assertNotFound();
    }
});

it('leaves an unpublished person out of the list', function (): void {
    fadApiCast();
    $draft = fadApiPerson('prov-draft', 'draft-person', 'Draft', 'Person');
    $draft->published(false);
    $draft->saveQuietly();

    expect(app(FindADocProviderRepository::class)->eligibleProviders()->map(fn (EntryInstance $e) => $e->id())->all())
        ->toBe(['prov-mccoy']);
});

it('exposes only people, however eligible an entry of another collection looks', function (): void {
    fadApiCast();

    // An entry of another collection that carries every field that makes a person eligible
    $impostor = new EntryInstance;
    $impostor->collection('clinical_specializations');
    $impostor->locale('default');
    $impostor->id('prov-impostor');
    $impostor->slug('impostor');
    $impostor->published(true);
    $impostor->data([...fadApiBaseFields(), 'title' => 'Impostor', 'credentials' => ['md']]);
    $impostor->saveQuietly();

    $ids = app(FindADocProviderRepository::class)->eligibleProviders()->map(fn (EntryInstance $e) => $e->id())->all();

    expect($ids)->toBe(['prov-mccoy'])
        ->and(app(FindADocProviderRepository::class)->findEligible('prov-impostor'))->toBeNull();
    $this->getJson('/api/v1/find-a-doc/providers/prov-impostor')->assertNotFound();
});

it('leaves out a published person whose date has not come, in a collection that keeps future entries private', function (): void {
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->dated(true);
    $people->futureDateBehavior('private');
    $people->save();

    $scheduled = fadApiPerson('prov-scheduled', 'scheduled-person', 'Scheduled', 'Person');
    $scheduled->date(Carbon::tomorrow());
    $scheduled->saveQuietly();

    $current = fadApiPerson('prov-current', 'current-person', 'Current', 'Person');
    $current->date(Carbon::yesterday());
    $current->saveQuietly();

    expect($scheduled->published())->toBeTrue()
        ->and($scheduled->status())->toBe('scheduled')
        ->and(app(FindADocProviderRepository::class)->eligibleProviders()->map(fn (EntryInstance $e) => $e->id())->all())->toBe(['prov-current']);
});

it('lists each eligible person once', function (): void {
    fadApiCast();
    fadApiPerson('prov-kirk', 'james-kirk', 'James', 'Kirk');

    $ids = app(FindADocProviderRepository::class)->eligibleProviders()->map(fn (EntryInstance $e) => $e->id())->all();

    expect($ids)->toEqualCanonicalizing(['prov-kirk', 'prov-mccoy']);
});

it('keeps `specialties` as the slugs in stored order, dropping an id that is not a specialization', function (): void {
    $other = new EntryInstance;
    $other->collection('people');
    $other->id('prov-other');
    $other->slug('christine-chapel');
    $other->published(true);
    $other->data(['title' => 'Christine Chapel']);
    $other->saveQuietly();

    fadApiPerson('prov-scott', 'montgomery-scott', 'Montgomery', 'Scott', [
        'clinical_specializations' => [
            fadApiSpecialization('thoracic-surgeon', 'Thoracic Surgeon'),
            'cardiology',
            'prov-other',
            fadApiSpecialization('congenital-heart-surgeon', 'Congenital Heart Surgeon'),
        ],
    ]);

    $body = fadApiBody('/api/v1/find-a-doc/providers/prov-scott');

    expect($body)->toContain('"specialties":["thoracic-surgeon","congenital-heart-surgeon"]');
});

it('emits null for an empty text and a false for a missing flag', function (): void {
    fadApiPerson('prov-bare', 'bare-person', 'Bare', 'Person', ['service_line' => '']);

    $payload = mustBeArray($this->getJson('/api/v1/find-a-doc/providers/prov-bare')->assertOk()->json('data'), 'data');

    expect($payload['credentials'])->toBe(['md'])
        ->and($payload['clinical_title'])->toBeNull()
        ->and($payload['service_line'])->toBe([])
        ->and($payload['pronouns'])->toBeNull()
        ->and($payload['specialties'])->toBe([])
        ->and($payload['appointments'])->toBe(['accepting_new_patients' => false, 'referral_required' => false])
        ->and($payload['portrait'])->toBe(['headshot' => null, 'wide' => null]);
});

it('emits null for a name, a pronoun and a portrait that are empty or not text, and drops empty and non-text list items', function (): void {
    fadApiPerson('prov-blank', 'blank-person', 'Blank', 'Person', [
        'title' => '',
        'person_name' => [
            'first_name' => ['text' => '', 'phonetic_text' => ''],
            'no_middle_name' => true,
            'no_nickname' => true,
            'last_name' => ['text' => '', 'phonetic_text' => ''],
            'no_generational_suffix' => true,
            'display_format' => null,
        ],
        'pronouns' => '',
        'portrait' => 5,
        'portrait_wide' => '',
        'service_line' => ['heart', '', 5, null, ['nested']],
    ]);

    $payload = mustBeArray($this->getJson('/api/v1/find-a-doc/providers/prov-blank')->assertOk()->json('data'), 'data');

    expect($payload['pronouns'])->toBeNull()
        ->and($payload['portrait'])->toBe(['headshot' => null, 'wide' => null])
        ->and($payload['service_line'])->toBe(['heart'])
        ->and(mustBeArray($payload['name'], 'name')['full'])->toBeNull();
});

it('drops a specialization with no usable slug', function (): void {
    $slugless = new EntryInstance;
    $slugless->collection('clinical_specializations');
    $slugless->locale('default');
    $slugless->id('spec-slugless');
    $slugless->published(true);
    $slugless->data(['title' => 'Slugless']);
    $slugless->saveQuietly();

    fadApiPerson('prov-spec', 'spec-person', 'Spec', 'Person', ['clinical_specializations' => ['spec-slugless']]);

    expect($this->getJson('/api/v1/find-a-doc/providers/prov-spec')->assertOk()->json('data.specialties'))->toBe([]);
});

it('coerces a stored boolean string to a strict bool and a single string to a list', function (): void {
    fadApiPerson('prov-str', 'string-person', 'String', 'Person', ['accepting_patients_query' => 'true', 'referral_required' => '1', 'service_line' => 'heart']);

    $payload = mustBeArray($this->getJson('/api/v1/find-a-doc/providers/prov-str')->assertOk()->json('data'), 'data');

    expect($payload['appointments'])->toBe(['accepting_new_patients' => true, 'referral_required' => true])
        ->and($payload['service_line'])->toBe(['heart']);
});

it('caches the index for the configured time and not at all when the time is zero', function (): void {
    fadApiCast();
    $first = fadApiBody('/api/v1/find-a-doc/providers');
    fadApiPerson('prov-kirk', 'james-kirk', 'James', 'Kirk');

    expect(fadApiBody('/api/v1/find-a-doc/providers'))->toBe($first)
        ->and(Cache::has('find_a_doc_api:v1:providers'))->toBeTrue();

    config()->set('find_a_doc_api.index_cache_ttl', 0);
    Cache::flush();

    expect(fadApiBody('/api/v1/find-a-doc/providers'))->not->toBe($first)
        ->and(Cache::has('find_a_doc_api:v1:providers'))->toBeFalse();
});

it('caches the index for the configured number of seconds', function (int|string|null $configured, int $seconds): void {
    fadApiCast();
    $start = Carbon::parse('2026-03-02 09:00:00');
    Carbon::setTestNow($start);

    if ($configured === null) {
        config()->set('find_a_doc_api', ['version' => 'v1']);
    } else {
        config()->set('find_a_doc_api.index_cache_ttl', $configured);
    }

    $this->getJson('/api/v1/find-a-doc/providers')->assertOk();
    $kept = [];
    foreach ([$seconds - 1, $seconds] as $elapsed) {
        Carbon::setTestNow($start->copy()->addSeconds($elapsed));
        $kept[] = Cache::has('find_a_doc_api:v1:providers');
    }

    Carbon::setTestNow();

    expect($kept)->toBe([true, false]);
})->with([
    'one second' => [1, 1],
    'sixty seconds' => [60, 60],
    'no configured time' => [null, 300],
    'a time that is not a number' => ['soon', 300],
]);

it('reports the configured version', function (): void {
    fadApiCast();
    config()->set('find_a_doc_api.version', 'v2');

    expect($this->getJson('/api/v1/find-a-doc/providers')->json('version'))->toBe('v2')
        ->and($this->getJson('/api/v1/find-a-doc/providers/prov-mccoy')->json('version'))->toBe('v2');
});

it('falls back to the default version and cache time when the config is not of the right type', function (): void {
    fadApiCast();
    config()->set('find_a_doc_api.version', ['v9']);
    config()->set('find_a_doc_api.index_cache_ttl', 'soon');

    expect($this->getJson('/api/v1/find-a-doc/providers')->json('version'))->toBe('v1')
        ->and(Cache::has('find_a_doc_api:v1:providers'))->toBeTrue()
        ->and($this->getJson('/api/v1/find-a-doc/providers/prov-mccoy')->json('version'))->toBe('v1');
});

it('registers the two GET routes in the api group, throttled, ahead of the catch-all', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_contains($route->uri(), 'find-a-doc'));

    expect($routes->map(fn (Route $route): string => $route->uri())->sort()->values()->all())
        ->toBe(['api/v1/find-a-doc/providers', 'api/v1/find-a-doc/providers/{provider}']);

    $routes->each(function (Route $route): void {
        expect($route->methods())->toBe(['GET', 'HEAD'])
            ->and($route->gatherMiddleware())->toContain('api')->toContain('throttle:60,1');
    });

    // The request reaches the controller: JSON from the API, not a page from the catch-all
    $response = $this->getJson('/api/v1/find-a-doc/providers')->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/json')
        ->and($this->getJson('/api/v1/find-a-doc/providers/nope')->assertNotFound()->json('message'))->toBe('Provider not found or not available.');
});

it('serves no write verb on the provider endpoints', function (): void {
    // The routes are GET only, so a write finds no route and the front end answers 404
    foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
        $this->{$method}('/api/v1/find-a-doc/providers')->assertNotFound();
    }
});

it('throttles at the configured requests per minute and reports it in the headers', function (): void {
    $first = $this->getJson('/api/v1/find-a-doc/providers')->assertOk();

    expect($first->headers->get('X-RateLimit-Limit'))->toBe('60')
        ->and($first->headers->get('X-RateLimit-Remaining'))->toBe('59');

    for ($i = 0; $i < 59; $i++) {
        $this->getJson('/api/v1/find-a-doc/providers')->assertOk();
    }

    $this->getJson('/api/v1/find-a-doc/providers')->assertStatus(429);
});
