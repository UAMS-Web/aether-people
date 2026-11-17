<?php

declare(strict_types=1);

use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Stache;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\FieldsetRepository;
use Statamic\Fields\Value;
use UAMSWeb\Aether\People\Modifiers\ClinicalTitlesCarried;
use UAMSWeb\Aether\People\Tests\Fixtures\HostStandIns\SanitizeStandIn;

covers(ClinicalTitlesCarried::class);

/**
 * The Find-a-Doc filter's clinical title select offers only titles the block's own providers carry
 * (UAMS-Web/aether-people#119, decided on UAMS-Web/uams-statamic#258).
 *
 * The filter partial is rendered with the ids the Providers block derives from its own list, and the
 * modifier that derives them is run over entries and augmented arrays.
 *
 * @command  vendor/bin/pest tests/Integration/Views/FadProvidersTitleFilterTest.php
 */
beforeEach(function (): void {
    Stache::clear();
    app('view')->addLocation(dirname(__DIR__, 2).'/Fixtures/HostPartials');
    app(FieldsetRepository::class)->setDirectory(dirname(__DIR__, 2).'/Fixtures/HostFieldsets');
    app('statamic.modifiers')->put('sanitize', SanitizeStandIn::class);

    // The collections and taxonomies the filter partial reads
    foreach (['clinical_specializations', 'people', 'clinical_expertise'] as $handle) {
        /** @var Collection $collection */
        $collection = CollectionFacade::make($handle);
        $collection->save();
    }

    foreach (['regions', 'location_types'] as $handle) {
        /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
        $taxonomy = Taxonomy::make($handle);
        $taxonomy->save();
    }

    foreach (['ct-cardiology' => 'Cardiology', 'ct-oncology' => 'Oncology', 'ct-urology' => 'Urology'] as $id => $title) {
        fadTitleEntry('clinical_specializations', $id, substr($id, 3), ['title' => $title])->save();
    }
});

/**
 * An unsaved entry in a collection.
 *
 * @param  array<string, mixed>  $data
 */
function fadTitleEntry(string $collection, ?string $id, string $slug, array $data): Entry
{
    /** @var Entry $entry */
    $entry = EntryFacade::make();
    $entry->collection($collection);

    if ($id !== null) {
        $entry->id($id);
    }

    $entry->slug($slug);
    $entry->data($data);

    return $entry;
}

/**
 * Render the filter partial as the Providers block does.
 *
 * @param  list<string>  $ids  The title ids the block's providers carry.
 * @return list<string> The slugs of the title options, in order.
 */
function titleOptionSlugs(array $ids, string $collection = 'clinical_providers'): array
{
    $html = (string) Antlers::parse(
        '{{ partial:page_builder/components/block_fad_filter fad_filter_collection="'.$collection.'" :fad_filter_title_ids="ids" }}',
        ['ids' => $ids],
        true,
    );

    // The title select is the one bound to the specialty facet
    preg_match('/data-fad-facet="specialty".*?<\/select>/s', $html, $select);
    preg_match_all('/<option value="([^"]+)"/', $select[0] ?? '', $matches);

    return $matches[1];
}

it('offers a title a provider carries and omits one none carries', function (): void {
    $slugs = titleOptionSlugs(['ct-cardiology', 'ct-urology']);

    expect($slugs)->toContain('cardiology')
        ->and($slugs)->not->toContain('oncology');
});

it('keeps the title order and slug values', function (): void {
    expect(titleOptionSlugs(['ct-urology', 'ct-cardiology', 'ct-oncology']))->toBe(['cardiology', 'oncology', 'urology']);
});

it('offers no title when the block has no providers carrying one', function (): void {
    expect(titleOptionSlugs([]))->toBe([]);
});

it('renders no title select outside the providers block', function (): void {
    expect(titleOptionSlugs(['ct-cardiology'], 'clinical_locations'))->toBe([]);
});

it('collects the distinct title ids across entries, first seen first', function (): void {
    $provider = fn (array $titles): Entry => fadTitleEntry('people', null, 'p', ['clinical_specializations' => $titles]);

    $ids = (new ClinicalTitlesCarried)->index(
        [$provider(['ct-urology']), $provider(['ct-urology', 'ct-cardiology']), $provider(['ct-cardiology']), $provider([])],
        [],
        [],
    );

    expect($ids)->toBe(['ct-urology', 'ct-cardiology']);
});

it('reads augmented arrays whose titles are entries or arrays with an id', function (): void {
    $ids = (new ClinicalTitlesCarried)->index(
        [
            ['clinical_specializations' => [EntryFacade::find('ct-oncology'), ['id' => 'ct-urology'], ['id' => ''], 7, 'ct-oncology', [], null]],
            ['clinical_specializations' => null],
            ['title' => 'No titles'],
            'not a provider',
        ],
        [],
        [],
    );

    expect($ids)->toBe(['ct-oncology', 'ct-urology', '7']);
});

it('reads the stored ids from a field value that is still wrapped', function (): void {
    $ids = (new ClinicalTitlesCarried)->index(
        [['clinical_specializations' => new Value(['ct-oncology', 'ct-urology'], 'clinical_specializations')]],
        [],
        [],
    );

    expect($ids)->toBe(['ct-oncology', 'ct-urology']);
});

it('answers an empty list for a value that is not a list of providers', function (): void {
    expect((new ClinicalTitlesCarried)->index(null, [], []))->toBe([]);
});

it('derives the ids from a provider list the way the Providers block passes them', function (): void {
    $provider = fn (array $titles): Entry => fadTitleEntry('people', null, 'p', ['clinical_specializations' => $titles]);

    $html = (string) Antlers::parse(
        '{{ partial:page_builder/components/block_fad_filter fad_filter_collection="clinical_providers" :fad_filter_title_ids="items | clinical_titles_carried" }}',
        ['items' => [$provider(['ct-urology']), $provider(['ct-urology', 'ct-oncology'])]],
        true,
    );

    expect($html)->toContain('<option value="oncology">')
        ->and($html)->toContain('<option value="urology">')
        ->and($html)->not->toContain('<option value="cardiology">');
});

it('passes the ids from its own provider list in the Providers block', function (): void {
    $block = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/page_builder/_uamsweb_block_fad_providers.antlers.html');

    // The block's own `items` are the providers it renders, so the options follow what the block shows
    expect($block)->toContain(':fad_filter_title_ids="items | clinical_titles_carried"');
});
