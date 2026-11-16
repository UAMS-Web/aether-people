<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Merge;

use Illuminate\Support\Facades\Route;
use Statamic\Facades\Permission;
use Statamic\Statamic;
use UAMSWeb\Aether\People\Http\Controllers\MergeController;

/**
 * Registers the people merge: the `merge people entries` permission and the Control Panel routes behind
 * the reconciliation screen.
 *
 * Called from the service provider's `bootAddon()`. The entry point is the `MergePeople` bulk action under
 * `src/Actions`, which Statamic finds on its own, so no nav item is registered.
 */
class PeopleMergeRegistrar
{
    /**
     * Register the permission and the reconciliation screen's routes.
     */
    public function register(): void
    {
        // The dedicated permission every merge endpoint requires
        Permission::group('people', 'People', function (): void {
            Permission::register('merge people entries')
                ->label(__('Merge duplicate people entries'));
        });

        // The reconciliation screen, and the preview and commit endpoints behind it
        Statamic::pushCpRoutes(function (): void {
            Route::prefix('people/merge')->name('people-merge.')->group(function (): void {
                Route::get('/', [MergeController::class, 'show'])->name('show');
                Route::post('preview', [MergeController::class, 'preview'])->name('preview');
                Route::post('commit', [MergeController::class, 'commit'])->name('commit');
            });
        });
    }
}
