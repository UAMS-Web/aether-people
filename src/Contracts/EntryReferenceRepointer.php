<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Contracts;

use UAMSWeb\Aether\People\Support\ReferenceRepointSummary;

/**
 * Re-points the references to retired entries onto their survivors.
 *
 * The host binds it: a reference to a person can sit in any collection's entries, in global sets and in
 * taxonomy terms, so finding every one means walking the host's blueprints, which this add-on does not own.
 * The people merge calls `preview()` for the reconciliation screen and `repoint()` once the merge is confirmed.
 *
 * Nothing in the add-on binds it. A host that has not bound it gets a `BindingResolutionException` on the
 * first request to a merge endpoint, because the controller resolves `PeopleMergeService`, which needs it;
 * the `MergePeople` bulk action is still listed. The implementation must return this add-on's
 * `ReferenceRepointSummary`.
 */
interface EntryReferenceRepointer
{
    /**
     * Rewrite every reference to a retired entry so it points at the survivor, and persist the changes.
     *
     * @param  array<string, string>  $idMap  Old (retired) entry id => new (survivor) entry id.
     * @return ReferenceRepointSummary Counts of items changed and occurrences rewritten.
     */
    public function repoint(array $idMap): ReferenceRepointSummary;

    /**
     * Count what {@see repoint()} would change, writing nothing.
     *
     * @param  array<string, string>  $idMap  Old (retired) entry id => new (survivor) entry id.
     * @return ReferenceRepointSummary Counts of items that would change and occurrences found.
     */
    public function preview(array $idMap): ReferenceRepointSummary;
}
