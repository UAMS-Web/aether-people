<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests;

use Illuminate\Foundation\Application;
use Statamic\Testing\AddonTestCase;
use UAMSWeb\Aether\People\Contracts\EntryReferenceRepointer;
use UAMSWeb\Aether\People\Contracts\LocationRegions;
use UAMSWeb\Aether\People\Contracts\MergeRedirectWriter;
use UAMSWeb\Aether\People\Contracts\PublicationCitations;
use UAMSWeb\Aether\People\Merge\RedirectsGlobalWriter;
use UAMSWeb\Aether\People\ServiceProvider;
use UAMSWeb\Aether\People\Tests\Support\HostFakes;

/**
 * Base class for the Integration suite: boots a minimal Statamic through
 * Orchestra Testbench with this add-on's provider registered.
 */
abstract class TestCase extends AddonTestCase
{
    /**
     * The add-on provider Statamic's test case registers before each test.
     *
     * @var string Class name of this add-on's service provider.
     */
    protected string $addonServiceProvider = ServiceProvider::class;

    /**
     * Give each parallel worker its own license-check cache, after Statamic's
     * test case sets the shared one.
     *
     * Every authenticated Control Panel page asks Statamic's license check, which
     * takes a file lock in the `outpost` cache store. Statamic's `AddonTestCase`
     * points that store at one directory for every worker, so on Windows a worker
     * opening the lock file while another holds it fails with "Permission denied",
     * failing whichever test was rendering a page (#126). A directory per worker
     * keeps the lock and the cached response apart, and still keeps the response
     * between tests, so the check does not reach the network on every test.
     *
     * Statamic sets the store here rather than in `defineEnvironment()`, which runs
     * first, so the override must follow the parent call.
     *
     * @param  Application  $app  The application being configured.
     */
    #[\Override]
    protected function getEnvironmentSetUp($app): void
    {
        // Let Statamic's test case configure the environment first
        parent::getEnvironmentSetUp($app);

        // Point the license-check cache and the local search index at this worker's directories, if any
        $token = $_SERVER['TEST_TOKEN'] ?? null;
        $path = self::outpostCachePath($token);
        if ($path !== null) {
            $app['config']->set('cache.stores.outpost.path', $path);
        }

        $searchPath = self::searchIndexPath($token);
        if ($searchPath !== null) {
            $app['config']->set('statamic.search.drivers.local.path', $searchPath);
        }
    }

    /**
     * The local search index directory for a parallel worker token, or null
     * outside a parallel run.
     *
     * Saving an entry updates the `default` search index, and Statamic's local driver
     * creates the index directory with a plain `mkdir` that throws when another worker
     * created it first, so one directory shared by every worker failed whichever test
     * saved an entry at that moment with "mkdir(): File exists" (#149). A directory per
     * worker removes the race.
     *
     * @param  mixed  $token  The parallel worker token, or null outside a parallel run.
     * @return ?string The worker's index directory, or null without a usable token.
     */
    public static function searchIndexPath(mixed $token): ?string
    {
        return is_string($token) || is_int($token) ? storage_path("statamic/search-{$token}") : null;
    }

    /**
     * The license-check cache directory for a parallel worker token, or null
     * outside a parallel run.
     *
     * @param  mixed  $token  The parallel worker token, or null outside a parallel run.
     * @return ?string The worker's cache directory, or null without a usable token.
     */
    public static function outpostCachePath(mixed $token): ?string
    {
        return is_string($token) || is_int($token) ? storage_path("framework/cache/outpost-data-{$token}") : null;
    }

    /**
     * Give the application an encryption key, which a Control Panel request
     * needs for its session, and register the REST and GraphQL routes.
     *
     * @param  Application  $app  The application being configured.
     */
    protected function defineEnvironment($app): void
    {
        // Set an encryption key for the session
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));

        // Statamic registers the REST and GraphQL routes while booting, and only when
        // enabled, so they are enabled here; which resources they expose, and the Pro
        // edition they require, are read per request and left to each test.
        $app['config']->set('statamic.api.enabled', true);
        $app['config']->set('statamic.graphql.enabled', true);

        // Bind the contracts the host binds, to stand-ins that return what the host returns for
        // content it has nothing for
        $app->singleton(LocationRegions::class, fn (): LocationRegions => HostFakes::locationRegions());
        $app->singleton(PublicationCitations::class, fn (): PublicationCitations => HostFakes::publicationCitations());
        $app->singleton(EntryReferenceRepointer::class, fn (): EntryReferenceRepointer => HostFakes::entryReferenceRepointer());
        $app->singleton(MergeRedirectWriter::class, fn (): MergeRedirectWriter => new RedirectsGlobalWriter);
    }
}
