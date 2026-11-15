<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\ComputedFields\ProviderPeople;

use Statamic\Entries\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Term;
use Statamic\Globals\Variables;
use Statamic\Stache\Repositories\EntryRepository as StacheEntryRepository;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Resolves everything the clinical provider lens's appointment CTA and Primary
 * Appointment Location block need, in one pass: eligibility, the provider's
 * published locations, the primary one, the portal inherited from it, and the
 * appointment line that portal implies.
 *
 * Why a resolver rather than Antlers: the CTA reproduces the WordPress block's
 * nine copy branches, and every one of them is keyed on facts that take a
 * relation walk to establish - is there a *published* location, how many, what
 * portal does the first one carry, does that portal name its own appointment
 * line. Antlers can walk a relation but cannot cheaply hoist the answers back
 * out of the loop, and the branch table is unreadable when each condition is an
 * inline query. The template keeps the branches; this keeps the facts.
 *
 * The portal is deliberately read from the provider's first published location
 * rather than from the provider. WordPress reads `physician_portal` off the
 * physician, but that field is a denormalized cache the plugin repopulates on
 * save from the provider's primary location - so the location is the source of
 * truth on both sides, and we read it directly.
 */
final readonly class AppointmentContextResolver
{
    /**
     * Taxonomy handle for the patient-portal terms, used to normalize a bare
     * stored slug into a `taxonomy::slug` reference.
     */
    private const string PORTAL_TAXONOMY = 'portals';

    /**
     * Portal slug meaning "this location has no patient portal". WordPress gates
     * the portal fragment on the slug not being this, so a `_none` portal is a
     * portal record but not an offered channel.
     */
    private const string PORTAL_SLUG_NONE = '_none';

    /**
     * Anchor the "contact the clinic directly" link targets when a provider
     * practices at more than one location - the on-page locations section.
     */
    private const string LOCATIONS_ANCHOR = '#locations';

    /**
     * Handle of the Find-a-Doc settings global.
     */
    private const string SETTINGS_GLOBAL = 'uams_fad_settings';

    /**
     * @param  StacheEntryRepository  $entries  Statamic Stache entry repository (`find`).
     */
    public function __construct(
        private StacheEntryRepository $entries
    ) {}

    /**
     * The appointment context for one provider.
     *
     * @return array{
     *     eligible: bool,
     *     location_valid: bool,
     *     location_count: int,
     *     primary_location_id: string|null,
     *     primary_location_title: string,
     *     primary_location_url: string,
     *     direct_url: string,
     *     direct_is_anchor: bool,
     *     show_portal: bool,
     *     portal_name: string,
     *     portal_url: string,
     *     referral_required: bool,
     *     accepting_patients: bool,
     *     main_line_enabled: bool,
     *     phone_tel: string,
     *     phone_display: string,
     *     phone_name: string
     * }
     */
    public function resolve(Entry $entry): array
    {
        $published = $this->publishedLocations($entry);
        $primary = $published[0] ?? null;
        $count = \count($published);

        $portal = $primary instanceof Entry ? $this->portalTerm($primary) : null;
        // The fallback is read only when a portal exists (`$showPortal` below), and it
        // never does on this branch, so any fallback string gives the same result.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $portalSlug = $portal instanceof LocalizedTerm ? $this->stringValue($portal->slug()) : '';

        // WordPress gates the portal fragment on a valid location, a portal term,
        // and a slug that is not `_none` - so `show_portal` always implies
        // `location_valid`, which is what makes several boolean combinations in
        // the branch table unreachable. A portal resolves only from the primary
        // location, so a portal term already implies that location exists.
        $showPortal = $portal instanceof LocalizedTerm
            && $portalSlug !== self::PORTAL_SLUG_NONE;

        $phone = $this->appointmentLine($portal);

        return [
            'eligible' => $this->isEligible($entry),
            'location_valid' => $primary instanceof Entry,
            'location_count' => $count,
            'primary_location_id' => $primary instanceof Entry ? ($this->stringValue($primary->id()) ?: null) : null,
            'primary_location_title' => $primary instanceof Entry ? $this->locationName($primary) : '',
            'primary_location_url' => $primary instanceof Entry ? $this->stringValue($primary->url()) : '',

            // One location links straight to it; more than one jumps to the
            // on-page list, exactly as WordPress does.
            // `$count === 1` holds only when a published location exists, so `$primary`
            // is always an Entry when the second conjunct is reached.
            // @pest-mutate-ignore: InstanceOfToTrue
            'direct_url' => $count === 1 && $primary instanceof Entry
                ? ($this->stringValue($primary->url()) ?: self::LOCATIONS_ANCHOR)
                : self::LOCATIONS_ANCHOR,
            'direct_is_anchor' => $count !== 1,

            'show_portal' => $showPortal,
            'portal_name' => $portal instanceof LocalizedTerm ? $this->stringValue($portal->get('title')) : '',
            'portal_url' => $portal instanceof LocalizedTerm ? $this->stringValue($portal->get('official_website')) : '',

            'referral_required' => $this->truthy($entry->get('referral_required')),
            'accepting_patients' => $this->truthy($entry->get('accepting_patients_query')),
            'main_line_enabled' => $this->mainLineEnabled(),

            'phone_tel' => $phone['tel'],
            'phone_display' => $phone['display'],
            'phone_name' => $phone['name'],
        ];
    }

    /**
     * Whether the provider gets an appointment CTA at all: a clinical role that
     * schedules appointments, appointed to the person rather than to a resource
     * - and never a resident, which forces eligibility off in WordPress too.
     */
    private function isEligible(Entry $entry): bool
    {
        if ($this->truthy($entry->get('resident_query'))) {
            return false;
        }

        return $this->truthy($entry->get('appointments_query'))
            && $this->truthy($entry->get('appointments_individual_query'));
    }

    /**
     * The provider's live `clinical_locations`, in the entered order.
     *
     * Publication status is checked here rather than left to the augmented
     * relation because the primary location, the count, and the direct-link
     * target all have to agree on the same filtered list - WordPress walks
     * `physician_locations` and skips anything not `publish`.
     *
     * The test is `status() === 'published'`, NOT `published()`. The two differ:
     * `published()` is the raw stored flag, while `clinical_locations` is a DATED
     * collection with `date_behavior: {future: private}`, so a flagged-published
     * entry dated tomorrow has status `scheduled` and is excluded from the
     * augmented relation every template gate walks. Reading the flag here made
     * the CTA name a clinic, and the primary-location block link to a URL, that
     * the page's own Locations section never rendered - a 404 for the patient.
     *
     * @return list<Entry>
     */
    private function publishedLocations(Entry $entry): array
    {
        $ids = $entry->get('clinical_locations', []);

        if (! \is_array($ids) || $ids === []) {
            return [];
        }

        $locations = [];

        foreach ($ids as $id) {
            if (! \is_string($id)) {
                continue;
            }

            // An empty id that got past this guard finds nothing (`find('')` is null),
            // so the list of published locations is the same.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($id === '') {
                continue;
            }

            $location = $this->entries->find($id);

            if ($location instanceof Entry && $location->status() === 'published') {
                $locations[] = $location;
            }
        }

        return $locations;
    }

    /**
     * A location's patient-facing name: the clean `generic_name` when set, else
     * the (often disambiguated) entry title - the same precedence the location
     * card uses (UAMS-Web/uams-statamic-archive#1188), so the CTA and the cards never name it differently.
     */
    private function locationName(Entry $location): string
    {
        $generic = $this->stringValue($location->get('generic_name'));

        return $generic !== '' ? $generic : $this->stringValue($location->get('title'));
    }

    /**
     * The patient-portal term the given location carries, or null.
     */
    private function portalTerm(Entry $location): ?LocalizedTerm
    {
        $portals = $location->get('portals');

        // The field is a `max_items: 1` terms field, so it stores either a bare
        // reference or a one-element list depending on how it was written.
        $reference = \is_array($portals) ? ($portals[0] ?? null) : $portals;

        // An empty reference that got past this guard looks up `portals::`, which
        // finds no term, so no portal resolves either way.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if (! \is_string($reference) || $reference === '') {
            return null;
        }

        $found = Term::find(
            str_contains($reference, '::') ? $reference : self::PORTAL_TAXONOMY.'::'.$reference
        );

        // The term query returns a LocalizedTerm or null, so this test is already
        // true for every non-null result.
        // @pest-mutate-ignore: InstanceOfToTrue
        if ($found instanceof LocalizedTerm) {
            return $found;
        }

        if (! $found instanceof TaxonomyTerm) {
            return null;
        }

        $default = $found->inDefaultLocale();

        return $default instanceof LocalizedTerm ? $default : null;
    }

    /**
     * The appointment line to offer: the portal's own number and spoken name when
     * it carries them, otherwise the Find-a-Doc system default.
     *
     * WordPress hard-codes a slug switch here (`ach-mychart` → Arkansas
     * Children's, `my-healthevet` → CAVHS). Reading the pair off the portal term
     * instead means a new portal needs no template change.
     *
     * The number and its spoken name fall back TOGETHER, not independently. The
     * template renders them as one sentence - "by calling {name} at {number}" -
     * so a portal that supplies only one half would otherwise label Arkansas
     * Children's number as the UAMS Health line, telling a patient to call one
     * organization by another's name. A half-filled portal is treated as
     * unconfigured and takes the system default for both.
     *
     * @return array{tel: string, display: string, name: string}
     */
    private function appointmentLine(?LocalizedTerm $portal): array
    {
        // Without a portal the other line's fallback stays '', which alone sends the
        // pair to the system default below, so either fallback string gives the same
        // result.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $number = $portal instanceof LocalizedTerm ? $this->stringValue($portal->get('appointment_phone')) : '';
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $name = $portal instanceof LocalizedTerm ? $this->stringValue($portal->get('appointment_phone_name')) : '';

        if ($number === '' || $name === '') {
            $number = $this->stringValue($this->settingValue('appointment_phone_default'));
            $name = $this->stringValue($this->settingValue('appointment_phone_name_default'));
        }

        return [
            'tel' => $this->telDigits($number),
            'display' => $this->displayNumber($number),
            'name' => $name,
        ];
    }

    /**
     * Whether the site-wide toggle allows mentioning the main appointment line.
     *
     * Defaults to true when unset, matching the field's own `default: true` and
     * WordPress, where an unset option is falsy but the field ships enabled.
     */
    private function mainLineEnabled(): bool
    {
        $value = $this->settingValue('provider_mainline');

        return $value === null || $this->truthy($value);
    }

    /**
     * The raw stored value of one Find-a-Doc setting, or null when unset.
     *
     * The Find-a-Doc settings global is rooted at the `default` site and its
     * fields are non-localizable, so `inDefaultSite()` resolves them from any
     * site context (UAMS-Web/uams-statamic-archive#1698). Computed-field resolution can run outside the
     * `health` site (the provider API, a queue), where a read tied to the
     * current site would miss the origin values.
     */
    private function settingValue(string $handle): mixed
    {
        $variables = GlobalSet::findByHandle(self::SETTINGS_GLOBAL)?->inDefaultSite();

        return $variables instanceof Variables ? $variables->get($handle) : null;
    }

    /**
     * Digits only, for a `tel:` href - a stored number may carry dashes, spaces,
     * parentheses, or the `+` of an E.164 value.
     */
    private function telDigits(string $number): string
    {
        // preg_replace() returns null only for an invalid pattern, and this one is
        // fixed, so the cast never changes the value.
        // @pest-mutate-ignore: RemoveStringCast
        return (string) preg_replace('/\D+/', '', $number);
    }

    /**
     * A North-American number in national display form, `(501) 686-8000`.
     *
     * Both fields that feed this are the `phone` fieldtype, which STORES E.164
     * (`+15016868000`) whatever the editor typed - so a bare ten-digit test never
     * matched and the CTA printed the raw `+15016868000` at a patient. The leading
     * `1` country code is stripped before formatting. Anything that is still not
     * ten digits (an international number, a vanity string like `1-800-UAMS-DOC`)
     * is returned as entered rather than mangled or hidden.
     */
    private function displayNumber(string $number): string
    {
        $digits = $this->telDigits($number);

        if (\strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        if (\strlen($digits) !== 10) {
            return $number;
        }

        return \sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
    }

    /**
     * Narrow an arbitrary stored value to a trimmed string, or '' when non-scalar.
     */
    private function stringValue(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Whether a stored `true_false` answer is an affirmative. The stored value is
     * a raw bool (or an importer's string), not the `LabeledValue` augmentation
     * produces, so this reads the storage shape.
     */
    private function truthy(mixed $value): bool
    {
        if (\is_string($value)) {
            return \in_array(strtolower(trim($value)), ['1', 'true', 'yes'], true);
        }

        return $value === true || $value === 1;
    }
}
