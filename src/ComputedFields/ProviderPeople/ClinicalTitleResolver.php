<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople;

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;

/**
 * Resolves a provider's patient-facing clinical title from the primary
 * `clinical_specializations` collection entry (UAMS-Web/uams-statamic-archive#2596).
 *
 * The title is the primary entry's patient-friendly specialization title
 * (`title_specialization_patient`), or the code-set name (`code_name`) when the
 * entry's `title_specialization_query` toggle is on, falling back to the
 * occupation title (`title_occupation`) and finally the entry title. Shared by the
 * SEO `<title>` formula (the host's `DocumentTitle`, where it is the
 * "Primary Specialty" segment) and the `clinical_title` computed field the
 * Find-a-Doc provider card renders (UAMS-Web/uams-statamic-archive#64), so the two never drift.
 */
final readonly class ClinicalTitleResolver
{
    /**
     * Collection handle for the primary-specialty entries. The field stores entry
     * ids, and an id from any other collection resolves to no title.
     */
    private const string SPECIALIZATION_COLLECTION = 'clinical_specializations';

    /**
     * The primary `clinical_specializations` entry's patient-facing title, or ''
     * when the entry has no resolvable specialization.
     */
    public function resolve(Entry $entry): string
    {
        $entryIds = $entry->get('clinical_specializations');
        if (! \is_array($entryIds) || $entryIds === []) {
            return '';
        }

        // A non-string id is rejected here rather than handed to `find()`, which
        // compares loosely. An empty string needs no check: no entry has that id.
        $primary = $entryIds[0] ?? null;
        if (! \is_string($primary)) {
            return '';
        }

        // The collection lives on `default` only (UAMS-Web/uams-statamic-archive#2605), so the id is the entry itself.
        $specialization = EntryFacade::find($primary);
        if (
            ! $specialization instanceof Entry
            || $specialization->collectionHandle() !== self::SPECIALIZATION_COLLECTION
        ) {
            return '';
        }

        // When the editor opts to reuse the code-set name as the patient-friendly
        // title, prefer it over the specialization title. An `if` already reads its
        // condition as a bool, so removing the cast is an equivalent mutant.
        // @pest-mutate-ignore: RemoveBooleanCast
        if ((bool) $specialization->get('title_specialization_query')) {
            $codeName = $this->stringValue($specialization->get('code_name'));
            if ($codeName !== '') {
                return $codeName;
            }
        }

        // Otherwise the patient-friendly specialization title, then the occupation
        // title, then the entry title as a last resort.
        foreach (['title_specialization_patient', 'title_occupation', 'title'] as $handle) {
            $value = $this->stringValue($specialization->get($handle));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Narrow an arbitrary stored value to a trimmed string, or '' when non-scalar.
     */
    private function stringValue(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }
}
