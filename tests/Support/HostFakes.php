<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests\Support;

use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Contracts\LocationRegions;
use UAMSWeb\Aether\People\Contracts\PublicationCitations;

/**
 * Stand-ins for the services the host application binds to the add-on's contracts.
 *
 * Each returns what the host returns for content it has nothing for: no regions, and the publication
 * sets unchanged. A test that needs more binds its own implementation.
 */
final class HostFakes
{
    /**
     * A location-regions service that finds no regions.
     */
    public static function locationRegions(): LocationRegions
    {
        return new class implements LocationRegions
        {
            public function resolveRegions(Entry $location): mixed
            {
                return null;
            }
        };
    }

    /**
     * A publication-citations service that adds nothing to the sets.
     */
    public static function publicationCitations(): PublicationCitations
    {
        return new class implements PublicationCitations
        {
            public function enrichResearchPublicationSelectSets(array $selectPublications): array
            {
                return $selectPublications;
            }
        };
    }
}
