<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Contracts;

use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;

/**
 * What the org-derived people lenses need from the host's department hubs.
 *
 * Organizations and their hub sites stay in the host application, so the host binds
 * this contract. It is a permanent seam, not a stand-in: the add-on never owns the
 * `organizations` collection (UAMS-Web/uams-statamic#281).
 */
interface DepartmentHubRoster
{
    /**
     * The `organizations` entry a hub site maps to.
     *
     * @param  string  $siteHandle  The handle of the site being rendered.
     * @return Entry|null The organization, or null when the site is not an opt-in hub.
     */
    public function organization(string $siteHandle): ?Entry;

    /**
     * The people in an organization's derived roster (leadership and mapped members).
     *
     * @param  Entry  $organization  The organization returned by {@see self::organization()}.
     * @return EntryCollection<int, Entry> The roster's `people` entries.
     */
    public function resolvePeople(Entry $organization): EntryCollection;
}
