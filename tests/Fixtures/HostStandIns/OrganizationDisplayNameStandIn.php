<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests\Fixtures\HostStandIns;

use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Contracts\OrganizationDisplayName;

/**
 * Stands in for the host's public-label resolver: a unit's name is its `public_label`.
 */
final class OrganizationDisplayNameStandIn implements OrganizationDisplayName
{
    #[\Override]
    public function displayName(Entry $entry): ?string
    {
        $label = $entry->value('public_label');

        return \is_string($label) && $label !== '' ? $label : null;
    }
}
