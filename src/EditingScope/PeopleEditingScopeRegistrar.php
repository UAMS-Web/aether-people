<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\EditingScope;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Statamic\Entries\Collection as EntryCollection;
use Statamic\Events\EntrySaving;
use Statamic\Facades\User;
use Statamic\Hooks\CP\EntriesIndexQuery;
use Statamic\Query\Builder as QueryBuilder;
use UAMSWeb\Aether\People\Rules\NotTopLevelOrganization;
use UAMSWeb\Aether\People\ServiceProvider;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;

/**
 * Registers the `people` editing scope: the listing hook, the two save listeners and the validation rule.
 *
 * Called from the service provider's `bootAddon()`. It sits in its own class so a change to the editing
 * scope's boot logic mutates this file and not the provider.
 */
class PeopleEditingScopeRegistrar
{
    /**
     * Scope Control Panel editing of `people` entries to the organizations of the editor's user groups.
     *
     * The entry policy (bound in {@see ServiceProvider::register()}) stops a scoped editor opening an entry outside their
     * organizations. This keeps those entries out of the listing and its search, refuses a save that would
     * leave the entry out of scope, and registers the validation rule the user group blueprint names. The
     * listeners are subscribed here rather than found by Statamic, so the order is fixed and the provider
     * layer flag cannot switch them off.
     */
    public function register(): void
    {
        // Keep out-of-scope entries out of the listing and its search results
        EntriesIndexQuery::hook('query', function (mixed $payload, \Closure $next): mixed {
            /** @var object{query: mixed, collection: mixed} $payload */
            $collection = $payload->collection;
            $query = $payload->query;
            $user = User::current();
            $scope = app(PeopleEditingScope::class);

            if (
                $collection instanceof EntryCollection
                && $collection->handle() === PeopleEditingScope::COLLECTION
                && $query instanceof QueryBuilder
                && $user !== null
                && $scope->appliesTo($user)
            ) {
                $query->whereIn('id', $scope->editableEntryIds($user));
            }

            return $next($payload);
        });

        // Refuse a save that leaves a `people` entry out of scope, then one that syncs an out-of-scope person
        Event::listen(EntrySaving::class, EnforcePeopleEditingScope::class);
        Event::listen(EntrySaving::class, EnforcePeopleScopeOnProviderSync::class);

        // Register the rule the user group blueprint names, reporting its failure on the field
        Validator::extend('not_top_level_organization', function (string $attribute, mixed $value, array $parameters, ValidatorContract $validator): bool {
            (new NotTopLevelOrganization)->validate($attribute, $value, function (string $message) use ($attribute, $validator): void {
                $validator->errors()->add($attribute, $message);
            });

            // Report success: a failure is already on the error bag, and a false return would add a second message
            return true;
        });
    }
}
