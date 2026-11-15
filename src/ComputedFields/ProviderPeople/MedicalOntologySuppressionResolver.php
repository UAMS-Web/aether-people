<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople;

use Statamic\Entries\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;

/**
 * Whether a provider's medical-ontology sections are suppressed by an editor-configured
 * removal rule (UAMS-Web/uams-statamic-archive#1687).
 *
 * WordPress computes `$hide_medical_ontology` on the provider profile from the
 * **Remove Medical Ontology** tab of the Find-a-Doc settings: a replicator of rows, each
 * pairing an optional service line with an optional region. A provider is suppressed when
 * EVERY non-empty criterion in a row matches; an all-empty row matches nothing; the first
 * matching row wins. Those semantics are ported faithfully.
 *
 * Two things are deliberately NOT ported, both recorded in `docs/DECISIONS.md`:
 *
 * 1. **Only the routable section honors this.** WordPress hid links to three ontology
 *    collections. Here `clinical_expertise` is routable while `clinical_conditions` and
 *    `clinical_services` are not, so conditions and treatments render as plain text with
 *    nothing to link away from. Only Areas of Expertise is suppressed.
 * 2. **Region matching is ANY, not sole.** WordPress compares
 *    `in_array(implode('', $provider_region), $remove_region)`, concatenating a provider's
 *    region ids into one string - so a provider in two regions becomes `"6566"` and can
 *    never match a removal list of `["65"]`. That is a string-concatenation accident, not
 *    a rule. A provider matches here when ANY of their regions is the row's region.
 *
 * The flag is computed once per provider and honored only on the profile render, so a
 * suppressed provider still appears in Find-a-Doc listings and on Areas-of-Expertise
 * pages. That boundary is deliberate and its residue is tracked separately.
 */
final readonly class MedicalOntologySuppressionResolver
{
    /**
     * Handle of the Find-a-Doc settings global carrying the removal rules.
     */
    private const string SETTINGS_GLOBAL = 'uams_fad_settings';

    /**
     * Handle of the replicator holding one removal rule per row.
     */
    private const string CRITERIA_HANDLE = 'remove_ontology_criteria';

    /**
     * @param  ProviderPeopleListingComputedValues  $listingComputed  Supplies the provider's regions.
     */
    public function __construct(
        private ProviderPeopleListingComputedValues $listingComputed
    ) {}

    /**
     * Whether this provider's Areas of Expertise section is suppressed.
     */
    public function resolve(Entry $entry): bool
    {
        foreach ($this->criteriaRows() as $row) {
            $serviceLine = $this->singleValue($row['remove_service_lines'] ?? null);
            $region = $this->singleValue($row['remove_regions'] ?? null);

            // An all-empty row matches nothing - WordPress has no branch that can fire
            // without at least one non-empty criterion, and a row an editor started and
            // abandoned must not suppress every provider on the site.
            if ($serviceLine === null && $region === null) {
                continue;
            }

            if ($serviceLine !== null && ! $this->matchesServiceLine($entry, $serviceLine)) {
                continue;
            }

            if ($region !== null && ! $this->matchesRegion($entry, $region)) {
                continue;
            }

            // First matching row wins; the rest cannot change the outcome.
            return true;
        }

        return false;
    }

    /**
     * The configured removal rows, or an empty list when the feature is unconfigured.
     *
     * The Find-a-Doc settings global is rooted at the `default` site and its fields are
     * non-localizable, so `inDefaultSite()` resolves them from any site context (UAMS-Web/uams-statamic-archive#1698) -
     * including the non-`health` contexts computed-field resolution can run in.
     *
     * A replicator augments to a plain PHP array rather than to a query builder, so an
     * absent or empty value is genuinely falsy here, unlike an `entries` relation,
     * which augments to a query builder that is truthy even when empty.
     *
     * A row the editor DISABLED is skipped. `Variables::get()` returns the raw stored
     * data rather than the augmented value, so the `enabled` flag Statamic's own
     * `Replicator::performAugmentation()` acts on is still present here and has to be
     * honored by hand - otherwise a rule an editor paused with the set's disable toggle
     * goes on silently hiding clinical content, with nothing to tell them why the toggle
     * did nothing. Tested `!== false` rather than for truthiness because an ABSENT flag
     * means enabled, matching both the fieldtype and `ProviderProfileBodyResolver`.
     *
     * Row keys are typed `mixed` rather than `string` deliberately: these rows are
     * whatever an editor's global holds, and claiming string keys would be an assertion
     * this method never checks. Every read is a `?? null` lookup, which needs no more.
     *
     * @return list<array<mixed, mixed>>
     */
    private function criteriaRows(): array
    {
        $variables = GlobalSet::findByHandle(self::SETTINGS_GLOBAL)?->inDefaultSite();

        if (! $variables instanceof Variables) {
            return [];
        }

        $rows = $variables->get(self::CRITERIA_HANDLE);

        if (! \is_array($rows)) {
            return [];
        }

        // resolve() only iterates the rows, so their keys never reach the outcome.
        // @pest-mutate-ignore: UnwrapArrayValues
        return array_values(array_filter(
            $rows,
            static fn (mixed $row): bool => \is_array($row) && ($row['enabled'] ?? true) !== false
        ));
    }

    /**
     * Whether the provider's service line is the one this row names.
     *
     * `service_line` is a stored blueprint field, so it is read directly. A provider
     * without one (the field is conditional, and `required_unless` leaves real gaps)
     * yields null and cannot match a non-empty criterion.
     */
    private function matchesServiceLine(Entry $entry, string $serviceLine): bool
    {
        return $this->singleValue($entry->get('service_line')) === $serviceLine;
    }

    /**
     * Whether ANY of the provider's regions is the one this row names.
     *
     * A provider's `regions` is a computed-only aggregate of their locations' regions and
     * is never stored, so `Entry::get('regions')` returns null - the derivation has to be
     * called directly.
     *
     * Each aggregated value goes through the same normalizer as the criterion, because
     * `computeRegions()` maps each location to the RAW `regions` stored on whichever
     * location owns its street group (UAMS-Web/uams-statamic-archive#2608), and inherits that field's two shapes: `Terms::process()` persists a bare scalar slug for a CP save,
     * while a programmatic write leaves a one-element array. Its `unique()` cannot collapse
     * the two - a loose `==` does not equate `'x'` and `['x']` - so a provider can carry
     * both shapes at once, and comparing only the string ones would silently skip a
     * location the editor's rule was written to catch.
     *
     * A provider with no regions at all is normal rather than exceptional: a location
     * contained within a facility resolves no region, and a child location inherits its
     * parent's, which may be empty. Such a provider matches no region criterion, which is
     * the correct reading of "providers in this region".
     */
    private function matchesRegion(Entry $entry, string $region): bool
    {
        return array_any(
            $this->listingComputed->computeRegions($entry),
            fn (mixed $value): bool => $this->singleValue($value) === $region
        );
    }

    /**
     * Normalize a `max_items: 1` relation or terms value to a single non-empty string.
     *
     * Both criterion fields and the provider's `service_line` are `max_items: 1`, and that
     * does NOT give them one storage shape in this repo: a value saved through the CP is a
     * bare scalar, while one written programmatically (the importer, a factory, a test
     * fixture) is a single-element array. Both shapes are present in real content, so a
     * direct `===` between the two sides silently fails to match.
     *
     * Known sharp edge, deliberately left: a criterion holding a non-string (an int, a
     * nested array) is read as UNSET rather than as unusable, which widens its row to the
     * remaining criterion instead of disabling it. No write path produces that shape for
     * these fields today, and the all-empty guard in `resolve()` still stops a row whose
     * criteria are BOTH unreadable from matching everything.
     */
    private function singleValue(mixed $value): ?string
    {
        if (\is_array($value)) {
            $value = $value === [] ? null : reset($value);
        }

        if (! \is_string($value)) {
            return null;
        }

        return $value === '' ? null : $value;
    }
}
