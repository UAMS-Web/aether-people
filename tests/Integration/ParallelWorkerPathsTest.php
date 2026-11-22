<?php

declare(strict_types=1);

use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use UAMSWeb\Aether\People\Tests\TestCase;

/**
 * The directories each parallel test worker writes to are its own
 * (UAMS-Web/aether-people#149).
 *
 * @command vendor/bin/pest --parallel tests/Integration/ParallelWorkerPathsTest.php
 */
it('names a search index directory per worker token', function (mixed $token, ?string $expected): void {
    expect(TestCase::searchIndexPath($token))->toBe($expected === null ? null : storage_path($expected));
})->with([
    'string token' => ['3', 'statamic/search-3'],
    'integer token' => [7, 'statamic/search-7'],
    'no token' => [null, null],
    'unusable token' => [['3'], null],
]);

it('points the local search index at the worker directory the token names', function (): void {
    $expected = TestCase::searchIndexPath($_SERVER['TEST_TOKEN'] ?? null);

    // Outside a parallel run there is no token, and Statamic's own directory stands
    expect(config('statamic.search.drivers.local.path'))->toBe($expected ?? storage_path('statamic/search'));
});

it('writes the search index of a saved entry under the configured directory', function (): void {
    Collection::make('probe_people')->save();
    $entry = mustBe(EntryInstance::class, Entry::make(), 'an entry');
    $entry->collection('probe_people');
    $entry->slug('probe-person');
    $entry->data(['title' => 'Probe Person']);
    $entry->save();

    $path = config('statamic.search.drivers.local.path');

    expect($path)->toBeString()
        ->and(is_dir(mustBeString($path, 'search path')))->toBeTrue();
});
