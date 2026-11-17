<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Merge;

/**
 * The field-by-field comparison the reconciliation UI renders before a people
 * merge (UAMS-Web/uams-statamic-archive#838): the candidate entries, the default survivor, every reconcilable
 * field with each candidate's value and a conflict flag, and a per-site map so
 * the operator can see the multisite footprint before committing.
 *
 * @phpstan-type CandidateShape array{id: string, title: string, slug: ?string, site: string, url: ?string, edit_url: ?string, is_survivor: bool}
 * @phpstan-type FieldValueShape array{entry_id: string, value: mixed, display: string, has_value: bool}
 * @phpstan-type FieldShape array{handle: string, display: string, type: string, conflict: bool, values: list<FieldValueShape>, default_source: string}
 * @phpstan-type SiteRowShape array{entry_id: string, slug: ?string, url: ?string, is_survivor: bool}
 */
class MergeReconciliation
{
    /**
     * @param  string  $survivorId  Default survivor entry id (the operator may change it).
     * @param  list<CandidateShape>  $candidates  One row per distinct person being merged.
     * @param  list<FieldShape>  $fields  Reconcilable fields with per-candidate values + conflict flag.
     * @param  array<string, list<SiteRowShape>>  $sites  Per-site footprint, keyed by site handle.
     */
    public function __construct(
        public readonly string $survivorId,
        public readonly array $candidates,
        public readonly array $fields,
        public readonly array $sites,
    ) {}

    /**
     * Number of fields whose candidates disagree (need an explicit decision).
     */
    public function conflictCount(): int
    {
        return \count(array_filter($this->fields, static fn (array $field): bool => $field['conflict']));
    }

    /**
     * Array shape for the JSON response consumed by the reconciliation page.
     *
     * @return array{survivor_id: string, candidates: list<CandidateShape>, fields: list<FieldShape>, sites: array<string, list<SiteRowShape>>, conflict_count: int}
     */
    public function toArray(): array
    {
        return [
            'survivor_id' => $this->survivorId,
            'candidates' => $this->candidates,
            'fields' => $this->fields,
            'sites' => $this->sites,
            'conflict_count' => $this->conflictCount(),
        ];
    }
}
