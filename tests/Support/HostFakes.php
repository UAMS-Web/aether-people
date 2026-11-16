<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests\Support;

use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use UAMSWeb\Aether\People\Contracts\EntryReferenceRepointer;
use UAMSWeb\Aether\People\Contracts\LocationRegions;
use UAMSWeb\Aether\People\Contracts\PublicationCitations;
use UAMSWeb\Aether\People\Support\ReferenceRepointSummary;

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

    /**
     * A reference repointer that rewrites an entry field holding a retired id exactly, anywhere in an
     * entry's data, and counts what it rewrote.
     *
     * The host's repointer also walks global sets and taxonomy terms, and matches ids inside text; those
     * stay with the host's own tests. This one gives the merge a real repointer to call, over entries only.
     */
    public static function entryReferenceRepointer(): EntryReferenceRepointer
    {
        return new class implements EntryReferenceRepointer
        {
            public function repoint(array $idMap): ReferenceRepointSummary
            {
                return $this->walk($idMap, true);
            }

            public function preview(array $idMap): ReferenceRepointSummary
            {
                return $this->walk($idMap, false);
            }

            /**
             * Rewrite, or only count, the exact-id values in every entry.
             *
             * @param  array<string, string>  $idMap  Old entry id => new entry id.
             */
            private function walk(array $idMap, bool $apply): ReferenceRepointSummary
            {
                $entries = 0;
                $occurrences = 0;

                foreach (EntryFacade::all() as $entry) {
                    if (! $entry instanceof Entry) {
                        continue;
                    }

                    /** @var Collection<string, mixed> $stored */
                    $stored = $entry->data();

                    $count = 0;
                    $data = $this->rewrite($stored->all(), $idMap, $count);

                    if ($count === 0) {
                        continue;
                    }

                    $entries++;
                    $occurrences += $count;

                    if ($apply) {
                        $entry->data($data);
                        $entry->saveQuietly();
                    }
                }

                return new ReferenceRepointSummary($entries, 0, 0, $occurrences);
            }

            /**
             * @param  array<mixed>  $value
             * @param  array<string, string>  $idMap
             * @return array<mixed>
             */
            private function rewrite(array $value, array $idMap, int &$count): array
            {
                foreach ($value as $key => $item) {
                    if (\is_array($item)) {
                        $value[$key] = $this->rewrite($item, $idMap, $count);
                    } elseif (\is_string($item) && isset($idMap[$item])) {
                        $value[$key] = $idMap[$item];
                        $count++;
                    }
                }

                return $value;
            }
        };
    }
}
