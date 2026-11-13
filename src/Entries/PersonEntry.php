<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Entries;

use Statamic\Entries\Entry;

/**
 * Custom Entry class for the `people` collection.
 *
 * Suppresses the front-end profile page for a person whose "Create a profile
 * page for this person?" toggle (`profile_page_query`) is explicitly disabled.
 * The entry stays published and queryable (it still feeds the People Showcase
 * block/Bard set and other listings) but becomes non-routable, so Statamic
 * returns a 404 for its `people/{slug}` URL and `{{ url }}` resolves to null
 * (the People Showcase heading then renders unlinked).
 *
 * The suppression hooks `route()`: returning null makes `Entry::uri()`
 * short-circuit to null, which Statamic treats as non-routable everywhere it
 * matters (the Stache URI index, `{{ url }}`, and front-end route resolution).
 * This is the idiomatic Statamic mechanism, with no bespoke controller or 404 guard.
 */
class PersonEntry extends Entry
{
    /**
     * Field handle of the "Create a profile page for this person?" toggle.
     */
    public const string PROFILE_PAGE_FIELD = 'profile_page_query';

    /**
     * Resolve the collection route for this entry, suppressing it when the
     * person has opted out of a front-end profile page.
     *
     * Returns null (no route → no URI → 404) only when `profile_page_query`
     * is explicitly `false`. An unset value keeps the collection default
     * (routable), matching the field's `true` default and the People Showcase
     * heading guard (`profile_page_query != false`). Reads raw data via
     * `get()` because routes are resolved before augmentation.
     *
     * @return string|null The collection route template, or null when suppressed
     */
    #[\Override]
    public function route(): ?string
    {
        // A person flagged "no profile page" is non-routable; everyone else
        // (including legacy entries with no stored value) keeps the default route.
        if ($this->get(self::PROFILE_PAGE_FIELD) === false) {
            return null;
        }

        // Statamic's Entry::route() is untyped (it returns the collection's
        // route template string, or null for an un-routed collection); narrow
        // the mixed result to the declared string|null.
        $route = parent::route();

        return \is_string($route) ? $route : null;
    }
}
