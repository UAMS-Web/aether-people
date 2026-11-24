Written against v0.5.4.

A group of editors should change only the people linked to their own college, department or service line.

It needs a user group in the host's Control Panel whose blueprint carries the `organizations` field the add-on reads (the field is defined by the host's user group blueprint), and permission to edit user groups.

## Steps

1. Open the user group and set its `organizations` field to the organizations the members should cover. Choose a college, institute, service line or department. A top-level organization (a brand) is refused: it would cover nearly everyone.
2. Give the group the ordinary Statamic permissions for the `people` collection, such as `view people entries` and `edit people entries`. The scope narrows what those permissions reach and adds none.
3. Confirm with a member's account. The People listing shows only people linked to a covered organization, with search narrowed the same way, and opening, editing, deleting or publishing an out-of-scope person is refused.
4. Save a person after removing every covered organization from their roles and service lines. The save is refused with "This person must stay linked to an organization you edit for."

Covered organizations are the ones named plus every organization below them in the `organizations` tree. A person counts as linked through any `entries` field pointing at the `organizations` collection, at the top level or inside a replicator or Bard set.

Super users and users with the `configure collections` permission are never limited.

## Reference

- [Control Panel tools](Control-Panel-tools.md#people-editing-scope): every rule of the scope, including the people fields on the clinical collections and the merge screen.
