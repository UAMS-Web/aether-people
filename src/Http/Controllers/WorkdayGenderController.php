<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Http\Controllers\CP\CpController;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;
use UAMSWeb\Aether\People\Support\WorkdayGender;

/**
 * Clears a flagged gender override from the `WorkdayGenderOverrides` dashboard
 * widget (UAMS-Web/uams-statamic-archive#3187, decided on UAMS-Web/uams-statamic-archive#3132), either by confirming the override or by
 * taking Workday's value.
 *
 * Each action needs the same right as editing the entry, so a user the people
 * editing scope covers can clear only the entries in their scope. The change is
 * saved straight to the entry, as the Workday sync's own writes are: taking
 * Workday's value writes the value the sync would have written had the entry
 * not been overridden.
 */
class WorkdayGenderController extends CpController
{
    /**
     * Keep the entry's `gender` and clear its flag.
     *
     * @param  string  $id  The person entry's id
     * @param  WorkdayGender  $gender  The gender override rule
     */
    public function confirm(string $id, WorkdayGender $gender): RedirectResponse
    {
        $entry = $this->editableEntry($id);

        $gender->confirmOverride($entry);
        $entry->save();

        $this->success(__('Gender override confirmed.'));

        return back();
    }

    /**
     * Set the entry's `gender` to Workday's value and clear its flag.
     *
     * @param  string  $id  The person entry's id
     * @param  WorkdayGender  $gender  The gender override rule
     */
    public function take(string $id, WorkdayGender $gender): RedirectResponse
    {
        $entry = $this->editableEntry($id);

        $gender->takeWorkdayValue($entry);
        $entry->save();

        $this->success(__('Gender set to the value in Workday.'));

        return back();
    }

    /**
     * The person entry with the id, once the user is shown to be allowed to
     * edit it. A missing entry, or one outside `people`, is a 404.
     *
     * @param  string  $id  The person entry's id
     */
    private function editableEntry(string $id): Entry
    {
        $entry = EntryFacade::find($id);

        if (! $entry instanceof Entry || $entry->collectionHandle() !== PeopleEditingScope::COLLECTION) {
            abort(404);
        }

        $this->authorize('edit', $entry);

        return $entry;
    }
}
