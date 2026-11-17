<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Duplicates;

/**
 * One cluster of `people` entries that likely describe the same real person (UAMS-Web/uams-statamic-archive#839).
 *
 * Detection only suggests, a cluster is a candidate handed to a human, never a
 * merge. The confidence and the signals that linked the cluster let the reviewer
 * triage strong matches (a shared clinical identifier) from weak ones (a shared
 * name alone) before launching the UAMS-Web/uams-statamic-archive#838 merge tool against it.
 */
final class DuplicatePersonCluster
{
    /**
     * Strong, near-certain match: a shared clinical/system identifier or email.
     */
    public const string CONFIDENCE_HIGH = 'high';

    /**
     * Plausible match needing review: a shared name with a corroborating signal,
     * or a shared phone number.
     */
    public const string CONFIDENCE_MEDIUM = 'medium';

    /**
     * Weak match: a shared name (or slug stem) alone, many people share a name.
     */
    public const string CONFIDENCE_LOW = 'low';

    /**
     * @param  list<string>  $entryIds  The clustered people entry ids (2 or more).
     * @param  string  $confidence  One of the CONFIDENCE_* levels.
     * @param  list<string>  $matchedSignals  Signal types shared by ≥2 members (e.g. `identifier`, `name`, `email`).
     */
    public function __construct(
        public readonly array $entryIds,
        public readonly string $confidence,
        public readonly array $matchedSignals,
    ) {}
}
