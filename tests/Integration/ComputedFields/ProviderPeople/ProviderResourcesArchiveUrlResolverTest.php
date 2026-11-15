<?php

declare(strict_types=1);

use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Path;
use Statamic\Facades\Stache;
use Statamic\Stache\Stores\Store;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderResourcesArchiveUrlResolver;

covers(ProviderResourcesArchiveUrlResolver::class);

/**
 * The clinical provider lens's "more resources" destination (UAMS-Web/uams-statamic-archive#1692).
 *
 * Real entries for the landing page: the archive root comes from the collection's mount, which
 * only exists on a persisted collection. The provider is an unsaved entry, because the resolver
 * reads only its slug and saving one would resolve the host's `person` blueprint. The site keeps
 * the half of the original file that tests the host's `ClinicalResourceFilter` tag (the archive's
 * notice naming the provider), which is a host class.
 *
 * @command  vendor/bin/pest tests/Integration/ComputedFields/ProviderPeople/ProviderResourcesArchiveUrlResolverTest.php
 */
beforeEach(function (): void {
    // Keep the Stache out of the shared fixtures directory, one directory per test
    $root = storage_path('framework/testing/stache/'.uniqid('archive-url-', true).'/dev-null');
    Stache::stores()->each(function (Store $store) use ($root): void {
        $store->directory(Path::tidy($root.'/'.$store->key()));
    });
    Stache::clear();

    // The `people` collection the unsaved providers belong to
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->save();

    $pages = CollectionFacade::make('pages');
    $pages->title('Pages');
    $pages->routes('/{slug}');
    $pages->save();
});

/**
 * Persist the resources collection, optionally mounted on a landing page.
 */
function archiveUrlCollection(?string $mountId = null): void
{
    $resources = CollectionFacade::make('clinical_resources');
    $resources->title('Clinical Resources');

    if ($mountId !== null) {
        $resources->mount($mountId);
    }

    $resources->save();
}

/**
 * Persist the archive landing page and return its id.
 */
function archiveUrlLanding(bool $published = true): string
{
    $entry = mustBe(EntryInstance::class, Entry::make(), 'an entry');
    $entry->collection('pages');
    $entry->id('resources-landing');
    $entry->slug('resources');
    $entry->published($published);
    $entry->data(['title' => 'Clinical Resources']);
    $entry->saveQuietly();

    return 'resources-landing';
}

/**
 * Build one provider with the given slug, without saving it.
 */
function archiveUrlProvider(string $slug): EntryInstance
{
    $entry = new EntryInstance;
    $entry->collection('people');
    $entry->id($slug);
    $entry->slug($slug);
    $entry->data(['title' => ucfirst($slug)]);

    return $entry;
}

// --- The CTA destination -------------------------------------------------------

it('points at the archive mount, carrying the provider slug', function (): void {
    archiveUrlCollection(archiveUrlLanding());
    $provider = archiveUrlProvider('mccoy');

    $url = app(ProviderResourcesArchiveUrlResolver::class)->resolve($provider);

    expect($url)->toContain('/resources')
        ->and($url)->toEndWith('?provider=mccoy');
});

it('reads the archive root from the mount rather than a hard-coded path', function (): void {
    // Moving or renaming the landing page must not leave the profile pointing at a
    // 404, which is the whole reason this is not the literal `/health/resources`.
    $landing = mustBe(EntryInstance::class, Entry::make(), 'an entry');
    $landing->collection('pages');
    $landing->id('moved-landing');
    $landing->slug('patient-resources');
    $landing->published(true);
    $landing->data(['title' => 'Patient Resources']);
    $landing->saveQuietly();

    archiveUrlCollection('moved-landing');

    expect(app(ProviderResourcesArchiveUrlResolver::class)->resolve(archiveUrlProvider('spock')))
        ->toContain('/patient-resources');
});

it('yields no destination when the collection is unmounted', function (): void {
    archiveUrlCollection();

    expect(app(ProviderResourcesArchiveUrlResolver::class)->resolve(archiveUrlProvider('uhura')))->toBe('');
});

it('yields no destination when the archive landing is unpublished', function (): void {
    // An unpublished mount would 302 the reader onto a 404 - no link beats a broken
    // link, and the template renders no CTA on ''.
    archiveUrlCollection(archiveUrlLanding(published: false));

    expect(app(ProviderResourcesArchiveUrlResolver::class)->resolve(archiveUrlProvider('scotty')))->toBe('');
});

it('emits the slug verbatim, and encodes anything that would break the query string', function (): void {
    // Statamic normalizes a slug on save, so a stored one is always `[a-z0-9-]` and never
    // actually needs encoding - the `rawurlencode` is belt-and-braces against a slug
    // written straight to the file. What matters here is that a NORMAL slug survives
    // untouched, so the archive's lookup sees exactly what the CTA sent.
    archiveUrlCollection(archiveUrlLanding());

    expect(app(ProviderResourcesArchiveUrlResolver::class)->resolve(archiveUrlProvider('jean-luc-picard')))
        ->toEndWith('?provider=jean-luc-picard');
});

it('yields no destination for a provider with no slug', function (?string $slug): void {
    archiveUrlCollection(archiveUrlLanding());

    // Built rather than saved: a save would give the entry a slug.
    $provider = mustBe(EntryInstance::class, Entry::make(), 'an entry');
    $provider->collection('people');
    $provider->slug($slug);

    expect(app(ProviderResourcesArchiveUrlResolver::class)->resolve($provider))->toBe('');
})->with([
    'no slug at all' => [null],
    'an empty slug' => [''],
    // Non-empty as stored, but `slug()` normalizes it to ''.
    'a slug of punctuation only' => ['!!!'],
]);

it('yields no destination when the resources collection does not exist', function (): void {
    expect(app(ProviderResourcesArchiveUrlResolver::class)->resolve(archiveUrlProvider('chekov')))->toBe('');
});

it('yields no destination when the mounted landing has no URL', function (): void {
    // A collection without a route gives its entries no URL, so the landing is
    // published and still has nowhere to send the reader.
    $unrouted = CollectionFacade::make('unrouted');
    $unrouted->title('Unrouted');
    $unrouted->save();

    $landing = mustBe(EntryInstance::class, Entry::make(), 'an entry');
    $landing->collection('unrouted');
    $landing->id('unrouted-landing');
    $landing->slug('resources');
    $landing->published(true);
    $landing->data(['title' => 'Clinical Resources']);
    $landing->saveQuietly();

    archiveUrlCollection('unrouted-landing');

    expect(app(ProviderResourcesArchiveUrlResolver::class)->resolve(archiveUrlProvider('rand')))->toBe('');
});
