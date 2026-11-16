<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Policies;

use Statamic\Entries\Entry;
use Statamic\Facades\User;
use Statamic\Policies\EntryPolicy;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;

/**
 * Statamic's entry policy, with `people` entries limited to the organizations of
 * the user's groups (UAMS-Web/uams-statamic-archive#3182, decided on UAMS-Web/uams-statamic-archive#3128).
 *
 * The permission checks are Statamic's own; this only adds that a scoped user may
 * view, edit, delete or publish a `people` entry when the entry is in their scope
 * (see {@see PeopleEditingScope}). Every other collection, and every administrator,
 * is left exactly as Statamic decides. The role scoped editors hold carries no
 * publish permission, so in practice only administrators publish; the scope on
 * publishing covers a role that is later given that permission.
 */
class ScopedEntryPolicy extends EntryPolicy
{
    /**
     * Determine if an entry can be viewed.
     *
     * @param  mixed  $user  The authenticated user
     * @param  mixed  $entry  The entry to view
     * @return bool
     */
    #[\Override]
    public function view($user, $entry)
    {
        return parent::view($user, $entry) && $this->inScope($user, $entry);
    }

    /**
     * Determine if an entry can be edited.
     *
     * @param  mixed  $user  The authenticated user
     * @param  mixed  $entry  The entry to edit
     * @return bool
     */
    #[\Override]
    public function edit($user, $entry)
    {
        return parent::edit($user, $entry) && $this->inScope($user, $entry);
    }

    /**
     * Determine if an entry can be deleted.
     *
     * @param  mixed  $user  The authenticated user
     * @param  mixed  $entry  The entry to delete
     * @return bool
     */
    #[\Override]
    public function delete($user, $entry)
    {
        return parent::delete($user, $entry) && $this->inScope($user, $entry);
    }

    /**
     * Determine if an entry, or entries in a collection, can be published.
     *
     * A collection is passed when the Control Panel asks whether publishing is
     * offered at all; only a single entry is scoped.
     *
     * @param  mixed  $user  The authenticated user
     * @param  mixed  $entry  The entry, or the collection, to publish in
     * @return bool
     */
    #[\Override]
    public function publish($user, $entry)
    {
        return parent::publish($user, $entry) && $this->inScope($user, $entry);
    }

    /**
     * Whether the entry is outside the scoped collection, or the user may act on it.
     *
     * @param  mixed  $user  The authenticated user
     * @param  mixed  $entry  The entry acted on
     */
    private function inScope(mixed $user, mixed $entry): bool
    {
        $scope = app(PeopleEditingScope::class);

        if (! $scope->governs($entry)) {
            return true;
        }

        $user = User::fromUser($user);

        // phpstan requires this null guard; the parent policy methods read the user first and
        // fail on null, so no caller reaches it.
        // @pest-mutate-ignore: FalseToTrue, RemoveEarlyReturn
        if ($user === null) {
            return false;
        }

        if (! $scope->appliesTo($user)) {
            return true;
        }

        /** @var Entry $entry */
        return $scope->allows($user, $entry);
    }
}
