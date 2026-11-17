<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Merge\MergeReconciliation;

covers(MergeReconciliation::class);

/**
 * One reconcilable field, conflicting or not.
 *
 * @return array{handle: string, display: string, type: string, conflict: bool, values: list<array{entry_id: string, value: mixed, display: string, has_value: bool}>, default_source: string}
 */
function reconciliationField(string $handle, bool $conflict): array
{
    return [
        'handle' => $handle,
        'display' => ucfirst($handle),
        'type' => 'text',
        'conflict' => $conflict,
        'values' => [
            ['entry_id' => 'surv', 'value' => 'A', 'display' => 'A', 'has_value' => true],
            ['entry_id' => 'lose', 'value' => 'B', 'display' => 'B', 'has_value' => true],
        ],
        'default_source' => 'surv',
    ];
}

/**
 * The candidate and site rows a reconciliation carries.
 *
 * @return array{candidates: list<array{id: string, title: string, slug: ?string, site: string, url: ?string, edit_url: ?string, is_survivor: bool}>, sites: array<string, list<array{entry_id: string, slug: ?string, url: ?string, is_survivor: bool}>>}
 */
function reconciliationRows(): array
{
    return [
        'candidates' => [
            ['id' => 'surv', 'title' => 'Survivor', 'slug' => 'survivor', 'site' => 'default', 'url' => '/people/survivor', 'edit_url' => null, 'is_survivor' => true],
            ['id' => 'lose', 'title' => 'Loser', 'slug' => null, 'site' => 'default', 'url' => null, 'edit_url' => '/cp/lose', 'is_survivor' => false],
        ],
        'sites' => [
            'default' => [['entry_id' => 'surv', 'slug' => 'survivor', 'url' => '/people/survivor', 'is_survivor' => true]],
        ],
    ];
}

/**
 * @command vendor/bin/pest tests/Unit/Merge/MergeReconciliationTest.php
 */
it('counts only the fields whose candidates disagree', function (): void {
    $rows = reconciliationRows();
    $reconciliation = new MergeReconciliation('surv', $rows['candidates'], [
        reconciliationField('bio', true),
        reconciliationField('email', false),
        reconciliationField('nickname', true),
    ], $rows['sites']);

    expect($reconciliation->conflictCount())->toBe(2);
});

it('counts no conflicts when there are no fields', function (): void {
    $rows = reconciliationRows();

    expect(new MergeReconciliation('surv', $rows['candidates'], [], $rows['sites'])->conflictCount())->toBe(0);
});

it('exposes the survivor, candidates, fields, sites and conflict count as the page reads them', function (): void {
    $rows = reconciliationRows();
    $fields = [reconciliationField('bio', true), reconciliationField('email', false)];

    $array = new MergeReconciliation('surv', $rows['candidates'], $fields, $rows['sites'])->toArray();

    expect($array)->toBe([
        'survivor_id' => 'surv',
        'candidates' => $rows['candidates'],
        'fields' => $fields,
        'sites' => $rows['sites'],
        'conflict_count' => 1,
    ]);
});
