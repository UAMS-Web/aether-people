<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Site;
use Statamic\Fields\Value;
use Statamic\Sites\Sites;

/**
 * Coverage for `{{ people_lens_url }}` (UAMS-Web/uams-statamic-archive#571) — the tag that resolves a canonical
 * `people` slug to the URL it renders at through a named lens on the CURRENT site.
 *
 * The clinical directory card uses it so `/provider/{slug}` is defined in exactly
 * one place (`config/people_lenses.php`, which also drives the route registration in
 * `ServiceProvider::registerLensRoutes()`) rather than being hard-coded in a template where a config change
 * would silently break it.
 *
 * @command vendor/bin/pest tests/Integration/Tags/PeopleLensUrlTagTest.php
 */
beforeEach(function (): void {
    // The canonical site at the root and the clinical site under `/health`
    Site::setSites([
        'default' => ['name' => 'Default', 'locale' => 'en_US', 'url' => '/'],
        'health' => ['name' => 'Health', 'locale' => 'en_US', 'url' => '/health/'],
    ]);
});

afterEach(function (): void {
    Site::setCurrent('default');
});

/**
 * Render the tag with the given Antlers scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderLensUrl(string $antlers, array $scope = []): string
{
    return \trim((string) Antlers::parse($antlers, $scope, true));
}

it('builds the clinical lens URL under the health site prefix', function (): void {
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='provider' slug='leonard-mccoy' }}"))
        ->toBe('/health/provider/leonard-mccoy');
});

it('reads the slug from the surrounding scope when no slug param is given', function (): void {
    // This is how the directory card loop calls it — the `{{ providers }}` scope
    // already carries the entry's slug.
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='provider' }}", ['slug' => 'christine-chapel']))
        ->toBe('/health/provider/christine-chapel');
});

it('returns an empty string on a site the lens is not active on', function (): void {
    // The clinical lens is registered for `health` only. On the canonical site the
    // route does not exist, so the tag must yield nothing and let the caller fall
    // back to the person's own permalink rather than emit a URL that would 404.
    Site::setCurrent('default');

    expect(renderLensUrl("{{ people_lens_url lens='provider' slug='leonard-mccoy' }}"))
        ->toBe('');
});

it('resolves an every-site lens at the canonical root prefix', function (): void {
    // The academic lens is registered on `['*']`, so it resolves wherever it is asked.
    Site::setCurrent('default');

    expect(renderLensUrl("{{ people_lens_url lens='academic' slug='spock' }}"))
        ->toBe('/faculty/spock');
});

it('returns an empty string for an unknown lens', function (): void {
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='klingon' slug='leonard-mccoy' }}"))
        ->toBe('');
});

it('returns an empty string with no slug to build from', function (): void {
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='provider' }}"))
        ->toBe('');
});

it('percent-encodes a slug so it cannot break out of the path', function (): void {
    // A CP-authored slug is already URL-safe; the WordPress importer writes raw YAML
    // that bypasses that validation, so the tag encodes defensively.
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='provider' slug='mccoy/../..' }}"))
        ->toBe('/health/provider/mccoy%2F..%2F..');
});

it('tracks the configured route rather than a hard-coded path', function (): void {
    // The whole point of the tag: repoint the lens in config and the link follows.
    Site::setCurrent('health');

    config(['people_lenses.lenses.provider.route' => 'clinician/{slug}']);

    expect(renderLensUrl("{{ people_lens_url lens='provider' slug='leonard-mccoy' }}"))
        ->toBe('/health/clinician/leonard-mccoy');
});

it('trims whitespace around the lens and the slug', function (): void {
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='  provider  ' slug='  leonard-mccoy  ' }}"))
        ->toBe('/health/provider/leonard-mccoy');
});

it('reads a numeric slug and refuses a non-scalar one', function (): void {
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='provider' :slug='slug' }}", ['slug' => 42]))->toBe('/health/provider/42')
        ->and(renderLensUrl("{{ people_lens_url lens='provider' :slug='slug' }}", ['slug' => ['a']]))->toBe('')
        ->and(renderLensUrl("{{ people_lens_url lens='provider' :slug='slug' }}", ['slug' => null]))->toBe('');
});

it('unwraps an augmented value for the slug', function (): void {
    Site::setCurrent('health');

    $slug = new Value('chapel', 'slug');

    expect(renderLensUrl("{{ people_lens_url lens='provider' :slug='slug' }}", ['slug' => $slug]))
        ->toBe('/health/provider/chapel');
});

it('joins a site prefix with a trailing slash and a route with a leading one without doubling either', function (): void {
    Site::setSites([
        'default' => ['name' => 'Default', 'locale' => 'en_US', 'url' => '/'],
        'health' => ['name' => 'Health', 'locale' => 'en_US', 'url' => '/health/'],
    ]);
    config()->set('people_lenses.lenses.provider.route', '/provider/{slug}');
    Site::setCurrent('health');

    expect(renderLensUrl("{{ people_lens_url lens='provider' slug='x' }}"))->toBe('/health/provider/x');
});

it('returns an empty string when the lens or the slug is blank', function (string $lens, string $slug): void {
    Site::setCurrent('health');

    expect(renderLensUrl('{{ people_lens_url :lens="lens" :slug="slug" }}', ['lens' => $lens, 'slug' => $slug]))->toBe('');
})->with([
    'blank lens' => ['', 'leonard-mccoy'],
    'blank slug' => ['provider', ''],
]);

it('returns an empty string when the current site has a handle or URL that is not a string', function (mixed $handle, mixed $url): void {
    $site = new class($handle, $url) extends Statamic\Sites\Site
    {
        public function __construct(private readonly mixed $fakeHandle, private readonly mixed $fakeUrl)
        {
            parent::__construct('health', ['name' => 'Health', 'locale' => 'en_US', 'url' => '/health/']);
        }

        /**
         * @return mixed
         */
        #[Override]
        public function handle()
        {
            return $this->fakeHandle;
        }

        /**
         * @return mixed
         */
        #[Override]
        public function url()
        {
            return $this->fakeUrl;
        }
    };

    $sites = new class($site) extends Sites
    {
        public function __construct(private readonly Statamic\Sites\Site $fakeCurrent)
        {
            parent::__construct();
            $this->setSites(['health' => ['name' => 'Health', 'locale' => 'en_US', 'url' => '/health/']]);
        }

        /**
         * @return mixed
         */
        #[Override]
        public function current()
        {
            return $this->fakeCurrent;
        }
    };

    Site::swap($sites);

    expect(renderLensUrl("{{ people_lens_url lens='provider' slug='x' }}"))->toBe('');
})->with([
    'handle is not a string' => [5, '/health/'],
    'url is not a string' => ['health', null],
]);

it('unwraps an augmented value read from the surrounding scope', function (): void {
    Site::setCurrent('health');

    $scope = ['slug' => new Value('chapel', 'slug')];

    expect(renderLensUrl("{{ people_lens_url lens='provider' }}", $scope))->toBe('/health/provider/chapel');
});
