<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Entry;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;

/**
 * Refuses an organization at the top of the `organizations` tree (a brand).
 *
 * A user group covers each organization it names and everything below it, so a
 * brand would grant its members nearly every `people` entry at once. The group
 * blueprint's `organizations` field carries this rule (UAMS-Web/uams-statamic-archive#3182).
 */
class NotTopLevelOrganization implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute  The name of the attribute being validated.
     * @param  mixed  $value  An organization id or a list of them.
     * @param  Closure  $fail  Adds a validation error message.
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Normalize the field value to a list of ids
        $ids = \is_array($value) ? $value : [$value];

        // Read the top of the tree once for every selected id
        $topLevel = app(PeopleEditingScope::class)->topLevelOrganizationIds();

        foreach ($ids as $id) {
            // Skip anything that is not an id
            if (! \is_string($id)) {
                continue;
            }

            // Skip every organization below the top
            if (! \in_array($id, $topLevel, true)) {
                continue;
            }

            // Name the refused organization, falling back to its id
            $organization = Entry::find($id);
            // phpstan requires this type guard; a tree drops a node whose entry is
            // missing, so every top-level id resolves to an entry.
            // @pest-mutate-ignore: InstanceOfToTrue
            $title = $organization instanceof EntryItem ? $organization->get('title') : null;

            $fail(__('aether-people::validation.not_top_level_organization.top_level', [
                'title' => \is_string($title) ? $title : $id,
            ]));

            return;
        }
    }
}
