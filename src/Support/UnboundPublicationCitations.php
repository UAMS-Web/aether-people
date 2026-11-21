<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use UAMSWeb\Aether\People\Contracts\PublicationCitations;

/**
 * The add-on's default for {@see PublicationCitations}: it refuses, naming the contract, until the
 * host binds its own (UAMS-Web/aether-people#144). A host binding replaces it.
 */
final class UnboundPublicationCitations implements PublicationCitations
{
    /**
     * Refuse: no host binding supplies the citations.
     *
     * @param  array<int|string, mixed>  $selectPublications  Replicator sets keyed by index or ID.
     * @return array<int|string, mixed> Never returns
     *
     * @throws MissingHostBindingException Always
     */
    public function enrichResearchPublicationSelectSets(array $selectPublications): array
    {
        throw MissingHostBindingException::forContract(PublicationCitations::class);
    }
}
