<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use UAMSWeb\Aether\People\Contracts\OrganizationDisplayName;

/**
 * The linked organizational unit a `role_title` scope names (UAMS-Web/uams-statamic-archive#2146).
 *
 * A unit-naming scope used to be typed text, so a row could link one unit and
 * name another, and a renamed unit left every title naming it stale. The scope
 * now links the unit and reads its display name at compose time. This class is
 * the one place that turns the stored link into a name, and that builds the
 * Statamic `entries` field the CP picker and the existence rule share.
 *
 * The link lives at `scope.scope_unit`; its field config is the `field` key of
 * that nested row in `config/role_title.php`.
 */
final class RoleTitleScopeUnit
{
    /**
     * Storage key of the group the link lives under.
     */
    public const string GROUP_KEY = 'scope';

    /**
     * Storage key of the link inside the group.
     */
    public const string NESTED_KEY = 'scope_unit';

    /**
     * The one linked entry id in a stored or posted value, or null.
     *
     * Accepts the stored string and the single-item list the CP picker posts,
     * so every reader agrees on what counts as "linked".
     *
     * @param  mixed  $raw  Stored id, posted id list, or anything else
     * @return string|null Trimmed entry id, or null when nothing is linked
     */
    public static function normalizeId(
        mixed $raw
    ): ?string {
        // Unwrap the single-item list the relationship picker posts
        if (\is_array($raw)) {
            $raw = $raw === [] ? null : \reset($raw);
        }

        // Only a non-blank string is an id
        if (! \is_string($raw)) {
            return null;
        }

        $id = \trim($raw);

        return $id === '' ? null : $id;
    }

    /**
     * The linked id read from a `scope` group payload, or null.
     *
     * @param  mixed  $scope  `scope` sub-payload or non-array
     * @return string|null Linked entry id, or null when none
     */
    public static function idFromScope(
        mixed $scope
    ): ?string {
        // Only an array payload can carry a link. Removing this return cannot be
        // killed, because `?? null` below already reads a non-array as unlinked;
        // it is kept so that does not rest on `??` swallowing a string offset.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! \is_array($scope)) {
            return null;
        }

        return self::normalizeId($scope[self::NESTED_KEY] ?? null);
    }

    /**
     * The linked entry, when the id resolves to an entry in an allowed collection.
     *
     * @param  string|null  $id  Linked entry id
     * @return Entry|null The entry, or null when absent or outside the allowed collections
     */
    public static function entry(
        ?string $id
    ): ?Entry {
        // Nothing linked resolves to nothing
        if ($id === null) {
            return null;
        }

        $entry = EntryFacade::find($id);

        // A missing entry, or one outside the configured collections, is not a unit
        if (! $entry instanceof Entry || ! \in_array(
            $entry->collectionHandle(),
            self::collections(),
            // The strict flag cannot be killed: both sides are collection
            // handles, non-numeric strings on which loose and strict agree.
            // @pest-mutate-ignore: TrueToFalse
            true
        )) {
            return null;
        }

        return $entry;
    }

    /**
     * The name a linked unit composes into a title, or null.
     *
     * The unit's `formal_name` when it has one (UAMS-Web/uams-statamic-archive#2546), since a title reads in
     * running text (`professor of the UAMS College of Medicine`) where the public
     * label may be shorter; otherwise its public name (see
     * `OrganizationDisplayName::displayName()`, which the host binds). Null when nothing is
     * linked, when the link does not resolve, or when the unit has neither.
     *
     * @param  string|null  $id  Linked entry id
     * @return string|null The unit's formal or public name, or null
     */
    public static function displayName(
        ?string $id
    ): ?string {
        $entry = self::entry($id);

        if (! $entry instanceof Entry) {
            return null;
        }

        $formalName = self::formalName($entry);

        if ($formalName !== null) {
            return $formalName;
        }

        // The public name is the host's to resolve, so a host that binds none has only the formal name
        return app()->bound(OrganizationDisplayName::class)
            ? app(OrganizationDisplayName::class)->displayName($entry)
            : null;
    }

    /**
     * The unit's authored `formal_name`, trimmed, or null when it is empty.
     *
     * @param  Entry  $entry  Organizational unit entry
     * @return string|null Formal name, or null
     */
    public static function formalName(
        Entry $entry
    ): ?string {
        $value = $entry->value('formal_name');

        if (! \is_string($value) || \trim($value) === '') {
            return null;
        }

        return \trim($value);
    }

    /**
     * Collections the link may point into, from the nested row's `field` config.
     *
     * @return list<string> Collection handles
     */
    public static function collections(): array
    {
        $collections = self::fieldConfig()['collections'] ?? [];

        return \is_array($collections)
            ? \array_values(\array_filter($collections, \is_string(...)))
            : [];
    }

    /**
     * The Statamic `entries` field config for the link.
     *
     * @return array<string, mixed> Field config including `type`
     */
    public static function fieldConfig(): array
    {
        $group = RoleTitleValue::topLevelSubfieldRow(self::GROUP_KEY);
        $fields = \is_array($group) ? ($group['fields'] ?? null) : null;
        $row = \is_array($fields) ? ($fields[self::NESTED_KEY] ?? null) : null;
        $field = \is_array($row) ? ($row['field'] ?? null) : null;

        return ['type' => 'entries'] + (\is_array($field) ? $field : []);
    }

    /**
     * A Statamic field for the link, holding the given id as its value.
     *
     * @param  string|null  $id  Linked entry id
     * @return Field The configured `entries` field
     */
    public static function field(
        ?string $id
    ): Field {
        $field = new Field(self::NESTED_KEY, self::fieldConfig());
        $field->setValue($id === null ? [] : [$id]);

        return $field;
    }

    /**
     * CP meta for the relationship picker, including the selected item's data.
     *
     * @param  string|null  $id  Linked entry id
     * @return array<array-key, mixed> Relationship fieldtype preload meta, as Statamic returns it
     */
    public static function preloadMeta(
        ?string $id
    ): array {
        // The relationship preload checks the current user's collection
        // permissions and cannot run without one. The CP always has a user;
        // anything else preloading the fieldtype gets no picker meta.
        if (! User::current() instanceof UserContract) {
            return [];
        }

        $fieldtype = self::field($id)->fieldtype();

        // An `entries` field always resolves to a fieldtype, so InstanceOfToTrue
        // cannot be killed; the guard is kept for the static type of `preload()`.
        // @pest-mutate-ignore: InstanceOfToTrue
        if (! $fieldtype instanceof Fieldtype) {
            return [];
        }

        $meta = $fieldtype->preload();

        return \is_array($meta) ? $meta : [];
    }
}
