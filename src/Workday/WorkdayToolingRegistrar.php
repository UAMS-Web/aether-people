<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Workday;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\EntrySaving;
use Statamic\Events\RevisionSaving;
use Statamic\Statamic;
use UAMSWeb\Aether\People\Http\Controllers\WorkdayGenderController;

/**
 * Registers the Workday tooling: the read-only and restore listeners and the gender override review routes.
 *
 * Called from the service provider's `bootAddon()`. It sits in its own class so a change to the Workday
 * tooling's boot logic mutates this file and not the provider.
 */
class WorkdayToolingRegistrar
{
    /**
     * Hold the Workday-managed `people` fields and serve the gender override review routes.
     *
     * The listeners sit under `src/Workday` rather than `src/Listeners`, so Statamic does not find them and
     * the provider layer flag cannot switch them off: without them a Control Panel user could change what
     * Workday owns. The `WorkdayGenderOverrides` widget under `src/Widgets` is found by Statamic; the routes
     * here are the two actions it links to. The save listener runs after the editing scope's two.
     */
    public function register(): void
    {
        // Show the Workday-managed fields read-only when a `people` blueprint is resolved
        Event::listen(EntryBlueprintFound::class, MakeWorkdayFieldsReadOnly::class);

        // Keep their stored values when an entry is saved, and when a working copy or revision is
        Event::listen(EntrySaving::class, RestoreWorkdayManagedFields::class.'@handleEntrySaving');
        Event::listen(RevisionSaving::class, RestoreWorkdayManagedFields::class.'@handleRevisionSaving');

        // Serve the two actions the gender override widget links to
        Statamic::pushCpRoutes(function (): void {
            Route::prefix('people/workday-gender/{id}')->name('people-workday-gender.')->group(function (): void {
                Route::post('confirm', [WorkdayGenderController::class, 'confirm'])->name('confirm');
                Route::post('take', [WorkdayGenderController::class, 'take'])->name('take');
            });
        });
    }
}
