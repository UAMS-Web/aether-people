Written against v0.5.4.

Two or more `people` entries describe the same person, and one should remain with the others redirected to it.

It needs the `merge people entries` permission, and the host must have bound `EntryReferenceRepointer` and `MergeRedirectWriter` (see [Host bindings](Host-bindings.md)).

## Steps

1. Find the duplicates. Either open the "Potential Duplicate People" widget on the dashboard and use the merge link on a cluster, or select two or more entries in the People listing and run the "Merge duplicates…" bulk action.
2. On the Merge People screen, choose the survivor. The survivor keeps its id, slug and public URL, and the other entries are retired and redirected to it.
3. Under Fields, review each field where the entries disagree. The survivor's value is pre-selected when it has one, and otherwise the first entry with a value. Pick another source per field where needed. Choosing an empty value clears the field on the survivor.
4. Under Sites, review the redirects: each retired URL will redirect to the survivor on that site, or to its canonical URL.
5. Under Review and merge, select Preview. The preview applies the same checks as the merge and writes nothing. It shows the survivor, the number of fields taken from other entries, the references re-pointed, the items updated, the redirects and the entries retired. Changing a selection clears the preview.
6. Select Merge and confirm. The survivor is saved, references are rewritten, redirects are written and the retired entries are deleted. The screen reports "People merged."

A merge cannot be undone.

## If the merge refuses

The messages are listed under [Merge duplicate people](Control-Panel-tools.md#messages). A user limited by the [people editing scope](Control-Panel-tools.md#people-editing-scope) can merge only people in their organizations, and the merged person must stay linked to one of them.

## Reference

- [Control Panel tools](Control-Panel-tools.md): the permission, routes, what a merge changes and how duplicates are detected.
- [Host bindings](Host-bindings.md): the two contracts a merge needs.
