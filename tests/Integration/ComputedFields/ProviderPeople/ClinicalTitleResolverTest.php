<?php

declare(strict_types=1);

use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Path;
use Statamic\Facades\Stache;
use Statamic\Stache\Stores\Store;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ClinicalTitleResolver;

/**
 * Coverage for ClinicalTitleResolver:
 * the patient-facing clinical title resolved from the primary
 * `clinical_specializations` entry (UAMS-Web/uams-statamic-archive#2596), shared by the host's SEO `<title>` formula
 * and the `clinical_title` computed the
 * Find-a-Doc provider card renders (UAMS-Web/uams-statamic-archive#64).
 *
 * Specialization entries are persisted into the Testbench Stache so the entry resolution runs
 * against the real query layer rather than mocks. The person is an unsaved entry: the resolver
 * only reads its `clinical_specializations` value, and saving one would resolve the host's
 * `person` blueprint, which the add-on does not carry.
 *
 * @command  vendor/bin/pest tests/Integration/ComputedFields/ProviderPeople/ClinicalTitleResolverTest.php
 */
covers(ClinicalTitleResolver::class);

beforeEach(function (): void {
    // Keep the Stache out of the shared fixtures directory, one directory per test
    $root = storage_path('framework/testing/stache/'.uniqid('clinical-title-', true).'/dev-null');
    Stache::stores()->each(function (Store $store) use ($root): void {
        $store->directory(Path::tidy($root.'/'.$store->key()));
    });
    Stache::clear();

    // The `people` collection the unsaved providers belong to
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    // A structured collection on `default` since UAMS-Web/uams-statamic-archive#2596; people store its entry ids.
    /** @var Collection $specializations */
    $specializations = CollectionFacade::make('clinical_specializations');
    $specializations->title('Clinical Specializations');
    $specializations->sites(['default']);
    $specializations->structureContents(['root' => false]);
    $specializations->save();
});

/**
 * Persist a `clinical_specializations` entry with the given patient-title data.
 *
 * Its id is `spec-<slug>`, deliberately not its slug, so a lookup by slug instead of
 * by the stored id resolves nothing and fails the test.
 *
 * @param  array<string, mixed>  $data
 */
function clinicalTitleMakeSpecialization(string $slug, array $data): void
{
    /** @var EntryInstance $entry */
    $entry = mustBe(EntryInstance::class, Entry::make(), 'an entry');
    $entry->id('spec-'.$slug);
    $entry->collection('clinical_specializations');
    $entry->locale('default');
    $entry->slug($slug);
    $entry->published(true);
    $entry->data($data);
    $entry->saveQuietly();
}

/**
 * A people entry holding the given data, never saved.
 *
 * @param  array<string, mixed>  $data
 */
function clinicalTitleMakePerson(array $data): EntryInstance
{
    $entry = new EntryInstance;
    $entry->collection('people');
    $entry->slug('provider-'.bin2hex(random_bytes(4)));
    $entry->data($data);

    return $entry;
}

function clinicalTitleResolver(): ClinicalTitleResolver
{
    return app(ClinicalTitleResolver::class);
}

it('resolves the patient-friendly specialization title', function (): void {
    clinicalTitleMakeSpecialization('thoracic', [
        'title' => 'Thoracic Surgeon',
        'title_specialization_patient' => 'Thoracic Surgery',
        'title_specialization_query' => false,
    ]);
    $person = clinicalTitleMakePerson(['clinical_specializations' => ['spec-thoracic']]);

    expect(clinicalTitleResolver()->resolve($person))->toBe('Thoracic Surgery');
});

it('prefers the taxonomy code-set name when the query toggle is on', function (): void {
    clinicalTitleMakeSpecialization('oncology', [
        'code_name' => 'Medical Oncology',
        'title_specialization_patient' => 'Cancer Care',
        'title_specialization_query' => true,
    ]);
    $person = clinicalTitleMakePerson(['clinical_specializations' => ['spec-oncology']]);

    // The toggle prefers code_name over the patient specialization title.
    expect(clinicalTitleResolver()->resolve($person))->toBe('Medical Oncology');
});

it('uses the patient title (not code_name) when the toggle is off', function (): void {
    clinicalTitleMakeSpecialization('both', [
        'code_name' => 'Medical Oncology',
        'title_specialization_patient' => 'Cancer Care',
        'title_specialization_query' => false,
    ]);
    $person = clinicalTitleMakePerson(['clinical_specializations' => ['spec-both']]);

    expect(clinicalTitleResolver()->resolve($person))->toBe('Cancer Care');
});

it('falls back to the occupation title, then the entry title', function (): void {
    clinicalTitleMakeSpecialization('occ-only', [
        'title' => 'Cardiologist',
        'title_occupation' => 'Heart Doctor',
        'title_specialization_query' => false,
    ]);
    $occ = clinicalTitleMakePerson(['clinical_specializations' => ['spec-occ-only']]);
    expect(clinicalTitleResolver()->resolve($occ))->toBe('Heart Doctor');

    clinicalTitleMakeSpecialization('title-only', [
        'title' => 'Neurology',
        'title_specialization_query' => false,
    ]);
    $titleOnly = clinicalTitleMakePerson(['clinical_specializations' => ['spec-title-only']]);
    expect(clinicalTitleResolver()->resolve($titleOnly))->toBe('Neurology');
});

it('resolves no title from a left-over term reference or a bare slug, now that the field stores entry ids (#2596)', function (): void {
    clinicalTitleMakeSpecialization('thoracic', [
        'title_specialization_patient' => 'Thoracic Surgery',
        'title_specialization_query' => false,
    ]);

    // The two shapes a taxonomy-era value could take. Neither is an entry id, and the
    // content set stored none of them when it was converted.
    $legacyReference = clinicalTitleMakePerson(['clinical_specializations' => ['clinical_specializations::thoracic']]);
    $bareSlug = clinicalTitleMakePerson(['clinical_specializations' => ['thoracic']]);

    expect(clinicalTitleResolver()->resolve($legacyReference))->toBe('')
        ->and(clinicalTitleResolver()->resolve($bareSlug))->toBe('');
});

it('resolves no title from an entry in another collection', function (): void {
    // An entry's id in the field: a real entry, but not a specialization.
    $crew = CollectionFacade::make('crew');
    $crew->title('Crew');
    $crew->sites(['default']);
    $crew->save();

    $other = mustBe(EntryInstance::class, Entry::make(), 'an entry');
    $other->id('crew-uhura');
    $other->collection('crew');
    $other->locale('default');
    $other->slug('uhura');
    $other->published(true);
    $other->data(['title' => 'Nyota Uhura', 'title_specialization_patient' => 'Communications']);
    $other->saveQuietly();

    $person = clinicalTitleMakePerson(['clinical_specializations' => ['crew-uhura']]);

    expect(clinicalTitleResolver()->resolve($person))->toBe('');
});

it('reads only the FIRST specialization when several are set', function (): void {
    clinicalTitleMakeSpecialization('primary', [
        'title_specialization_patient' => 'Primary Specialty',
        'title_specialization_query' => false,
    ]);
    clinicalTitleMakeSpecialization('secondary', [
        'title_specialization_patient' => 'Secondary Specialty',
        'title_specialization_query' => false,
    ]);
    $person = clinicalTitleMakePerson([
        'clinical_specializations' => ['spec-primary', 'spec-secondary'],
    ]);

    expect(clinicalTitleResolver()->resolve($person))->toBe('Primary Specialty');
});

it('returns empty for no specialization, an unresolvable term, or a non-array field', function (): void {
    expect(clinicalTitleResolver()->resolve(clinicalTitleMakePerson(['title' => 'No Specialty'])))->toBe('')
        ->and(clinicalTitleResolver()->resolve(clinicalTitleMakePerson([
            'clinical_specializations' => ['does-not-exist'],
        ])))->toBe('')
        ->and(clinicalTitleResolver()->resolve(clinicalTitleMakePerson([
            'clinical_specializations' => 'oops',
        ])))->toBe('');
});

it('returns empty for a non-array field even when it reads as a specialization id', function (): void {
    // A string field indexes to its first character, so a one-character id would
    // resolve if the array guard did not stop the read first.
    /** @var EntryInstance $entry */
    $entry = mustBe(EntryInstance::class, Entry::make(), 'an entry');
    $entry->id('x');
    $entry->collection('clinical_specializations');
    $entry->locale('default');
    $entry->slug('x-ray');
    $entry->published(true);
    $entry->data(['title_specialization_patient' => 'Radiology']);
    $entry->saveQuietly();

    $person = clinicalTitleMakePerson(['clinical_specializations' => 'x']);

    expect(clinicalTitleResolver()->resolve($person))->toBe('');
});

it('returns empty when the primary id is an empty string', function (): void {
    $person = clinicalTitleMakePerson(['clinical_specializations' => ['']]);

    expect(clinicalTitleResolver()->resolve($person))->toBe('');
});

it('returns empty for an integer id even when a specialization carries it as a string', function (): void {
    /** @var EntryInstance $specialization */
    $specialization = mustBe(EntryInstance::class, Entry::make(), 'an entry');
    $specialization->id('1701');
    $specialization->collection('clinical_specializations');
    $specialization->locale('default');
    $specialization->slug('enterprise');
    $specialization->published(true);
    $specialization->data(['title_specialization_patient' => 'Starfleet Medical']);
    $specialization->saveQuietly();

    $person = clinicalTitleMakePerson(['clinical_specializations' => [1701]]);

    expect(clinicalTitleResolver()->resolve($person))->toBe('');
});

it('returns empty when every title the specialization carries is blank', function (): void {
    clinicalTitleMakeSpecialization('blank-titles', [
        'title_specialization_patient' => '  ',
        'title_occupation' => '',
        'title' => ' ',
    ]);
    $person = clinicalTitleMakePerson(['clinical_specializations' => ['spec-blank-titles']]);

    expect(clinicalTitleResolver()->resolve($person))->toBe('');
});

it('trims a stored title and reads a numeric one as text', function (mixed $stored, string $expected): void {
    clinicalTitleMakeSpecialization('scalar-title', ['title_specialization_patient' => $stored]);
    $person = clinicalTitleMakePerson(['clinical_specializations' => ['spec-scalar-title']]);

    expect(clinicalTitleResolver()->resolve($person))->toBe($expected);
})->with([
    'padded text' => ['  Sickbay  ', 'Sickbay'],
    'an integer' => [47, '47'],
]);

it('falls through to the patient title when the toggle is on but code_name is empty', function (): void {
    clinicalTitleMakeSpecialization('blank-code', [
        'code_name' => '',
        'title_specialization_patient' => 'Sickbay',
        'title_specialization_query' => true,
    ]);
    $person = clinicalTitleMakePerson(['clinical_specializations' => ['spec-blank-code']]);

    expect(clinicalTitleResolver()->resolve($person))->toBe('Sickbay');
});

it('skips a non-scalar title value and reads the next fallback', function (): void {
    clinicalTitleMakeSpecialization('non-scalar', [
        'title_specialization_patient' => ['Engineering'],
        'title_occupation' => 'Chief Engineer',
        'title_specialization_query' => false,
    ]);
    $person = clinicalTitleMakePerson(['clinical_specializations' => ['spec-non-scalar']]);

    expect(clinicalTitleResolver()->resolve($person))->toBe('Chief Engineer');
});
