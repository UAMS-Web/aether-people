<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Merge\MergeResult;
use UAMSWeb\Aether\People\Support\ReferenceRepointSummary;

covers(MergeResult::class);

/**
 * @command vendor/bin/pest tests/Unit/Merge/MergeResultTest.php
 */
it('exposes every part of the outcome as the page reads it', function (): void {
    $redirects = [['site' => 'default', 'source' => '/people/jane-2', 'target' => '/people/jane', 'response' => '301']];

    $result = new MergeResult('surv', true, ['bio', 'nickname'], new ReferenceRepointSummary(1, 2, 3, 9), 1, $redirects, ['lose']);

    expect($result->toArray())->toBe([
        'survivor_id' => 'surv',
        'dry_run' => true,
        'applied_fields' => ['bio', 'nickname'],
        'references' => ['entries' => 1, 'globals' => 2, 'terms' => 3, 'occurrences' => 9, 'items_changed' => 6],
        'redirects_created' => 1,
        'redirects' => $redirects,
        'retired_ids' => ['lose'],
    ]);
});

it('reports a committed merge as not a dry run', function (): void {
    $result = new MergeResult('surv', false, [], new ReferenceRepointSummary(0, 0, 0, 0), 0, [], ['lose']);

    expect($result->toArray()['dry_run'])->toBeFalse();
});
