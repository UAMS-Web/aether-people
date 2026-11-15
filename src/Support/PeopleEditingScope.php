<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Statamic\Auth\UserGroup;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Sites\Site as SiteItem;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;

/**
 * Scopes Control Panel editing of `people` entries to the organizations of the
 * editor's user groups (UAMS-Web/uams-statamic-archive#3182, decided on UAMS-Web/uams-statamic-archive#3128).
 *
 * A user group's `organizations` field names the organizations its members may
 * edit. A group covers each organization it names and every organization below
 * it in the `organizations` tree, never one above it. A top-level organization
 * (a brand) covers nothing: the group blueprint refuses one on save, and a group
 * saved another way still grants nothing through it. A `people` entry is in a
 * user's scope when any organization the entry links to is covered. The tree is
 * read on every call rather than cached, so a move in it takes effect on the next
 * request. The entry's organizations are read from every
 * `entries` field that targets the `organizations` collection, whether it sits
 * at the top of the blueprint (`service_line`, `resident_academic_department`)
 * or inside a replicator or Bard set (each `job_title` role row's `department`
 * or `service_line`). Reading the blueprint rather than a list of handles keeps
 * such a field added later in scope with no change here. A field inside a grid,
 * a group or a set nested in another set is not read, so an entry linked only
 * that way reads as linked to nothing and stays outside every group.
 *
 * Super users, and users who may configure collections, are never scoped: they
 * are the administrators the decision leaves unchanged, and Statamic's own
 * entry policy already grants them everything.
 */
final class PeopleEditingScope
{
    /**
     * The collection whose editing is scoped.
     */
    public const string COLLECTION = 'people';

    /**
     * The collection an entry's organizations are read from.
     */
    private const string ORGANIZATIONS_COLLECTION = 'organizations';

    /**
     * The user group field holding the organizations the group may edit.
     */
    public const string GROUP_FIELD = 'organizations';

    /**
     * Whether a user's `people` access is limited to their groups' organizations.
     *
     * @param  Authenticatable|null  $user  The user to check
     */
    public function appliesTo(?Authenticatable $user): bool
    {
        // Equivalent: `User::fromUser(null)` returns null, which the guard below refuses.
        // @pest-mutate-ignore: InstanceOfToTrue, RemoveEarlyReturn
        if (! $user instanceof Authenticatable) {
            return false;
        }

        $user = User::fromUser($user);

        // phpstan requires this type guard; no caller reaches it with another type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $user instanceof UserContract) {
            return false;
        }

        if ($user->isSuper()) {
            return false;
        }

        return ! $user->hasPermission('configure collections');
    }

    /**
     * Whether an entry belongs to the collection this scope governs.
     *
     * @param  mixed  $entry  The entry a policy or listener was handed
     */
    public function governs(mixed $entry): bool
    {
        return $this->governedEntry($entry) instanceof Entry;
    }

    /**
     * The entry, typed as one, when it belongs to the collection this scope governs.
     *
     * For a caller that goes on to use the entry: a type assertion on governs() would
     * carry only the instanceof half of the check, so the analyzer would read every
     * false result for an entry as impossible.
     *
     * @param  mixed  $entry  The entry a policy or listener was handed
     * @return Entry|null The governed entry, or null when this scope does not govern it
     */
    public function governedEntry(mixed $entry): ?Entry
    {
        return $entry instanceof Entry && $entry->collectionHandle() === self::COLLECTION
            ? $entry
            : null;
    }

    /**
     * Whether a scoped user may act on a `people` entry.
     *
     * @param  Authenticatable  $user  The user acting
     * @param  Entry  $entry  The `people` entry acted on
     */
    public function allows(Authenticatable $user, Entry $entry): bool
    {
        return $this->linksToAny($entry, $this->userOrganizationIds($user));
    }

    /**
     * The ids of the `people` entries a scoped user may act on.
     *
     * @param  Authenticatable  $user  The user acting
     * @return list<string>
     */
    public function editableEntryIds(Authenticatable $user): array
    {
        $userOrganizations = $this->userOrganizationIds($user);

        // Equivalent: with no organizations every entry fails linksToAny, so the result is
        // the same empty list; the early return only skips the query.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($userOrganizations === []) {
            return [];
        }

        if (CollectionFacade::findByHandle(self::COLLECTION) === null) {
            return [];
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::COLLECTION);

        $ids = [];

        /** @var iterable<mixed> $entries */
        $entries = $query->get();

        foreach ($entries as $entry) {
            // phpstan requires this type guard; no caller reaches it with another type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $entry instanceof Entry) {
                continue;
            }

            $id = $entry->id();

            if (\is_string($id) && $this->linksToAny($entry, $userOrganizations)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * The ids of the `people` entries a scoped user may not act on.
     *
     * A relationship picker excludes these rather than limiting itself to the
     * editable ids, so a picker that also targets another collection keeps
     * every entry of that collection.
     *
     * @param  Authenticatable  $user  The user acting
     * @return list<string>
     */
    public function outOfScopeEntryIds(Authenticatable $user): array
    {
        $userOrganizations = $this->userOrganizationIds($user);

        if (CollectionFacade::findByHandle(self::COLLECTION) === null) {
            return [];
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::COLLECTION);

        $ids = [];

        /** @var iterable<mixed> $entries */
        $entries = $query->get();

        foreach ($entries as $entry) {
            // phpstan requires this type guard; no caller reaches it with another type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $entry instanceof Entry) {
                continue;
            }

            $id = $entry->id();

            if (\is_string($id) && ! $this->linksToAny($entry, $userOrganizations)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * The organization ids the user's groups cover: each organization a group
     * names, and every organization below it in the tree. A top-level
     * organization a group names covers nothing.
     *
     * @param  Authenticatable  $user  The user acting
     * @return list<string>
     */
    public function userOrganizationIds(Authenticatable $user): array
    {
        $named = $this->namedOrganizationIds($user);

        if ($named === []) {
            return [];
        }

        $covered = [];
        $inTree = [];
        $this->collectCovered($this->organizationTree(), $named, false, true, $covered, $inTree);

        // An organization outside the tree has no place in it, so it covers itself alone
        foreach ($named as $id) {
            if (! isset($inTree[$id])) {
                $covered[] = $id;
            }
        }

        // Equivalent: each tree node and each id outside the tree is collected once, and
        // namedOrganizationIds already removed duplicates, so `$covered` is a unique list.
        // @pest-mutate-ignore: UnwrapArrayUnique, UnwrapArrayValues
        return array_values(array_unique($covered));
    }

    /**
     * The ids of the organizations at the top of the `organizations` tree.
     *
     * @return list<string>
     */
    public function topLevelOrganizationIds(): array
    {
        $ids = [];

        foreach ($this->organizationTree() as $node) {
            $id = \is_array($node) ? ($node['entry'] ?? null) : null;

            if (\is_string($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * The organization ids named by the user's groups, before the tree is applied.
     *
     * @param  Authenticatable  $user  The user acting
     * @return list<string>
     */
    private function namedOrganizationIds(Authenticatable $user): array
    {
        $user = User::fromUser($user);

        // phpstan requires this type guard; no caller reaches it with another type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $user instanceof UserContract) {
            return [];
        }

        $ids = [];

        /** @var iterable<mixed> $groups */
        $groups = $user->groups();

        foreach ($groups as $group) {
            // phpstan requires this type guard; no caller reaches it with another type.
            // @pest-mutate-ignore: InstanceOfToTrue
            if (! $group instanceof UserGroup) {
                continue;
            }

            $ids = [...$ids, ...$this->normalizeIds($group->get(self::GROUP_FIELD))];
        }

        // Equivalent: userOrganizationIds removes duplicates and renumbers the result.
        // @pest-mutate-ignore: UnwrapArrayUnique, UnwrapArrayValues
        return array_values(array_unique($ids));
    }

    /**
     * Walk the tree, collecting each node a named organization covers.
     *
     * @param  array<mixed>  $nodes  The nodes at this level
     * @param  list<string>  $named  The organizations the user's groups name
     * @param  bool  $underNamed  Whether an ancestor of these nodes is covered
     * @param  bool  $topLevel  Whether these nodes are the top of the tree
     * @param  list<string>  $covered  Collected covered ids
     * @param  array<string, true>  $inTree  Collected ids of every node seen
     */
    private function collectCovered(array $nodes, array $named, bool $underNamed, bool $topLevel, array &$covered, array &$inTree): void
    {
        foreach ($nodes as $node) {
            if (! \is_array($node)) {
                continue;
            }

            $id = $node['entry'] ?? null;

            if (! \is_string($id)) {
                continue;
            }

            // Equivalent: the map is read with isset, which is true for false as well.
            // @pest-mutate-ignore: TrueToFalse
            $inTree[$id] = true;
            // Equivalent: the strict flag compares string ids with string ids.
            // @pest-mutate-ignore: TrueToFalse
            $isCovered = $underNamed || (! $topLevel && \in_array($id, $named, true));

            if ($isCovered) {
                $covered[] = $id;
            }

            $children = $node['children'] ?? null;

            if (\is_array($children)) {
                $this->collectCovered($children, $named, $isCovered, false, $covered, $inTree);
            }
        }
    }

    /**
     * The `organizations` tree on the default site, as stored.
     *
     * @return array<mixed>
     */
    private function organizationTree(): array
    {
        $structure = CollectionFacade::findByHandle(self::ORGANIZATIONS_COLLECTION)?->structure();

        // phpstan requires this type guard; no caller reaches it with another type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $structure instanceof CollectionStructure) {
            return [];
        }

        $site = Site::default();

        // phpstan requires this type guard; no caller reaches it with another type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $site instanceof SiteItem) {
            return [];
        }

        $tree = $structure->in($site->handle());

        // phpstan requires this type guard; no caller reaches it with another type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $tree instanceof Tree) {
            return [];
        }

        $nodes = $tree->tree();

        return \is_array($nodes) ? $nodes : [];
    }

    /**
     * Whether an entry links to any of the given organizations.
     *
     * @param  Entry  $entry  The `people` entry
     * @param  list<string>  $organizationIds  The organizations a user covers
     */
    private function linksToAny(Entry $entry, array $organizationIds): bool
    {
        // Equivalent: an intersection with an empty list is empty, so the result is the same.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($organizationIds === []) {
            return false;
        }

        return array_intersect($this->entryOrganizationIds($entry), $organizationIds) !== [];
    }

    /**
     * The organization ids an entry links to, across every organizations field.
     *
     * @param  Entry  $entry  The `people` entry
     * @return list<string>
     */
    public function entryOrganizationIds(Entry $entry): array
    {
        $blueprint = $entry->blueprint();

        // phpstan requires this type guard; no caller reaches it with another type.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $blueprint instanceof Blueprint) {
            return [];
        }

        $ids = [];

        foreach ($this->organizationPaths($blueprint) as $path) {
            $value = $entry->value($path['field']);

            if ($path['set_fields'] === null) {
                $ids = [...$ids, ...$this->normalizeIds($value)];

                continue;
            }

            if (! \is_array($value)) {
                continue;
            }

            foreach ($value as $row) {
                if (! \is_array($row)) {
                    continue;
                }

                // Check if the row is a Bard set node, which keeps its set handle and
                // fields under `attrs.values`
                if (($row['type'] ?? null) === 'set') {
                    // Read the set's values, if the node carries any
                    $attributes = $row['attrs'] ?? null;
                    $values = \is_array($attributes) ? ($attributes['values'] ?? null) : null;

                    // Read the set from its values; a set without them has no handle and
                    // is skipped below
                    if (\is_array($values)) {
                        $row = $values;
                    }
                }

                $type = $row['type'] ?? null;

                if (! \is_string($type)) {
                    continue;
                }

                foreach ($path['set_fields'][$type] ?? [] as $handle) {
                    $ids = [...$ids, ...$this->normalizeIds($row[$handle] ?? null)];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Where a blueprint keeps organization links: each top-level organizations
     * field (no set fields), and each replicator or Bard field with the handles
     * of the organizations fields in each of its set types.
     *
     * @return list<array{field: string, set_fields: array<string, list<string>>|null}>
     */
    private function organizationPaths(Blueprint $blueprint): array
    {
        /** @var list<array{field: string, set_fields: array<string, list<string>>|null}> $paths */
        $paths = Blink::once(sprintf('people-editing-scope-paths:%s', $blueprint->fullyQualifiedHandle()), function () use ($blueprint): array {
            $paths = [];

            /** @var Field $field */
            foreach ($blueprint->fields()->all() as $field) {
                if ($this->isOrganizationsField($field)) {
                    $paths[] = ['field' => $field->handle(), 'set_fields' => null];

                    continue;
                }

                $setFields = $this->setOrganizationFields($field);

                if ($setFields !== []) {
                    $paths[] = ['field' => $field->handle(), 'set_fields' => $setFields];
                }
            }

            return $paths;
        });

        return $paths;
    }

    /**
     * The organizations fields in each set type of a replicator or Bard field,
     * keyed by set handle. Sets may be grouped (`sets: {group: {sets: ...}}`) or flat.
     *
     * @return array<string, list<string>>
     */
    private function setOrganizationFields(Field $field): array
    {
        // Equivalent: the strict flag compares a fieldtype handle string with strings, and
        // no other fieldtype carries a `sets` array, so the early return only skips the read.
        // @pest-mutate-ignore: TrueToFalse, RemoveEarlyReturn
        if (! \in_array($field->type(), ['replicator', 'bard'], true)) {
            return [];
        }

        $sets = $field->get('sets');

        if (! \is_array($sets)) {
            return [];
        }

        $found = [];

        foreach ($this->flattenSets($sets) as $setHandle => $setFields) {
            $handles = [];

            /** @var Field $setField */
            foreach (new Fields($setFields)->all() as $setField) {
                $handle = $setField->handle();

                if (\is_string($handle) && $this->isOrganizationsField($setField)) {
                    $handles[] = $handle;
                }
            }

            if ($handles !== []) {
                $found[$setHandle] = $handles;
            }
        }

        return $found;
    }

    /**
     * Each set's field definitions keyed by set handle, through any set groups.
     *
     * @param  array<mixed>  $sets
     * @return array<string, array<mixed>>
     */
    private function flattenSets(array $sets): array
    {
        $flat = [];

        foreach ($sets as $handle => $set) {
            if (! \is_array($set)) {
                continue;
            }

            if (\is_array($set['sets'] ?? null)) {
                $flat = [...$flat, ...$this->flattenSets($set['sets'])];

                continue;
            }

            // Equivalent: Statamic keys sets by their string handle and gives every set a
            // `fields` list, so both conditions hold for every set a blueprint can hold.
            // @pest-mutate-ignore: BooleanAndToBooleanOr
            if (\is_string($handle) && \is_array($set['fields'] ?? null)) {
                $flat[$handle] = $set['fields'];
            }
        }

        return $flat;
    }

    /**
     * Whether a field links to `organizations` entries.
     */
    private function isOrganizationsField(Field $field): bool
    {
        // Equivalent: only an `entries` field carries a `collections` list.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($field->type() !== 'entries') {
            return false;
        }

        $collections = $field->get('collections');

        // Equivalent: the strict flag compares a collection handle string with strings.
        // @pest-mutate-ignore: TrueToFalse
        return \is_array($collections) && \in_array(self::ORGANIZATIONS_COLLECTION, $collections, true);
    }

    /**
     * A stored link value (one id, or a list of ids) as a list of non-empty ids.
     *
     * @return list<string>
     */
    private function normalizeIds(mixed $value): array
    {
        // Equivalent: a null becomes `[null]`, which the filter below empties.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($value === null) {
            return [];
        }

        $ids = \is_array($value) ? $value : [$value];

        // Equivalent: every caller spreads the result, which renumbers its keys.
        // @pest-mutate-ignore: UnwrapArrayValues
        return array_values(array_filter(
            $ids,
            fn (mixed $id): bool => \is_string($id) && $id !== ''
        ));
    }
}
