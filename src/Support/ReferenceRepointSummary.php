<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use UAMSWeb\Aether\People\Contracts\EntryReferenceRepointer;

/**
 * Immutable tally of an {@see EntryReferenceRepointer} scan: how many items held
 * references to the retired entries and how many individual occurrences were
 * (or would be) rewritten. Drives both the merge preview and the post-merge
 * result summary (UAMS-Web/uams-statamic-archive#838).
 */
class ReferenceRepointSummary
{
    /**
     * @param  int  $entries  Number of entries changed.
     * @param  int  $globals  Number of global set localizations changed.
     * @param  int  $terms  Number of taxonomy terms changed.
     * @param  int  $occurrences  Total individual reference occurrences rewritten.
     */
    public function __construct(
        public readonly int $entries,
        public readonly int $globals,
        public readonly int $terms,
        public readonly int $occurrences,
    ) {}

    /**
     * Total number of distinct content items (entries + globals + terms) changed.
     */
    public function itemsChanged(): int
    {
        return $this->entries + $this->globals + $this->terms;
    }

    /**
     * Array shape for JSON responses consumed by the reconciliation UI.
     *
     * @return array{entries: int, globals: int, terms: int, occurrences: int, items_changed: int}
     */
    public function toArray(): array
    {
        return [
            'entries' => $this->entries,
            'globals' => $this->globals,
            'terms' => $this->terms,
            'occurrences' => $this->occurrences,
            'items_changed' => $this->itemsChanged(),
        ];
    }
}
