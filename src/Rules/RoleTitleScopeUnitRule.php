<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Support\RoleTitleScopeUnit;
use UAMSWeb\Aether\People\Support\RoleTitleValue;

/**
 * Validates a `role_title` scope's linked organizational unit (UAMS-Web/uams-statamic-archive#2146).
 *
 * Three things must hold for a linked scope to compose the title it claims:
 *
 * - the link resolves to an entry in an allowed collection that has a public
 *   name, or the composed scope would silently come out empty;
 * - the title selects no object, since an object names a unit generically and
 *   a linked scope names one specifically, so the composer would drop the
 *   object the author just chose (UAMS-Web/uams-statamic-archive#2546; before it, the Control Panel hid the
 *   article-bearing joiners instead);
 * - when the row also links its unit through a sibling field (the replicator
 *   set's `department` or `service_line`, named by the blueprint's
 *   `scope_unit_row_link`), the two links are the same entry. A row cannot
 *   link one unit and name a different one in its title.
 */
final class RoleTitleScopeUnitRule implements DataAwareRule, ValidationRule
{
    /**
     * Full request data bag, for the sibling object and the row link.
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Create a rule bound to the parent `role_title` path and the row link.
     *
     * @param  string  $parentFieldPath  Dotted path to the role_title value (e.g.
     *                                   `title` or `job_title.0.title`)
     * @param  string|null  $rowLinkHandle  Sibling field in the same row that links
     *                                      the row's unit, or null when there is none
     */
    public function __construct(
        private readonly string $parentFieldPath,
        private readonly ?string $rowLinkHandle
    ) {}

    /**
     * Receive the full validation data bag for `DataAwareRule`.
     *
     * @param  array<string, mixed>  $data  Request data including the parent field
     */
    public function setData(
        array $data
    ): static {
        // Store the bag so `validate()` can read the object and the row link
        $this->data = $data;

        return $this;
    }

    /**
     * Validate the linked unit against its target, the object, and the row link.
     *
     * @param  string  $attribute  Dotted validation attribute path
     * @param  mixed  $value  Posted link (an id or a single-item id list)
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        $id = RoleTitleScopeUnit::normalizeId($value);

        // Nothing linked: the typed-name path applies and nothing here is checked
        if ($id === null) {
            return;
        }

        // The link must resolve to a named unit, or the scope composes empty
        if (! RoleTitleScopeUnit::entry($id) instanceof Entry) {
            $fail('aether-people::fieldtypes.role_title.validation.scope_unit_not_found')->translate();

            return;
        }

        if (RoleTitleScopeUnit::displayName($id) === null) {
            $fail('aether-people::fieldtypes.role_title.validation.scope_unit_unnamed')->translate();

            return;
        }

        // An object and a linked unit both name a unit, so the object would be dropped
        if ($this->hasObjectSelected()) {
            $fail('aether-people::fieldtypes.role_title.validation.scope_unit_object')->translate();

            return;
        }

        // When the row links its own unit, the scope must link the same one
        $rowLinked = $this->rowLinkedId();

        if ($rowLinked !== null && $rowLinked !== $id) {
            $fail('aether-people::fieldtypes.role_title.validation.scope_unit_row_mismatch')->translate();
        }
    }

    /**
     * Whether the posted title selects an object that would compose, read the way
     * the composer reads it ({@see RoleTitleValue::objectSelected()}).
     *
     * @return bool True when an object composes in the posted configuration
     */
    private function hasObjectSelected(): bool
    {
        return RoleTitleValue::objectSelected(Arr::get($this->data, $this->parentFieldPath));
    }

    /**
     * The id the row links through its own unit field, or null.
     *
     * The row is the parent of the role_title value, so the sibling sits beside
     * the value's last path segment (`job_title.0.title` -> `job_title.0.department`).
     *
     * @return string|null Row-linked entry id, or null when there is none
     */
    private function rowLinkedId(): ?string
    {
        // Without this guard the read below resolves `job_title.0.` and finds
        // nothing, so no mutant of it can be killed; it is kept so the
        // no-row-link case does not rest on how `Arr::get()` treats a trailing dot.
        // @pest-mutate-ignore: BooleanOrToBooleanAnd, EmptyStringToNotEmpty, RemoveEarlyReturn
        if ($this->rowLinkHandle === null || $this->rowLinkHandle === '') {
            return null;
        }

        $lastDot = \strrpos($this->parentFieldPath, '.');
        $rowPath = $lastDot === false ? '' : \substr($this->parentFieldPath, 0, $lastDot).'.';

        return RoleTitleScopeUnit::normalizeId(
            Arr::get($this->data, $rowPath.$this->rowLinkHandle)
        );
    }
}
