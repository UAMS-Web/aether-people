<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Listeners;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use UAMSWeb\Aether\People\Support\PeopleCollection;

/**
 * Strips virtual computed-only fields from providers and people entries before
 * save.
 *
 * Removes values that are injected at augment time (PubMed HTML on publication
 * sets; certification activity computeds) so they are not persisted with the
 * entry.
 */
class StripProviderPeopleVirtualFieldsOnEntrySaving
{
    /**
     * Collection handles whose entries may carry virtual fields to strip.
     *
     * @return list<string>
     */
    private static function collectionHandles(): array
    {
        return [
            'clinical_providers',
            PeopleCollection::handle(),
        ];
    }

    /**
     * Strip virtual fields from the saving entry when it belongs to a supported
     * collection.
     *
     * @param  EntrySaving  $event  Statamic entry save event carrying the entry
     */
    public function handle(
        EntrySaving $event
    ): void {
        // Get the entry from the event
        /** @var Entry $entry */
        $entry = $event->entry;

        // Check if the entry belongs to a supported collection
        // collectionHandle() returns a string or null, and collection handles are never
        // numeric strings, so a loose match finds the same handles as a strict one.
        // @pest-mutate-ignore: TrueToFalse
        if (! \in_array(
            $entry->collectionHandle(),
            self::collectionHandles(),
            true
        )) {
            // Return early if the entry does not belong to a supported collection
            return;
        }

        // Strip virtual fields from the research publication select replicator set
        $this->stripResearchPublicationVirtualFields($entry);

        // Check if the entry belongs to the people collection
        if ($entry->collectionHandle() !== PeopleCollection::handle()) {
            // Return early to signal that the entry is not a people entry
            return;
        }

        // Strip virtual fields from the certification replicator set
        $this->stripCertificationVirtualFields($entry);
    }

    /**
     * Remove `pubmed_information` from each `research_publication_select` replicator
     * set.
     *
     * @param  Entry  $entry  The providers or people entry being saved
     */
    private function stripResearchPublicationVirtualFields(
        Entry $entry
    ): void {
        // Get the research publication select replicator set
        /** @var array<array-key, mixed> $selectPublications */
        $selectPublications = $entry->get(
            'research_publication_select',
            []
        );

        // Return early if the research publication select replicator set is empty
        if (empty($selectPublications)) {
            return;
        }

        // Strip the virtual field from the research publication select replicator set
        $updatedSets = \array_map(function (mixed $set): mixed {
            // Return early if the set is not an array
            if (! \is_array($set)) {
                return $set;
            }

            // Unset the virtual field from the set
            unset($set['pubmed_information']);

            // Return the updated set
            return $set;
        }, $selectPublications);

        // Set the updated research publication select replicator set
        $entry->set(
            'research_publication_select',
            $updatedSets
        );
    }

    /**
     * Remove virtual activity computeds from each `certifications` replicator set.
     *
     * @param  Entry  $entry  The people entry being saved
     */
    private function stripCertificationVirtualFields(
        Entry $entry
    ): void {
        // Get the certifications replicator set
        $certifications = $entry->get(
            'certifications',
            []
        );

        // Check if the certifications replicator set is an Illuminate collection
        if ($certifications instanceof IlluminateCollection) {
            // Unwrap the Illuminate collection to its underlying array
            $certifications = $certifications->all();
        }

        // Return early if the certifications replicator set is not an array or is empty
        if (
            ! \is_array($certifications)
            || $certifications === []
        ) {
            return;
        }

        // Strip the virtual fields from the certifications replicator set
        $updatedCertifications = \array_map(function (mixed $set): mixed {
            // Return early if the set is not an array
            if (! \is_array($set)) {
                return $set;
            }

            // Unset the virtual fields from the set
            unset(
                $set['activity_valid_for'],
                $set['activity_active_query']
            );

            // Return the updated set
            return $set;
        }, $certifications);

        // Set the updated certifications replicator set
        $entry->set(
            'certifications',
            $updatedCertifications
        );
    }
}
