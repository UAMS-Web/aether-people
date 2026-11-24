Written against v0.5.4.

What the add-on adds to the Control Panel: the merge screen, two dashboard widgets, the people editing scope and the locks on Workday-managed fields.

## Permissions

| Permission | Group | Allows |
| --- | --- | --- |
| `merge people entries` | People | Merging duplicate people, and seeing the duplicate people widget. |

The add-on uses these existing Statamic permissions as well: `edit people entries` (the gender overrides widget and the gender actions) and `configure collections` (exempts a user from the editing scope and the Workday locks).

## Merge duplicate people

An action on the people listing, titled "Merge duplicates…", opens the merge screen for the selected entries. The bulk form needs two or more people entries selected, and the action needs `merge people entries`. The action is also offered on a single entry, where the screen has one candidate and a merge refuses to run.

| Method | Path under the Control Panel | Route name | Purpose |
| --- | --- | --- | --- |
| GET | `people/merge?ids=a,b,...` | `people-merge.show` | The reconciliation screen. |
| POST | `people/merge/preview` | `people-merge.preview` | A dry run that writes nothing. |
| POST | `people/merge/commit` | `people-merge.commit` | The merge. |

The add-on registers no navigation item for the screen. The steps are in [Merging duplicate people](Merging-duplicate-people.md).

### What a merge does

- Only entries in the people collection merge, and the survivor must be one of the selected entries.
- For each field, the survivor's value is kept if it has one, else the first candidate with a value. The editor can choose another source per field. Selecting an empty value clears the field.
- `id`, `slug`, `title`, `blueprint`, `date`, `published`, `updated_at`, `updated_by` and computed fields are never reconciled.
- The survivor is saved first. Then references to the retired entries are rewritten, redirects are written, and the retired entries are deleted without firing delete events.
- Each retired entry's URL gets a `301` redirect to the survivor's URL on the same site, or to the survivor's canonical URL when that site has none.

References and redirects are written through [host bindings](Host-bindings.md): the add-on never binds `EntryReferenceRepointer` or `MergeRedirectWriter` itself.

### Messages

| Message | Cause |
| --- | --- |
| Select at least two people entries to merge. | Fewer than two entries resolved. |
| Entry [id] is not a people entry and cannot be merged. | A selected entry is in another collection. |
| The survivor must be one of the selected people entries. | The survivor id is not among the candidates. |
| Unknown source entry [id] for field [handle]. | A field selection names an entry that is not a candidate. |
| You cannot merge ...: they are not linked to an organization you edit for. | A scoped editor selected people outside their organizations. |
| The merged ... must stay linked to an organization you edit for. Keep one of your organizations on a role or service line. | The merged result would leave a scoped editor's organizations. |
| People merge cannot run: no redirect writer is bound. | The host has not bound `MergeRedirectWriter`. Nothing is written. |

On the screen, "Merge these entries? The retired entries will be deleted. This cannot be undone." is the confirmation, and "People merged." the success message.

## Dashboard widgets

| Widget | Needs | Config | Shows |
| --- | --- | --- | --- |
| `DuplicatePeople`, titled "Potential Duplicate People" | `merge people entries` | `title`, `cache_ttl` (default 300 seconds), `limit` (default 25) | Clusters of people who may be duplicates, each with a confidence and a link into the merge screen. |
| `WorkdayGenderOverrides`, titled "Gender Overrides to Review" | `edit people entries` | `title`, `limit` (default 25) | People whose gender was changed by an editor after Workday supplied another value. |

A scoped editor sees only clusters whose members are all in their editable set, and only their own people in the gender list.

### How duplicates are detected

Detection only suggests; it never changes an entry. Two people join a cluster when they share any of these:

- an identifier: NPI, Epic SER id, Workday universal id or Workday employee id;
- an email address (trimmed, lowercased);
- a normalized first and last name;
- a phone number of at least seven digits, from `telephone` or `telephone_mobile`;
- a slug stem, the slug without a trailing `-N`.

A cluster needs two or more people and at least one signal shared by two or more of them. Confidence is:

- **high**: a shared identifier, or a shared email, or a shared name and phone;
- **medium**: a shared name and slug stem, or a shared phone;
- **low**: a shared name alone, or a shared slug stem alone.

Clusters sort by confidence, then by size.

## People editing scope

A user group can limit its members to the people linked to certain organizations.

- A user group's `organizations` field names the organizations its members may edit. The group covers each named organization and every organization below it in the `organizations` collection tree, never above. A top-level organization (a brand) covers nothing, and the validation rule `not_top_level_organization` refuses to select one: ":title is at the top of the organizations tree, so it would cover nearly everyone. Choose the college, institute, service line or department instead."
- A person is in scope when any organization they link to is covered. The add-on reads organization links from top-level `entries` fields and from fields inside replicator and Bard sets, not from grid, group or nested sets.
- Super users and users with `configure collections` are never scoped, and neither are imports and console runs, which have no signed-in user.
- For a scoped user the people listing and its search show only in-scope people. View, edit, delete and publish are refused outside the scope.
- Saving is refused unless the person stays linked to an organization the editor covers: "This person must stay linked to an organization you edit for. Add one of your organizations to a role or service line before saving."
- When a scoped editor saves an entry in `clinical_locations`, `clinical_expertise`, `clinical_conditions`, `clinical_services`, `clinical_resources` or `recognition_lists`, they cannot add or remove a person outside their scope in the entry's people field (`physician_locations`, `clinical_providers` or `members`): "You cannot add or remove ...: they are not linked to an organization you edit for. Ask an administrator to change them." People already listed and unchanged do not block the save.

## Workday-managed fields

For users the editing scope applies to, these fields are read-only, and any attempt to change them is undone on save:

- `person_name`, `uams_worker_query`, `uams_worker_subtype`, `uams_employee_subtype`, `uams_workday_id` and `workday_gender`, always;
- `trainee_type`, only when the entry's `workday_supplied` list names it.

The nickname, pronunciation and display format fields, and `gender`, stay editable. Locked fields are restored to their stored values in entries, working copies and restored revisions, so a locked field cannot be given a first value either.

### Gender overrides

`gender` is the editable value and `workday_gender` is Workday's last value. When a Workday sync calls `WorkdayGender::apply`:

- an empty Workday value changes nothing;
- `gender` follows Workday when it is empty or equals the previous Workday value;
- when the new Workday value differs from the previous `workday_gender` (a first sync counts, with no previous value) and `gender` does not already match it, the person is flagged for review.

While `gender` differs from `workday_gender`, the instructions of the `workday_gender` field begin with "Overridden: Gender above differs from the value in Workday." The widget lists flagged people with two actions, "Keep override" (the `confirm` route) and "Use Workday value" (the `take` route, which sets `gender` to Workday's value). Neither sends mail. See [Reviewing gender overrides](Reviewing-gender-overrides.md).

| Method | Path under the Control Panel | Route name |
| --- | --- | --- |
| POST | `people/workday-gender/{id}/confirm` | `people-workday-gender.confirm` |
| POST | `people/workday-gender/{id}/take` | `people-workday-gender.take` |

## Update script

`CarryTraineeTypeOver` runs with `php please updates:run` (which `statamic:up` calls). It copies the old `resident_query` and `fellow_query` answers on every people entry into `trainee_type`, saving without firing events. It is safe to run again.
