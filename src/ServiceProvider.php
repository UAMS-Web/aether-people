<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;
use Statamic\Facades\Blueprint;
use Statamic\Facades\URL as StatamicUrl;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Sites\Site as SiteInstance;
use Statamic\Sites\Sites;
use Statamic\Statamic;
use UAMSWeb\Aether\People\Http\Controllers\PeopleLensController;
use UAMSWeb\Aether\People\Lenses\PeopleLensRegistry;

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
        $this->mergeConfigFrom(__DIR__.'/../config/people_lenses.php', 'people_lenses');
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

        // Render the person_name field on a public form
        $this->addStatamicViewsAfterHostOverrides(__DIR__.'/../resources/field-views');

        // Render the people search card, after a host's override of it
        $this->addStatamicViewsAfterHostOverrides(__DIR__.'/../resources/search-views', 'aether-search');

        // Serve the people templates, the Find-a-Doc blocks and the people group page under their own
        // names, after the host's views: a host that still carries a copy keeps its copy
        View::addLocation(__DIR__.'/../resources/views');

        // Route each lens ahead of Statamic's catch-all
        Statamic::pushWebRoutes(fn () => $this->registerLensRoutes());
    }

    /**
     * Register one route per active site for each configured lens.
     *
     * A lens renders one canonical `people` entry, which lives only on the canonical
     * site, at its own URL on each site it is active on: the clinical `/provider/{slug}`
     * on `health`, the org-derived lenses on their hub sites. The routes sit under
     * each site's own URL prefix and ahead of Statamic's catch-all, so a lens URL
     * reaches {@see PeopleLensController}; a URL under the same prefix that is not a
     * lens falls through to the front end without shadowing a real page. The registry
     * is the single source of the lens set (UAMS-Web/uams-statamic-archive#1635 and
     * UAMS-Web/uams-statamic-archive#1636).
     */
    private function registerLensRoutes(): void
    {
        $sites = $this->app->make(Sites::class)->all();

        // A site list that is not iterable leaves nothing to route
        if (! \is_iterable($sites)) {
            return;
        }

        foreach ($this->app->make(PeopleLensRegistry::class)->all() as $lens) {
            // Collect each active site's own URL prefix, once
            $prefixes = [];

            foreach ($sites as $site) {
                // Statamic's site accessors are untyped, so a malformed site is skipped
                if (! $site instanceof SiteInstance || ! \is_string($site->handle()) || ! \is_string($site->url())) {
                    continue;
                }

                $prefix = StatamicUrl::makeRelative($site->url());

                // A prefix always begins with a slash, so no two distinct ones compare loosely equal and the
                // strict comparison changes nothing.
                // @pest-mutate-ignore: TrueToFalse
                if ($lens->activeOnSite($site->handle()) && ! \in_array($prefix, $prefixes, true)) {
                    $prefixes[] = $prefix;
                }
            }

            foreach ($prefixes as $prefix) {
                Route::prefix($prefix)->group(function () use ($lens): void {
                    Route::get($lens->route, PeopleLensController::class)
                        ->defaults('lens', $lens->handle)
                        ->middleware('statamic.web');
                });
            }
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

    /**
     * Add a path to the `statamic` view namespace, after a host's overrides of it.
     *
     * Statamic renders a public form field with the first of
     * `statamic::forms.fields.<handle>` and its fallbacks that exists. Laravel
     * lists a host's own `resources/views/vendor/statamic` ahead of Statamic's
     * views, which is how a host overrides a package view. The add-on's views go
     * between the two, so they supply the view and a host's copy still wins.
     * The same helper is in `uams-web/aether-form-settings`, where the path
     * resolution was fixed first (UAMS-Web/aether-form-settings#37).
     *
     * @param  string  $path  The add-on's view directory.
     * @param  string  $namespace  The view namespace to add it to.
     */
    private function addStatamicViewsAfterHostOverrides(string $path, string $namespace = 'statamic'): void
    {
        // Read the namespace's current paths
        $finder = View::getFinder();
        // Laravel's view factory always holds a FileViewFinder, so the guard is for PHPStan and the
        // other branch cannot be reached from a test.
        // @pest-mutate-ignore: InstanceOfToTrue
        $hints = $finder instanceof FileViewFinder ? $finder->getHints()[$namespace] ?? [] : [];

        // The override directories Laravel looks for under each of the host's view paths, resolved as the
        // finder resolves a hint: a recent Laravel stores a hint through realpath(), so a view path that goes
        // through a symlink or a `..` segment would otherwise never match its own hint
        $viewPaths = config('view.paths');
        $overrides = array_map(
            // A view path that is not a string names no directory, so any text in its place matches no hint.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            static fn (mixed $viewPath): string => self::resolveViewPath((\is_string($viewPath) ? $viewPath : '')."/vendor/{$namespace}"),
            \is_array($viewPaths) ? $viewPaths : [],
        );

        // Keep the host's overrides first, then the add-on's views, then the rest
        $first = [];
        $rest = [];
        foreach ($hints as $hint) {
            // Resolve the hint too, for a Laravel release that stores hints as given
            $resolved = \is_string($hint) ? self::resolveViewPath($hint) : $hint;

            // Hints and overrides are both directory paths, which compare the same loosely or strictly.
            // @pest-mutate-ignore: TrueToFalse
            if (\in_array($resolved, $overrides, true)) {
                $first[] = $hint;
            } else {
                $rest[] = $hint;
            }
        }

        View::replaceNamespace($namespace, [...$first, $path, ...$rest]);
    }

    /**
     * Resolve a view directory the way Laravel's view finder resolves a namespace hint.
     *
     * @param  string  $path  The directory as configured.
     * @return string The directory with its symlinks resolved, or as given when it does not exist.
     */
    private static function resolveViewPath(string $path): string
    {
        return realpath($path) ?: $path;
    }
}
