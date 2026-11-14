<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Api\FindADoc;

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ClinicalTitleResolver;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;

/**
 * Builds the Find-a-Doc read API's provider payload from a `people` entry (UAMS-Web/uams-statamic-archive#1043)
 * - the Statamic analog of the WordPress `provider_meta` denormalization the
 * "UAMS Health" app consumes.
 *
 * The payload is an explicit **allow-list**: it names every emitted field and
 * never spreads the entry, so `people` PII (#47) - identifiers (`npi`,
 * `epic_ser_id`, `uams_workday_id`), contact (`email`, `telephone`, `fax`),
 * HR/separation internals, `gender`, hometown/death fields - cannot leak by
 * construction. Display values reuse the same computed name / clinical-title
 * services the public profile renders, so the API and the site never drift.
 */
final class ProviderPayloadTransformer
{
    public function __construct(
        private readonly ProviderPeoplePersonNameComputedValues $names,
        private readonly ClinicalTitleResolver $clinicalTitle
    ) {}

    /**
     * The versioned, PII-free provider payload for a single person.
     *
     * @return array<string, mixed> The allow-listed provider record
     */
    public function toArray(Entry $person): array
    {
        return [
            'id' => $this->str($person->id()),
            'slug' => $this->str($person->slug()),
            'url' => $this->str($person->absoluteUrl()),
            'name' => [
                'full' => $this->text($this->names->getDisplayNameFull($person)),
                'full_with_credentials' => $this->text($this->names->getDisplayNameFullWithCredentials($person)),
                'short_with_prefix' => $this->text($this->names->getDisplayNameShortWithPrefix($person)),
                'prefix' => $this->text($this->names->getPrefixValue($person)),
            ],
            'credentials' => $this->stringList($person->get('credentials')),
            'clinical_title' => $this->emptyToNull($this->clinicalTitle->resolve($person)),
            'specialties' => $this->specialtySlugs($person->get('clinical_specializations')),
            'service_line' => $this->stringList($person->get('service_line')),
            'pronouns' => $this->str($person->get('pronouns')),
            'appointments' => [
                'accepting_new_patients' => $this->truthy($person->get('accepting_patients_query')),
                'referral_required' => $this->truthy($person->get('referral_required')),
            ],
            'is_resident' => $this->truthy($person->get('resident_query')),
            'portrait' => [
                // The stored asset paths (3:4 headshot + 16:9 wide). URL resolution
                // is deferred to a coordinated follow-up with the app team.
                'headshot' => $this->str($person->get('portrait')),
                'wide' => $this->str($person->get('portrait_wide')),
            ],
        ];
    }

    /**
     * Extract the `text` string from a name computed's `{text, phonetic_text}`
     * pair, or null when empty.
     *
     * @param  array<string, mixed>  $pair
     */
    private function text(array $pair): ?string
    {
        $text = $pair['text'] ?? null;

        return \is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * Narrow a mixed value to a non-empty string, or null.
     */
    private function str(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Collapse an empty string to null.
     */
    private function emptyToNull(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }

    /**
     * The slugs of the stored `clinical_specializations` entries, in stored order.
     *
     * The field stores entry ids since UAMS-Web/uams-statamic-archive#2596 converted the vocabulary from a taxonomy
     * to a collection. The API has always emitted the specialization slugs, and each
     * entry kept its term's slug, so mapping the ids back keeps `specialties`
     * byte-identical for API consumers. An id that resolves to no entry in the
     * collection is dropped.
     *
     * @return array<int, string>
     */
    private function specialtySlugs(mixed $value): array
    {
        $slugs = [];

        foreach ($this->stringList($value) as $entryId) {
            $specialization = EntryFacade::find($entryId);
            if (! $specialization instanceof Entry) {
                continue;
            }

            if ($specialization->collectionHandle() !== 'clinical_specializations') {
                continue;
            }

            $slug = $specialization->slug();

            if (\is_string($slug) && $slug !== '') {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * Coerce a stored terms/scalar value to a list of non-empty string slugs.
     *
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (\is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (! \is_array($value)) {
            return [];
        }

        $slugs = [];

        foreach ($value as $item) {
            if (\is_string($item) && $item !== '') {
                $slugs[] = $item;
            }
        }

        return $slugs;
    }

    /**
     * Coerce a stored `true_false` value to a strict bool (handles bool / "1" / "true").
     */
    private function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
