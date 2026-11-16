<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\EditingScope;

use Exception;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\User;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;

/**
 * Refuses a scoped editor's save of a `people` entry that would leave the entry
 * outside their organizations (UAMS-Web/uams-statamic-archive#3182, decided on UAMS-Web/uams-statamic-archive#3128).
 *
 * The entry policy stops a scoped editor opening an entry that is out of scope,
 * but creating an entry is authorized against the collection alone, and a change
 * can remove the organization that put the entry in scope. This check runs on the
 * entry as it is about to be written, so a new entry must name one of the
 * editor's organizations, and a change must keep one.
 *
 * It applies only while a scoped user is signed in, so imports, console commands
 * and administrators are unaffected. A change to a published entry under
 * revisions is written as a working copy rather than saved, so it does not reach
 * this check; the policy has already limited it to entries in scope.
 */
class EnforcePeopleEditingScope
{
    public function __construct(
        protected PeopleEditingScope $scope
    ) {}

    /**
     * Abort the save when a scoped user's `people` entry would fall out of scope.
     *
     * @throws Exception When the entry would name none of the user's organizations
     */
    public function handle(EntrySaving $event): void
    {
        $entry = $event->entry;

        // Equivalent: governs() independently rejects every non-entry value.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $entry instanceof Entry || ! $this->scope->governs($entry)) {
            return;
        }

        $user = User::current();

        if ($user === null || ! $this->scope->appliesTo($user)) {
            return;
        }

        if ($this->scope->allows($user, $entry)) {
            return;
        }

        throw new Exception(
            'This person must stay linked to an organization you edit for. '
            .'Add one of your organizations to a role or service line before saving.'
        );
    }
}
