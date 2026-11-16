<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\ReferenceRepointSummary;

covers(ReferenceRepointSummary::class);

/**
 * @command vendor/bin/pest tests/Unit/Merge/ReferenceRepointSummaryTest.php
 */
it('totals the entries, globals and terms changed, and leaves out the occurrences', function (): void {
    expect(new ReferenceRepointSummary(1, 20, 300, 9000)->itemsChanged())->toBe(321);
});

it('exposes each count and the total under the keys the page reads', function (): void {
    expect(new ReferenceRepointSummary(1, 2, 3, 9)->toArray())
        ->toBe(['entries' => 1, 'globals' => 2, 'terms' => 3, 'occurrences' => 9, 'items_changed' => 6]);
});
