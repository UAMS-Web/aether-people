<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Actions;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Statamic\Actions\Action;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Merge\PeopleMergeService;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;

use function Statamic\trans as __;

/**
 * CP bulk action that starts a duplicate-people merge (UAMS-Web/uams-statamic-archive#838).
 *
 * Selecting two or more `people` entries and running this action sends the
 * operator to the reconciliation screen (it does not merge anything itself — the
 * actual merge is previewed and explicitly confirmed there). The action is only
 * offered on the `people` collection, requires two or more entries, and is gated
 * by the `merge people entries` permission. A user the people editing scope
 * covers may run it only on entries in their scope (UAMS-Web/uams-statamic-archive#3197).
 */
class MergePeople extends Action
{
    /**
     * Icon shown next to the action in the CP.
     *
     * @var string
     */
    public $icon = 'arrows-merge-alt';

    /**
     * No confirmation modal — confirmation happens on the reconciliation screen.
     *
     * @var bool
     */
    protected $confirm = false;

    /**
     * The action's display title.
     */
    #[\Override]
    public static function title(): string
    {
        $title = __('Merge duplicates…');

        // Both branches are the English string under the test locale, so
        // TernaryNegated is an equivalent mutant.
        // @pest-mutate-ignore: TernaryNegated
        return \is_string($title) ? $title : 'Merge duplicates…';
    }

    /**
     * Only show for individual `people` entries.
     *
     * @param  mixed  $item
     */
    #[\Override]
    public function visibleTo($item): bool
    {
        return $item instanceof Entry
            && $item->collectionHandle() === PeopleMergeService::peopleCollection();
    }

    /**
     * Only show in bulk when two or more people entries are selected.
     *
     * @param  Collection<int, mixed>  $items
     */
    #[\Override]
    public function visibleToBulk($items): bool
    {
        // A merge needs at least two entries to collapse together
        if ($items->count() < 2) {
            return false;
        }

        // Every selected item must be a people entry.
        // parent::visibleToBulk() already returns bool; the cast is for
        // static analysis only.
        // @pest-mutate-ignore: RemoveBooleanCast
        return (bool) parent::visibleToBulk($items);
    }

    /**
     * Authorize against the dedicated merge permission, and for a user the
     * people editing scope covers, against the entry being in their scope (UAMS-Web/uams-statamic-archive#3197).
     *
     * @param  Authorizable|null  $user
     * @param  mixed  $item
     */
    #[\Override]
    public function authorize($user, $item): bool
    {
        // Authorizable::can() is bool; the cast is for static analysis only.
        // @pest-mutate-ignore: RemoveBooleanCast
        if (! (bool) ($user?->can('merge people entries') ?? false)) {
            return false;
        }

        $scope = app(PeopleEditingScope::class);

        // Administrators may merge any people entry
        if (! $user instanceof Authenticatable || ! $scope->appliesTo($user)) {
            return true;
        }

        return $item instanceof Entry && $scope->allows($user, $item);
    }

    /**
     * The action itself is a no-op; the merge is performed on the reconciliation
     * screen after the operator chooses the survivor and per-field sources.
     *
     * @param  Collection<int, mixed>  $items
     * @param  array<string, mixed>  $values
     */
    #[\Override]
    public function run($items, $values): void
    {
        //
    }

    /**
     * Send the operator to the reconciliation screen for the selected entries.
     *
     * @param  Collection<int, Entry>  $items
     * @param  array<string, mixed>  $values
     */
    #[\Override]
    public function redirect($items, $values): string
    {
        $ids = $items
            ->map(function (Entry $entry): ?string {
                $id = $entry->id();

                return \is_string($id) ? $id : null;
            })
            ->filter()
            ->values()
            ->implode(',');

        $url = cp_route('people-merge.show', ['ids' => $ids]);

        // Defensive fallback if cp_route() is ever non-string; not reachable
        // with a registered people-merge.show route.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        return \is_string($url) ? $url : '';
    }

    /**
     * Navigating to a full CP screen is a full-page transition.
     */
    #[\Override]
    public function triggersFullPageRefresh(): bool
    {
        return true;
    }

    /**
     * The reconciliation screen carries its own unsaved-state handling.
     */
    #[\Override]
    public function bypassesDirtyWarning(): bool
    {
        return true;
    }
}
