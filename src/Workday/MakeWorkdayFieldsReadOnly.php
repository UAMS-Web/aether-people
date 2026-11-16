<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Workday;

use Statamic\Entries\Entry;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;
use UAMSWeb\Aether\People\Support\WorkdayGender;
use UAMSWeb\Aether\People\Support\WorkdayManagedFields;

/**
 * Shows the Workday-managed `people` fields as read-only in the Control Panel
 * to every user they apply to (UAMS-Web/uams-statamic-archive#3186, decided on UAMS-Web/uams-statamic-archive#3130).
 *
 * Statamic shares one blueprint object between the entries of a request, so the
 * visibility is set in both directions on every dispatch rather than only
 * switched on: a trainee field locked for one entry must not stay greyed out on
 * the next. The field's own visibility is kept under a private config key the
 * first time it is changed, and is what an unlocked field returns to. This is
 * only the editing surface; `RestoreWorkdayManagedFields` is what keeps a
 * locked value unchanged on save.
 *
 * It also marks `workday_gender` "Overridden" when the entry's `gender` differs
 * from it (UAMS-Web/uams-statamic-archive#3133), for every user, administrators included, since the marker
 * only describes the entry and locks nothing.
 */
class MakeWorkdayFieldsReadOnly
{
    /**
     * The config key holding a field's visibility before this listener changed it.
     */
    private const string BASE_VISIBILITY_KEY = 'workday_managed_base_visibility';

    /**
     * The `collections.` namespace of the `people` entry blueprints.
     */
    // A constant expression is never a covered line, so the mutation runner skips it as
    // uncovered. Applied by hand, each of the three concatenation mutants fails 3 tests
    // in WorkdayGenderTest.
    // @pest-mutate-ignore: ConcatRemoveLeft, ConcatRemoveRight, ConcatSwitchSides
    private const string PEOPLE_NAMESPACE = 'collections.'.PeopleEditingScope::COLLECTION;

    /**
     * The config key holding `workday_gender`'s instructions before the
     * "Overridden" marker was added to them.
     */
    private const string BASE_INSTRUCTIONS_KEY = 'workday_gender_base_instructions';

    /**
     * The marker shown on `workday_gender` when `gender` differs from it (UAMS-Web/uams-statamic-archive#3133).
     */
    public const string OVERRIDDEN_MARKER = '**Overridden**: Gender above differs from the value in Workday.';

    public function __construct(
        protected WorkdayManagedFields $fields,
        protected WorkdayGender $gender
    ) {}

    /**
     * Set each Workday-managed field's visibility for the signed-in user.
     *
     * @param  EntryBlueprintFound  $event  The resolved blueprint, and the entry when editing
     */
    public function handle(EntryBlueprintFound $event): void
    {
        $blueprint = $event->blueprint;

        if (! $blueprint instanceof Blueprint || $blueprint->namespace() !== self::PEOPLE_NAMESPACE) {
            return;
        }

        // Read the stored data the locks are decided from, only for a user held to them
        $entry = $event->entry;
        $applies = $this->fields->appliesTo(User::current());
        $storedData = $applies && $entry instanceof Entry ? $this->fields->storedData($entry) : [];

        $handles = [...WorkdayManagedFields::ALWAYS_LOCKED, ...WorkdayManagedFields::LOCKED_WHEN_SUPPLIED];

        foreach ($handles as $handle) {
            $field = $blueprint->field($handle);

            if (! $field instanceof Field) {
                continue;
            }

            // Remember the field's own visibility before the first change to it
            $config = $field->config();
            $base = $config[self::BASE_VISIBILITY_KEY] ?? $config['visibility'] ?? 'visible';

            // Lock the field for a user held to it, otherwise give back its own visibility
            $locked = $applies && $this->fields->isLocked($handle, $storedData);

            $blueprint->ensureFieldHasConfig($handle, [
                'visibility' => $locked ? 'read_only' : $base,
                self::BASE_VISIBILITY_KEY => $base,
            ]);
        }

        $this->markOverride($blueprint, $entry);
    }

    /**
     * Add the "Overridden" marker to `workday_gender` when the entry's `gender`
     * differs from it, for every user, and take it away otherwise.
     *
     * @param  Blueprint  $blueprint  The `people` blueprint, shared within a request
     * @param  mixed  $entry  The entry being edited, or null when creating one
     */
    private function markOverride(Blueprint $blueprint, mixed $entry): void
    {
        $field = $blueprint->field(WorkdayGender::WORKDAY_FIELD);

        if (! $field instanceof Field) {
            return;
        }

        // Remember the field's own instructions before the first change to them
        $config = $field->config();
        $base = $config[self::BASE_INSTRUCTIONS_KEY] ?? $config['instructions'] ?? null;
        $base = \is_string($base) ? $base : '';

        // Lead with the marker on an overridden entry, otherwise give back the instructions
        $overridden = $entry instanceof Entry && $this->gender->isOverridden($entry);
        $instructions = $overridden ? trim(self::OVERRIDDEN_MARKER.' '.$base) : $base;

        $blueprint->ensureFieldHasConfig(WorkdayGender::WORKDAY_FIELD, [
            'instructions' => $instructions,
            self::BASE_INSTRUCTIONS_KEY => $base,
        ]);
    }
}
