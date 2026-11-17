<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Contracts;

/**
 * Writes the redirects from retired people entries to their survivor.
 *
 * The host binds it: where a site keeps its redirects (a globals set, a table, a file) is the host's
 * decision, and the add-on does not own it. The people merge calls `count()` for the reconciliation
 * screen's preview and `write()` once the merge is confirmed, so the preview reports what the commit
 * writes. A host that wants no redirects binds an implementation that writes nothing and counts zero.
 *
 * Nothing in the add-on binds it. A merge on a host that has not bound it is refused with a
 * `PeopleMergeException` that names this interface, before any entry is written or retired.
 */
interface MergeRedirectWriter
{
    /**
     * Count the redirects {@see write()} would create for the plan, writing nothing.
     *
     * @param  list<array{site: string, source: string, target: string, response: string}>  $plan  One row per retired URL.
     * @return int Number of redirects a write would create.
     */
    public function count(array $plan): int;

    /**
     * Create the redirects in the plan and persist them.
     *
     * @param  list<array{site: string, source: string, target: string, response: string}>  $plan  One row per retired URL.
     * @return int Number of redirects created.
     */
    public function write(array $plan): int;
}
