<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Contracts;

use Statamic\Entries\Entry;

/**
 * The public name of an organizational unit, which the host resolves.
 *
 * A `role_title` scope that links a unit composes the unit's name into the title. The unit's
 * own `formal_name` is read here; where it has none, the name the host publishes for the unit
 * (its public label, which may inherit from an ancestor) comes from this contract. A host binds
 * its implementation in the container. With none bound, a unit without a `formal_name` has no
 * name to compose.
 */
interface OrganizationDisplayName
{
    /**
     * The unit's public name, or null when it has none.
     *
     * @param  Entry  $entry  The organizational unit entry
     * @return string|null The public name, or null
     */
    public function displayName(Entry $entry): ?string;
}
