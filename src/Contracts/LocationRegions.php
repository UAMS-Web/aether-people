<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Contracts;

use Statamic\Entries\Entry;

/**
 * The stored region terms that apply to a clinical location.
 *
 * The host binds it: locations belong to the `clinical_locations` collection, which stays in the
 * host, and the regions follow a location's street address through the facility that contains it.
 */
interface LocationRegions
{
    /**
     * The stored region terms that apply to a location, or null when none apply.
     *
     * @param  Entry  $location  A `clinical_locations` entry.
     */
    public function resolveRegions(Entry $location): mixed;
}
