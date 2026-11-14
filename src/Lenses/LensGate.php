<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Lenses;

use Statamic\Entries\Entry;
use Statamic\Sites\Site;

/**
 * Decides whether a resolved canonical `people` entry may be rendered through a
 * particular lens on the site currently being requested (UAMS-Web/uams-statamic-archive#1636).
 *
 * A lens gate is the "gating association" half of a lens: the clinical provider
 * lens gates on UAMS-Web/uams-statamic-archive#919 eligibility; the org-derived lenses gate on the site's
 * organization membership via the org-to-people bridge. A lens with no gate
 * (the general lens) has no `LensGate`.
 */
interface LensGate
{
    /**
     * Whether the person qualifies for this lens on the given site.
     *
     * @param  Entry  $person  The resolved canonical `people` entry.
     * @param  Site  $site  The site the lens is rendering on.
     * @return bool True when the lens may render this person on this site.
     */
    public function passes(Entry $person, Site $site): bool;
}
