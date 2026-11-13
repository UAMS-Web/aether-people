<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People;

use Illuminate\Contracts\Foundation\Application;
use Statamic\Facades\Blueprint;
use Statamic\Providers\AddonServiceProvider;

/**
 * Service provider for the add-on.
 *
 * The host application discovers it through `extra.laravel.providers` in
 * `composer.json`. Register the add-on's tags, fieldtypes, listeners, routes and
 * the like here, using the properties `AddonServiceProvider` documents, or in
 * `bootAddon()` for anything that needs Statamic booted first.
 */
class ServiceProvider extends AddonServiceProvider
{
    /**
     * The collections whose blueprints this add-on serves, each from its own
     * directory under `resources/collection-blueprints/`.
     *
     * The person-only fieldsets need no registration: Statamic serves the add-on's
     * `resources/fieldsets/` as `aether-people::<handle>`.
     *
     * @var list<string>
     */
    // PCOV attributes no coverage to a constant's initializer, so Pest selects no test for its
    // mutants and reports them uncovered. Removing either entry, applied by hand, fails that
    // collection's case of "serves each collection blueprint under the collection's own namespace".
    // @pest-mutate-ignore: RemoveArrayItem
    public const array COLLECTION_BLUEPRINT_DIRECTORIES = ['people', 'people_groups'];

    /**
     * The Vite build of the Control Panel assets. `input` and `publicDirectory`
     * must match `vite.config.js`; the built files are committed under
     * `resources/dist`.
     *
     * Assigned in the constructor rather than as a property default: coverage
     * drivers do not report a property default as an executable line, so
     * mutation testing could not reach it there.
     *
     * @var array{input: list<string>, publicDirectory: string}
     */
    protected $vite;

    /**
     * Create the provider and set the Vite build.
     *
     * @param  Application  $app
     */
    public function __construct($app)
    {
        parent::__construct($app);

        // Point Statamic at the Control Panel entry points and the committed build
        $this->vite = [
            'input' => [
                'resources/js/cp.js',
                'resources/css/cp.css',
            ],
            'publicDirectory' => 'resources/dist',
        ];
    }

    /**
     * Merge the `people` config while providers register, so a host provider that reads
     * `people.collection` during its own registration finds it (UAMS-Web/uams-statamic#281).
     */
    #[\Override]
    public function register(): void
    {
        // Merge the add-on's defaults under the key the code reads
        $this->mergeConfigFrom(__DIR__.'/../config/people.php', 'people');
    }

    /**
     * Boot the add-on once Statamic has booted.
     */
    public function bootAddon(): void
    {
        // Serve the people blueprints as the collections' own, with their handles unchanged
        foreach (self::COLLECTION_BLUEPRINT_DIRECTORIES as $collection) {
            Blueprint::addNamespace("collections.{$collection}", self::collectionBlueprintPath($collection));
        }
    }

    /**
     * The directory holding a collection's blueprints in this add-on.
     *
     * A collection's blueprints are read from here in place of the host's
     * `resources/blueprints/collections/<collection>/`, which Statamic then no
     * longer reads. Saving one in the Control Panel writes an override to the
     * host's `resources/blueprints/vendor/collections/<collection>/`, which wins
     * (UAMS-Web/uams-statamic#281).
     *
     * Listing them needs the `statamic/cms` patch UAMS-Web/patches-statamic-cms#6
     * adds: on 6.40.0 unpatched, a collection listing its blueprints from an added
     * namespace fails with "Undefined array key 0".
     *
     * @param  string  $collection  The collection handle.
     */
    public static function collectionBlueprintPath(string $collection): string
    {
        return \dirname(__DIR__)."/resources/collection-blueprints/{$collection}";
    }
}
