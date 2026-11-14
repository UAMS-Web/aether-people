<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople;

use Statamic\Entries\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;

/**
 * Answers one question for the clinical provider lens: does anything render
 * BETWEEN the two appointment CTAs (UAMS-Web/uams-statamic-archive#1695)?
 *
 * WordPress renders the appointment block twice and gates the second instance on
 * `$show_appointment_section` AND at least one intervening section having
 * rendered, before it sets `$appointment_block_instance = 2`.
 *
 * Ours rendered instance 2 whenever instance 1 rendered, so a provider with an
 * appointment CTA and nothing else - no bio, clinical focus, expertise,
 * conditions, treatments, locations, podcast, resources, credentials, or awards
 * - saw the same block twice with nothing between them.
 *
 * Why a resolver rather than the call site: reproducing the gate in Antlers means
 * restating every intervening section's own condition where the second CTA is
 * included, and ten conditions copied into an eleventh place drift the moment a
 * section is added, removed, or re-gated. This is the one place that list lives.
 *
 * The irreducible coupling is the LIST of sections, not their semantics: each
 * check below reproduces the gate its section actually uses in
 * `resources/views/people/provider/_profile.antlers.html`. Two cases in
 * `ProviderProfileBodyResolverTest` hold that coupling - one covering every handle
 * here against real entries, and an "accounts for every section" case that counts
 * the section-producing partial calls between the two CTA include sites and pins
 * the total, so a section ADDED to or REMOVED from the profile reddens the suite
 * until this list is updated.
 *
 * What is deliberately NOT verified, and is an accepted gap: that a given section's
 * gate and its check here agree on the same EDGE value. No test evaluates the two
 * against one another. That gap already bit once - a whitespace-only podcast guest
 * name rendered the section while this returned false - so each check names the
 * gate it mirrors, to be re-read as a pair whenever either side moves.
 *
 * The bias is deliberately toward rendering: an unrecognized shape counts as
 * content. Over-reporting reproduces the pre-existing duplicate CTA in a narrow
 * case; under-reporting would DROP the foot-of-page CTA from a real profile,
 * which is a regression against a patient trying to book.
 */
final readonly class ProviderProfileBodyResolver
{
    /**
     * Bard and replicator handles whose mere presence renders a section.
     *
     * `bio_clinical`/`bio` back the About block (`:when="bio_clinical || bio"`),
     * `clinical_focus` the Clinical Focus block, `certifications` and `member_of`
     * the Credentials block (`:when="certifications || member_of"`), and `awards`
     * the shared awards component.
     *
     * @var list<string>
     */
    // PCOV attributes no coverage to a constant's initializer, so Pest selects no test for its
    // mutants and reports them uncovered. Removing any one content handle, applied by hand, fails
    // the case of "reports a rendered body for each ... section in turn" that names it.
    // @pest-mutate-ignore: RemoveArrayItem
    private const array CONTENT_HANDLES = [
        'bio_clinical',
        'bio',
        'clinical_focus',
        'certifications',
        'member_of',
        'awards',
    ];

    /**
     * Relation handles whose sections are gated on `| count`: the count of
     * published entries the relation resolves, not the stored ids (UAMS-Web/uams-statamic-archive#1693).
     *
     * @var list<string>
     */
    // PCOV attributes no coverage to a constant's initializer, so Pest selects no test for its
    // mutants and reports them uncovered. Removing any one relation handle, applied by hand, fails
    // the case of "reports a rendered body for each ... section in turn" that names it.
    // @pest-mutate-ignore: RemoveArrayItem
    private const array RELATION_HANDLES = [
        'expertise',
        'conditions',
        'treatments',
        'clinical_locations',
        'clinical_resources',
    ];

    /**
     * Handle of the Find-a-Doc settings global.
     */
    private const string SETTINGS_GLOBAL = 'uams_fad_settings';

    /**
     * @param  StacheEntryRepository  $entries  Statamic Stache entry repository (`find`).
     * @param  MedicalOntologySuppressionResolver  $ontologySuppression  Whether a removal rule hides Areas of Expertise (UAMS-Web/uams-statamic-archive#1687).
     */
    public function __construct(
        private StacheEntryRepository $entries,
        private MedicalOntologySuppressionResolver $ontologySuppression
    ) {}

    /**
     * Whether at least one profile section renders between the two appointment
     * CTAs, and the foot-of-page CTA therefore repeats.
     */
    public function resolve(Entry $entry): bool
    {
        // Bard and replicator sections: a non-empty stored value renders
        foreach (self::CONTENT_HANDLES as $handle) {
            if ($this->hasContent($entry->get($handle))) {
                return true;
            }
        }

        // Ontology, locations, and resources sections: a relation resolving to at
        // least one PUBLISHED entry renders
        $suppressed = $this->suppressedRelationHandles($entry);

        foreach (self::RELATION_HANDLES as $handle) {
            // Both sides are strings, so a loose comparison finds the same handles.
            // @pest-mutate-ignore: TrueToFalse
            if (\in_array($handle, $suppressed, true)) {
                continue;
            }

            if ($this->hasPublishedRelation($entry, $handle)) {
                return true;
            }
        }

        return $this->rendersPodcast($entry);
    }

    /**
     * Whether a stored Bard, replicator, or text value carries anything.
     *
     * A Bard field stores a list of node arrays and a replicator a list of set
     * arrays, so a non-empty array is content - EXCEPT that a replicator set the
     * editor disabled never reaches the template. `Replicator::performAugmentation()`
     * rejects every set whose `enabled` flag is exactly `false`, so an `awards`
     * field holding only disabled sets augments to `[]` and the Awards component's
     * `{{ if awards }}` renders nothing. Counting the raw array would report a
     * section that is not there and put the back-to-back CTA straight back.
     *
     * A Bard node carries no `enabled` key, so the same rule passes it through
     * unchanged. A cleared-but-touched Bard can store a single empty paragraph
     * node, which the template's own truthiness treats as content too - matching
     * it here keeps the two in step rather than second-guessing the section that
     * actually renders.
     */
    private function hasContent(mixed $value): bool
    {
        if (\is_array($value)) {
            return array_any($value, fn (mixed $item): bool => $this->isEnabledItem($item));
        }

        if (\is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null && $value !== false;
    }

    /**
     * Whether one element of a stored Bard/replicator list survives augmentation.
     *
     * Only an explicit `enabled: false` removes a set - an absent flag means
     * enabled, which is both the fieldtype's default and what every Bard node
     * looks like.
     */
    private function isEnabledItem(mixed $item): bool
    {
        // A scalar has no `enabled` key, so the `??` read below would also give
        // `true`; removing this return is an equivalent mutant. It stays so the
        // offset is only ever read from an array.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! \is_array($item)) {
            return true;
        }

        return ($item['enabled'] ?? true) !== false;
    }

    /**
     * Relation handles whose sections a removal rule suppresses for this provider.
     *
     * A suppressed section does not render, so counting it here would report a body
     * that is not there and put UAMS-Web/uams-statamic-archive#1695's back-to-back CTA straight back for a provider
     * whose Areas of Expertise was the only thing between the two. The other relation
     * handles still cover a provider with locations or resources, and `CONTENT_HANDLES`
     * covers a bio, focus, credentials, or awards.
     *
     * `remove_ontology_criteria` names three ontology collections, but only the routable
     * Areas of Expertise is suppressed here (UAMS-Web/uams-statamic-archive#1687), so `conditions` and `treatments`
     * keep counting. When the excluded surfaces are revisited this list grows with them.
     *
     * @return list<string>
     */
    private function suppressedRelationHandles(Entry $entry): array
    {
        return $this->ontologySuppression->resolve($entry) ? ['expertise'] : [];
    }

    /**
     * Whether an `entries` relation resolves to at least one published entry.
     *
     * Publication is checked here for the reason `AppointmentContextResolver`
     * documents: the template gate is `| count`, which counts the published
     * entries of the augmented `StatusQueryBuilder`, so a relation holding only
     * unpublished entries renders NO section even though it stores ids (UAMS-Web/uams-statamic-archive#1693).
     * Counting stored ids here would report a section that never appears.
     *
     * `status() === 'published'` rather than `published()` - a dated collection's
     * future-dated entry is flagged published but has status `scheduled`, and the
     * augmented relation excludes it.
     */
    private function hasPublishedRelation(Entry $entry, string $handle): bool
    {
        $ids = $entry->get($handle);

        if (! \is_array($ids) || $ids === []) {
            return false;
        }

        foreach ($ids as $id) {
            // An empty id needs no check of its own: no entry has that id.
            if (! \is_string($id)) {
                continue;
            }

            $related = $this->entries->find($id);

            if ($related instanceof Entry && $related->status() === 'published') {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the UAMS Health Talk podcast section renders.
     *
     * Mirrors the partial's own gate: a provider carrying a podcast guest name,
     * and the Find-a-Doc toggle not explicitly off. It tests against `false`
     * rather than `true` so an unsaved global (null, before an editor has opened
     * the tab) still counts, matching the field's `default: true`.
     */
    private function rendersPodcast(Entry $entry): bool
    {
        if (! $this->hasContent($entry->get('podcast_guest_name_uams_health_talk'))) {
            return false;
        }

        return $this->settingValue('health_talk_query') !== false;
    }

    /**
     * The raw stored value of one Find-a-Doc setting, or null when unset.
     *
     * The Find-a-Doc settings global is rooted at the `default` site and its
     * fields are non-localizable, so `inDefaultSite()` resolves them from any
     * site context (UAMS-Web/uams-statamic-archive#1698) - including the non-`health` contexts computed-field
     * resolution can run in, where a read tied to the current site would miss
     * the origin values.
     */
    private function settingValue(string $handle): mixed
    {
        $variables = GlobalSet::findByHandle(self::SETTINGS_GLOBAL)?->inDefaultSite();

        return $variables instanceof Variables ? $variables->get($handle) : null;
    }
}
