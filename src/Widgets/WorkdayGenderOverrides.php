<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Widgets;

use Illuminate\View\View;
use Statamic\Auth\User;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User as UserFacade;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Widgets\Widget;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;
use UAMSWeb\Aether\People\Support\WorkdayGender;

/**
 * CP dashboard widget listing the `people` entries whose gender override Workday
 * has since disagreed with (UAMS-Web/uams-statamic-archive#3187, decided on UAMS-Web/uams-statamic-archive#3132).
 *
 * Each row offers the two ways to clear the flag: confirm the override, or take
 * Workday's value. It is shown only to users who may edit people entries, and a
 * user the people editing scope covers sees only the entries in their scope,
 * since the actions refuse any other. Nobody is emailed about a flag; this list
 * is where it surfaces.
 */
class WorkdayGenderOverrides extends Widget
{
    /**
     * The Blade view the widget renders, found through the add-on's view location, after a host's copy.
     *
     * @var view-string
     */
    private const string VIEW = 'widgets.workday_gender_overrides';

    /**
     * The permission required to see and clear flagged overrides.
     */
    private const string PERMISSION = 'edit people entries';

    /**
     * Default maximum entries to display.
     */
    private const int DEFAULT_LIMIT = 25;

    /**
     * Render the widget, or nothing when the viewer cannot edit people.
     */
    #[\Override]
    public function html(): string|View
    {
        /** @var User|null $user */
        $user = UserFacade::current();

        if ($user === null || ! $user->can(self::PERMISSION)) {
            return '';
        }

        return view(self::VIEW, [
            'title' => $this->config('title', __('Gender Overrides to Review')),
            'rows' => $this->rows($user),
        ]);
    }

    /**
     * The flagged entries the viewer may edit, as display rows, up to the limit.
     *
     * @param  User  $user  The viewer
     * @return list<array{id: string, title: string, edit_url: string, gender: string, workday_gender: string, confirm_url: string, take_url: string}>
     */
    private function rows(User $user): array
    {
        // Resolve the display limit from config
        $limitValue = $this->config('limit', self::DEFAULT_LIMIT);
        $limit = \is_scalar($limitValue) ? (int) $limitValue : self::DEFAULT_LIMIT;
        $limit = $limit < 1 ? self::DEFAULT_LIMIT : $limit;

        $gender = app(WorkdayGender::class);
        $scope = app(PeopleEditingScope::class);

        // Find the flagged people entries. The flag is only a first cut, since
        // isFlagged() below re-reads it with the override, so dropping it changes
        // which entries are loaded but not which are listed: removing that call
        // is an equivalent mutant, and an annotation cannot reach the statement
        // it sits on.
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', PeopleEditingScope::COLLECTION);
        $query->where(WorkdayGender::REVIEW_KEY, true);

        // Limit a scoped viewer to the entries they may edit, since the actions refuse any other
        if ($scope->appliesTo($user)) {
            $query->whereIn('id', $scope->editableEntryIds($user));
        }

        /** @var iterable<mixed> $entries */
        $entries = $query->get();

        $rows = [];

        foreach ($entries as $entry) {
            // Keep entries still flagged and overridden. An entry query returns
            // only entries; the guard is for the static analysis.
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $entry instanceof Entry) {
                continue;
            }

            if (! $gender->isFlagged($entry)) {
                continue;
            }

            $id = $this->stringOr($entry->id());

            $rows[] = [
                'id' => $id,
                'title' => $this->displayTitle($entry, $id),
                'edit_url' => $this->stringOr($entry->editUrl()),
                'gender' => $this->stringOr($entry->get(WorkdayGender::FIELD)),
                'workday_gender' => $this->stringOr($entry->get(WorkdayGender::WORKDAY_FIELD)),
                'confirm_url' => $this->stringOr(cp_route('people-workday-gender.confirm', ['id' => $id])),
                'take_url' => $this->stringOr(cp_route('people-workday-gender.take', ['id' => $id])),
            ];
        }

        // Order by title so the list is stable between loads, then cap it
        usort($rows, static fn (array $a, array $b): int => strcasecmp($a['title'], $b['title']));

        return \array_slice($rows, 0, $limit);
    }

    /**
     * Resolve a human-readable title for a person entry, falling back to slug then id.
     *
     * @param  Entry  $entry  The person entry
     * @param  string  $id  The entry's id
     */
    private function displayTitle(Entry $entry, string $id): string
    {
        $title = $entry->value('title');

        if (\is_string($title) && $title !== '') {
            return $title;
        }

        $slug = $entry->slug();

        return \is_string($slug) ? $slug : $id;
    }

    /**
     * The value when it is a string, and otherwise the empty string.
     *
     * @param  mixed  $value  The value to show
     */
    private function stringOr(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
