<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Lenses;

/**
 * An immutable description of a single people lens (UAMS-Web/uams-statamic-archive#1636).
 *
 * A lens is a site-specific render of one canonical `people` entry: the template
 * it renders through, the optional gate that decides eligibility, the URL pattern
 * it lives at (relative to each active site's own prefix), and the sites it is
 * active on.
 */
final readonly class LensDescriptor
{
    /**
     * @param  string  $handle  The lens handle (e.g. `provider`); the view sub-directory name.
     * @param  string  $template  The Antlers view rendered for the lens (under `people/<lens>/`).
     * @param  class-string<LensGate>|null  $gate  The gate class, or null for an ungated lens.
     * @param  string  $route  The URL pattern with a `{slug}` placeholder, relative to a site prefix.
     * @param  list<string>  $sites  Site handles the lens is active on; `['*']` means every site.
     */
    public function __construct(
        public string $handle,
        public string $template,
        public ?string $gate,
        public string $route,
        public array $sites,
    ) {}

    /**
     * Whether this lens is active on the given site.
     */
    public function activeOnSite(string $site): bool
    {
        return \in_array('*', $this->sites, true)
            || \in_array($site, $this->sites, true);
    }
}
