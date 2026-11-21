<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Contracts\LocationRegions;

/**
 * The add-on's default for {@see LocationRegions}: it refuses, naming the contract, until the host
 * binds its own (UAMS-Web/aether-people#144). A host binding replaces it.
 */
final class UnboundLocationRegions implements LocationRegions
{
    /**
     * Refuse: no host binding supplies the regions.
     *
     * @param  Entry  $location  A `clinical_locations` entry.
     *
     * @throws MissingHostBindingException Always
     */
    public function resolveRegions(Entry $location): mixed
    {
        throw MissingHostBindingException::forContract(LocationRegions::class);
    }
}
