<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Resolves the single canonical `people` entry for a slug (UAMS-Web/uams-statamic-archive#1635).
 *
 * People are stored as one entry on the canonical site
 * (`config('people_lenses.canonical_site')`) with no per-site localizations, so
 * every lens renders that one entry cross-site. A site-scoped `{{ collection:people }}`
 * query returns nothing on a non-canonical site; this resolver always targets the
 * canonical site so a lens route on any site resolves the same source of truth.
 */
final class CanonicalPeopleResolver
{
    /**
     * The site whose `people` entries are the canonical source of truth.
     */
    public function canonicalSite(): string
    {
        $site = config('people_lenses.canonical_site', 'default');

        return is_string($site) && $site !== '' ? $site : 'default';
    }

    /**
     * Resolve the published-or-not canonical `people` entry for a slug.
     *
     * Returns the entry regardless of publish status so the caller (a lens
     * controller) applies the same publish/visibility gate the normal front end
     * would, rather than silently 404-ing a draft the editor is previewing.
     *
     * @param  string  $slug  The people entry slug from the lens route.
     * @return Entry|null The canonical entry, or null when no such slug exists.
     */
    public function resolve(string $slug): ?Entry
    {
        if ($slug === '') {
            return null;
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', PeopleCollection::handle());
        $query->where('site', $this->canonicalSite());
        $query->where('slug', $slug);

        $entry = $query->first();

        // Narrow the query contract to the concrete Entry the lens gates + templates
        // consume (a file `people` entry is always the concrete class at runtime).
        return $entry instanceof Entry ? $entry : null;
    }
}
