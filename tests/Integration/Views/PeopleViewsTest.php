<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Statamic\Facades\Collection;

/**
 * The moved templates against the host partials they include
 * (UAMS-Web/uams-statamic#281, UAMS-Web/aether-people#55).
 *
 * The templates include partials the host keeps (`layout/article`, the page-builder
 * components and the clinical cards), a runtime contract with the host. The fixtures in
 * `tests/Fixtures/HostPartials/` stand in for them, one marker each, and a guard fails when
 * a template names a partial that is neither moved nor stubbed, or a stub is no longer named.
 *
 * @command vendor/bin/pest tests/Integration/Views/PeopleViewsTest.php
 */

/**
 * The partial names a set of view files include, as `dir/name`.
 *
 * @param  list<string>  $files  Absolute paths.
 * @return list<string>
 */
function includedPartials(array $files): array
{
    $names = [];

    foreach ($files as $file) {
        $text = (string) file_get_contents($file);

        // `partial:name` in a tag and `partial src="name"` both name one
        foreach (['/partial:([A-Za-z0-9_\/.:-]+)/', '/\{\{\s*partial\s+src=[\'"]([^\'"]+)/'] as $pattern) {
            preg_match_all($pattern, $text, $matches);
            array_push($names, ...$matches[1]);
        }
    }

    $names = array_values(array_unique($names));
    sort($names);

    return $names;
}

/**
 * Every view file under a directory, absolute.
 *
 * @return list<string>
 */
function viewFilesUnder(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile()) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * The `dir/name` of a partial file, from its path under a root.
 */
function partialNameOf(string $path, string $root): string
{
    $relative = substr($path, strlen($root) + 1);
    $name = preg_replace('/\.antlers\.html$/', '', $relative);

    return (string) preg_replace('/(^|\/)_([^\/]+)$/', '$1$2', (string) $name);
}

it("includes only partials that are moved or stubbed as the host's, and stubs only ones it includes", function (): void {
    $addon = dirname(__DIR__, 3).'/resources';
    $stubs = dirname(__DIR__, 2).'/Fixtures/HostPartials';

    $moved = array_map(static fn (string $path): string => partialNameOf($path, $addon.'/views'), viewFilesUnder($addon.'/views'));
    $stubbed = array_map(
        static fn (string $path): string => partialNameOf($path, $stubs),
        array_filter(viewFilesUnder($stubs), static fn (string $path): bool => str_contains(basename($path), '_')),
    );
    $included = array_filter(
        includedPartials([...viewFilesUnder($addon.'/views'), ...viewFilesUnder($addon.'/search-views')]),
        // A partial in another view namespace is the package's own
        static fn (string $name): bool => ! str_contains($name, '::'),
    );

    $unresolved = array_values(array_diff($included, $moved, $stubbed));
    $stale = array_values(array_diff($stubbed, $included));
    sort($stale);

    expect($unresolved)->toBe([])
        ->and($stale)->toBe([])
        ->and($included)->not->toBeEmpty();
});

it('renders every moved lens template against the host stubs', function (string $template, string $marker): void {
    app('view')->addLocation(dirname(__DIR__, 2).'/Fixtures/HostPartials');
    Collection::make('probe_people')->save();

    $html = View::file(dirname(__DIR__, 3)."/resources/views/{$template}.antlers.html", ['title' => 'Spock', 'slug' => 'spock'])->render();

    expect($html)->toContain($marker);
})->with([
    'general profile' => ['people/show', '[host:'],
    'academic lens' => ['people/academic/show', '[host:'],
    'institutional lens' => ['people/institutional/show', '[host:'],
    'researcher lens' => ['people/researcher/show', '[host:'],
    'clinical provider lens' => ['people/provider/show', '[host:'],
]);

it('serves the moved templates under their own names', function (string $view): void {
    expect(View::exists($view))->toBeTrue();
})->with([
    'general profile' => ['people.show'],
    'clinical lens' => ['people.provider.show'],
    'people block' => ['page_builder._uamsweb_block_people'],
    'people group page' => ['people_groups.show'],
]);

it('serves the people search card from the search view namespace', function (): void {
    expect(View::exists('aether-search::cards._people'))->toBeTrue();
});
