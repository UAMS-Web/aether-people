<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Merge;

use UAMSWeb\Aether\People\Support\ReferenceRepointSummary;

/**
 * Outcome of a people merge (UAMS-Web/uams-statamic-archive#838), in both preview (dry-run) and committed
 * form. In preview the counts describe what *would* happen; on commit they
 * describe what *did*. The shape is identical so the UI can show the same
 * summary on the confirm screen and the success screen.
 *
 * @phpstan-type RedirectPlanShape array{site: string, source: string, target: string, response: string}
 */
class MergeResult
{
    /**
     * @param  string  $survivorId  The entry that persists (identity preserved).
     * @param  bool  $dryRun  True for a preview; false once the merge is committed.
     * @param  list<string>  $appliedFields  Field handles whose value was taken from a non-survivor source.
     * @param  ReferenceRepointSummary  $references  Inbound references re-pointed (or to be re-pointed).
     * @param  int  $redirectsCreated  Number of redirect rows created (or planned).
     * @param  list<RedirectPlanShape>  $redirects  The redirect rows themselves (loser URL → survivor URL).
     * @param  list<string>  $retiredIds  Entry ids removed (or to be removed) after the merge.
     */
    public function __construct(
        public readonly string $survivorId,
        public readonly bool $dryRun,
        public readonly array $appliedFields,
        public readonly ReferenceRepointSummary $references,
        public readonly int $redirectsCreated,
        public readonly array $redirects,
        public readonly array $retiredIds,
    ) {}

    /**
     * Array shape for the JSON response consumed by the reconciliation page.
     *
     * @return array{survivor_id: string, dry_run: bool, applied_fields: list<string>, references: array{entries: int, globals: int, terms: int, occurrences: int, items_changed: int}, redirects_created: int, redirects: list<RedirectPlanShape>, retired_ids: list<string>}
     */
    public function toArray(): array
    {
        return [
            'survivor_id' => $this->survivorId,
            'dry_run' => $this->dryRun,
            'applied_fields' => $this->appliedFields,
            'references' => $this->references->toArray(),
            'redirects_created' => $this->redirectsCreated,
            'redirects' => $this->redirects,
            'retired_ids' => $this->retiredIds,
        ];
    }
}
