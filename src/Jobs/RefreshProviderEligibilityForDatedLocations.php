<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Jobs;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;
use UAMSWeb\Aether\People\Support\ProviderEligibilityIndexRefresher;

/**
 * Re-indexes provider eligibility for the people linked to practice locations whose date has passed
 * since the last run (UAMS-Web/uams-statamic-archive#3178).
 *
 * `clinical_locations` is dated with `future: private`, so a location published with a future date is
 * scheduled, and a scheduled location confers clinical provider profile eligibility only from its date
 * (UAMS-Web/uams-statamic-archive#3176). When that date passes, the location's status changes with no save, so nothing refreshes the
 * linked people's `clinical_provider_profile_eligible_query` index; this job does, through
 * {@see ProviderEligibilityIndexRefresher}, the same path the save and delete listener takes.
 *
 * A new job rather than an extension of {@see ApplyScheduledPublishing}: that job writes `publish_at`
 * and `unpublish_at` on undated collections and saves entries, while this one saves nothing and only
 * re-reads a status that `date_behavior` already resolves. It also belongs to the clinical provider
 * layer, which moves into `uams-web/statamic-people` (#281), while scheduled publishing stays in the site.
 *
 * The time of the last run is kept in the cache. A run with no previous time, the first after a deploy
 * or after the cache is cleared, treats every location dated up to now as passed, which costs one wider
 * refresh and never misses a date. Each run reads only published locations.
 *
 * The job is unique while it is queued or running. The lock expires after the {@see UniqueFor} attribute's
 * seconds, so a run that never finished, such as one whose worker was killed, cannot block every
 * later dispatch (#488). A location's date then takes effect at most that much later.
 *
 * Dispatched by the schedule in `routes/console.php`, at the cadence in
 * `clinical_provider_profile.location_date_refresh.cron`.
 *
 * One hour is forty times the queue's default `retry_after` of 90 seconds, the longest a job can
 * run on those settings before the queue hands it out again. No production run has been timed.
 */
// A class attribute runs no line coverage can see, so the mutants of its number report as uncovered. The
// seconds are asserted exactly by reading the attribute in the job's test.
// @pest-mutate-ignore: DecrementInteger
// @pest-mutate-ignore: IncrementInteger
#[UniqueFor(3600)]
final class RefreshProviderEligibilityForDatedLocations implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The cache key holding the Unix time of the last run.
     */
    public const string LAST_RUN_KEY = 'clinical_provider_profile:location_date_refresh:last_run';

    public function handle(ProviderEligibilityIndexRefresher $refresher): void
    {
        $now = Carbon::now();
        $since = $this->lastRun();

        // Read every published location; their status may have changed with the date
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', 'clinical_locations');
        $query->where('published', true);

        /** @var Collection<int, Entry> $locations */
        $locations = $query->get();

        /** @var list<string> $passed */
        $passed = $locations
            ->filter(fn (Entry $location): bool => $this->datePassed($location, $since, $now))
            ->map(fn (Entry $location): mixed => $location->id())
            ->filter(fn (mixed $id): bool => \is_string($id))
            ->values()
            ->all();

        $refreshed = $refresher->refreshPeopleLinking($passed);

        Cache::forever(self::LAST_RUN_KEY, $now->getTimestamp());

        Log::info('Refreshed provider eligibility for dated locations.', [
            'locations' => \count($passed),
            'people' => $refreshed,
            'since' => $since?->toIso8601String(),
            'now' => $now->toIso8601String(),
        ]);
    }

    /**
     * The time of the last run, or null when none is recorded.
     */
    private function lastRun(): ?CarbonInterface
    {
        $timestamp = Cache::get(self::LAST_RUN_KEY);

        return \is_int($timestamp) ? Carbon::createFromTimestamp($timestamp) : null;
    }

    /**
     * Whether the location's date falls after the last run and at or before now.
     */
    private function datePassed(Entry $location, ?CarbonInterface $since, CarbonInterface $now): bool
    {
        // An undated collection's entries read no date, so they have none to pass
        $date = $location->date();

        if (! $date instanceof CarbonInterface || $date->greaterThan($now)) {
            return false;
        }

        return ! $since instanceof CarbonInterface || $date->greaterThan($since);
    }
}
