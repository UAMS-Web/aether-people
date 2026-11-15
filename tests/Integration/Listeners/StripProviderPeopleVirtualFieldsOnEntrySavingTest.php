<?php

declare(strict_types=1);

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use UAMSWeb\Aether\People\Listeners\StripProviderPeopleVirtualFieldsOnEntrySaving;

uses(MockeryPHPUnitIntegration::class);

covers(StripProviderPeopleVirtualFieldsOnEntrySaving::class);

/**
 * The listener that removes the computed fields' virtual values from a provider or person entry before
 * it is saved.
 *
 * @command  vendor/bin/pest tests/Integration/Listeners/StripProviderPeopleVirtualFieldsOnEntrySavingTest.php
 */
it('strips pubmed_information from each publication select set on provider entry saving', function (): void {
    // Mock a clinical_providers collection entry carrying virtual PubMed fields
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')
        ->andReturn('clinical_providers');
    $entry->shouldReceive('get')
        ->with(
            'research_publication_select',
            []
        )
        ->andReturn([
            [
                'id' => 'a',
                'pubmed_information' => 'virtual',
            ],
        ]);
    $entry->shouldReceive('set')
        ->once()
        ->with(
            'research_publication_select',
            [
                [
                    'id' => 'a',
                ],
            ]
        );

    // Dispatch the entry saving listener to strip virtual publication fields
    $listener = new StripProviderPeopleVirtualFieldsOnEntrySaving;
    $listener->handle(new EntrySaving($entry));
});

it('strips virtual activity computeds from people certification sets on entry saving', function (): void {
    // Mock a people collection entry carrying virtual certification computeds
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')
        ->andReturn('people');
    $entry->shouldReceive('get')
        ->andReturnUsing(
            fn (string $key, mixed $default = null): mixed => match ($key) {
                'research_publication_select' => [],
                'certifications' => [
                    [
                        'board' => 'x',
                        'activity_valid_for' => 'v',
                        'activity_active_query' => 'v2',
                    ],
                ],
                default => $default,
            }
        );
    $entry->shouldReceive('set')
        ->once()
        ->with(
            'certifications',
            [
                [
                    'board' => 'x',
                ],
            ]
        );

    // Dispatch the entry saving listener to strip virtual certification fields
    $listener = new StripProviderPeopleVirtualFieldsOnEntrySaving;
    $listener->handle(new EntrySaving($entry));
});

it('leaves an entry outside the provider and people collections untouched', function (): void {
    // An unsupported entry is never read or written, so a publication set it
    // carries keeps its PubMed field.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn('posts');
    $entry->shouldNotReceive('get');
    $entry->shouldNotReceive('set');

    (new StripProviderPeopleVirtualFieldsOnEntrySaving)->handle(new EntrySaving($entry));
});

it('strips certification computeds when the sets arrive as a collection', function (): void {
    // A replicator value can be an Illuminate collection; it is unwrapped to an
    // array before the virtual fields are removed.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn('people');
    $entry->shouldReceive('get')
        ->andReturnUsing(fn (string $key, mixed $default = null): mixed => match ($key) {
            'research_publication_select' => [],
            'certifications' => collect([
                ['board' => 'x', 'activity_valid_for' => 'v', 'activity_active_query' => 'v2'],
            ]),
            default => $default,
        });
    $entry->shouldReceive('set')->once()->with('certifications', [['board' => 'x']]);

    (new StripProviderPeopleVirtualFieldsOnEntrySaving)->handle(new EntrySaving($entry));
});

it('writes nothing back for a people entry with no certifications', function (): void {
    // An empty certification list is left as it is rather than rewritten.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn('people');
    $entry->shouldReceive('get')
        ->andReturnUsing(fn (string $key, mixed $default = null): mixed => $key === 'certifications' ? [] : $default);
    $entry->shouldNotReceive('set');

    (new StripProviderPeopleVirtualFieldsOnEntrySaving)->handle(new EntrySaving($entry));
});
