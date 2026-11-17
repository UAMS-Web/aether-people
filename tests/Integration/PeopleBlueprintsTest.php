<?php

declare(strict_types=1);

use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\Field;
use Statamic\Fields\FieldsetRepository;
use UAMSWeb\Aether\People\ServiceProvider;

/**
 * The people blueprints and person-only fieldsets are served from the add-on (UAMS-Web/uams-statamic#281).
 *
 * The collection blueprints keep their handles. The fieldsets are served as
 * `aether-people::<handle>`, the namespace Statamic gives an add-on's
 * `resources/fieldsets/`, and the blueprints and fieldsets import them by that
 * name; a fieldset the host keeps is imported by its bare handle.
 *
 * The host's own fieldsets are stood in for by `tests/Fixtures/HostFieldsets/`, which
 * `scripts/generate-host-fieldset-stubs.php` writes from the add-on's references.
 *
 * @command  vendor/bin/pest tests/Integration/PeopleBlueprintsTest.php
 */
beforeEach(function (): void {
    // Read host fieldsets from the stand-ins, as the host would from its own directory
    app(FieldsetRepository::class)->setDirectory(__DIR__.'/../Fixtures/HostFieldsets');
});

it("serves each collection blueprint under the collection's own namespace", function (string $collection, string $blueprint): void {
    // Make the collection, as the host's content does
    Collection::make($collection)->save();

    // List the blueprints the collection finds
    $listed = Collection::findByHandle($collection)?->entryBlueprints();
    if (! $listed instanceof Illuminate\Support\Collection) {
        throw new LogicException("The {$collection} collection listed no blueprints.");
    }

    expect($listed->map(fn (mixed $found): ?string => $found instanceof StatamicBlueprint ? $found->handle() : null)->all())->toBe([$blueprint])
        ->and(Blueprint::in("collections/{$collection}")->keys()->all())->toBe([$blueprint]);
})->with([
    'people' => ['people', 'person'],
    'people groups' => ['people_groups', 'people_group'],
]);

it('reads each collection blueprint from the add-on directory', function (string $collection): void {
    // Read the directory registered for the collection
    $registered = Blueprint::getAdditionalNamespaces()->get("collections.{$collection}");
    if (! is_string($registered)) {
        throw new LogicException("No directory is registered for the {$collection} collection.");
    }

    expect(realpath($registered))->toBe(realpath(__DIR__."/../../resources/collection-blueprints/{$collection}"))
        ->and(ServiceProvider::collectionBlueprintPath($collection))->toBe($registered);
})->with(ServiceProvider::COLLECTION_BLUEPRINT_DIRECTORIES);

it('serves every person-only fieldset under the add-on namespace', function (string $handle): void {
    expect(Fieldset::find("aether-people::{$handle}"))->not->toBeNull()
        ->and(Fieldset::find($handle))->toBeNull();
})->with(fn (): array => array_map(
    fn (string $path): string => basename($path, '.yaml'),
    glob(__DIR__.'/../../resources/fieldsets/*.yaml') ?: [],
));

/**
 * The person blueprint, as the people collection serves it.
 */
function personBlueprint(): StatamicBlueprint
{
    Collection::make('people')->save();

    $blueprint = Collection::findByHandle('people')?->entryBlueprint('person');
    if (! $blueprint instanceof StatamicBlueprint) {
        throw new LogicException('The people collection served no person blueprint.');
    }

    return $blueprint;
}

/**
 * A field of the person blueprint.
 */
function personField(string $handle): Field
{
    $field = personBlueprint()->field($handle);
    if (! $field instanceof Field) {
        throw new LogicException("The person blueprint has no {$handle} field.");
    }

    return $field;
}

it('records the trainee type in one select field in place of the per-type yes/no fields (UAMS-Web/aether-people#58)', function (): void {
    $field = personField('trainee_type');
    $options = $field->config()['options'] ?? null;

    expect($field->type())->toBe('select')
        ->and(is_array($options) ? array_column($options, 'key') : null)->toBe(['none', 'resident', 'fellow'])
        ->and($field->get('localizable'))->toBeFalse()
        ->and($field->get('validate'))->toBe(['required_unless:clinical_separated_query,true', 'sometimes'])
        ->and($field->conditions())->toBe(['if' => ['uams_worker_query' => 'equals true']])
        ->and(personBlueprint()->hasField('resident_query'))->toBeFalse()
        ->and(personBlueprint()->hasField('fellow_query'))->toBeFalse();
});

it('shows each trainee-dependent field from the trainee type (UAMS-Web/aether-people#58)', function (string $handle, array $conditions): void {
    expect(personField($handle)->conditions())->toBe($conditions);
})->with([
    'the residency department' => ['resident_academic_department', ['if' => ['trainee_type' => 'equals resident']]],
    'the chief resident question' => ['resident_chief_query', ['if' => ['trainee_type' => 'equals resident']]],
    'the fellowship department' => ['fellow_academic_department', ['if' => ['trainee_type' => 'equals fellow']]],
    'the home country question for either trainee' => ['home_country_query', ['if' => ['trainee_type' => 'includes_any resident, fellow']]],
]);

it('hides the clinical fields from both kinds of trainee (UAMS-Web/aether-people#58)', function (string $handle): void {
    $conditions = personField($handle)->conditions()['if'] ?? null;

    expect(is_array($conditions) ? ($conditions['trainee_type'] ?? null) : null)->toBe(['not resident', 'not fellow'])
        ->and(is_array($conditions) ? ($conditions['clinical_role_query'] ?? null) : null)->toBe('equals true');
})->with(['clinical_specializations', 'service_line']);

it('names no per-type trainee field anywhere in the blueprints or fieldsets (UAMS-Web/aether-people#58)', function (): void {
    $files = array_merge(
        glob(__DIR__.'/../../resources/collection-blueprints/*/*.yaml') ?: [],
        glob(__DIR__.'/../../resources/fieldsets/*.yaml') ?: [],
    );

    $naming = array_filter($files, fn (string $file): bool => preg_match('/\b(?:resident|fellow)_query\b/', (string) file_get_contents($file)) === 1);

    expect($files)->not->toBeEmpty()
        ->and(array_values($naming))->toBe([]);
});
