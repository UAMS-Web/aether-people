<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Contracts;

/**
 * Adds PubMed citations to a person's `research_publication_select` sets.
 *
 * The host binds it: the E-summary cache and the command that warms it stay in the host.
 */
interface PublicationCitations
{
    /**
     * Enrich each set with `pubmed_information` HTML.
     *
     * Request-time only: an implementation reads its cache and never fetches over HTTP, so a cold
     * cache yields an empty citation instead of blocking a render.
     *
     * @param  array<int|string, mixed>  $selectPublications  Replicator sets keyed by index or ID.
     * @return array<int|string, mixed> Sets with `pubmed_information` added for array rows with a PMID.
     */
    public function enrichResearchPublicationSelectSets(array $selectPublications): array;
}
