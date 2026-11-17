<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\UpdateScripts;

use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\UpdateScripts\UpdateScript;
use UAMSWeb\Aether\People\Support\PeopleCollection;
use UAMSWeb\Aether\People\Support\TraineeType;

/**
 * Carries every `people` entry's old `resident_query` and `fellow_query` answers over to the
 * `trainee_type` field (UAMS-Web/aether-people#58).
 *
 * Statamic finds the script in this folder by itself and runs it with `php please updates:run`, which
 * `statamic:up` also calls after an update. It is safe to run again: an entry with neither old field is
 * left as it is, so a second run changes nothing. The entries are saved without events, because the
 * carried value changes no eligibility, so no listener has anything to refresh.
 */
final class CarryTraineeTypeOver extends UpdateScript
{
    /**
     * Run on every update, since the script finds its own work: no entry carries an old field once it has run.
     *
     * @param  string  $newVersion  The version being updated to
     * @param  string  $oldVersion  The version being updated from
     */
    #[\Override]
    public function shouldUpdate($newVersion, $oldVersion): bool
    {
        return true;
    }

    /**
     * Carry the old fields over on every entry that has them.
     */
    #[\Override]
    public function update(): void
    {
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();

        // Read every person of every site, since each localization can carry its own copy
        $query->where('collection', PeopleCollection::handle());

        /** @var iterable<mixed> $entries */
        $entries = $query->get();

        foreach ($entries as $entry) {
            // An entry query returns only entries; the guard is for the static analysis.
            // @pest-mutate-ignore: InstanceOfToTrue, ContinueToBreak
            if (! $entry instanceof Entry) {
                continue;
            }

            /** @var Collection<array-key, mixed> $stored */
            $stored = $entry->data();

            $carried = TraineeType::carryOver($stored->all());

            if ($carried === null) {
                continue;
            }

            $entry->data($carried);
            $entry->saveQuietly();
        }
    }
}
