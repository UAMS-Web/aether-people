Written against v0.5.4.

One entry per symptom, as you meet it: the message or behavior, its cause, and the fix.

- [The provider layer needs a host binding](#the-provider-layer-needs-a-host-binding)
- [A merge stops with "no redirect writer is bound"](#a-merge-stops-with-no-redirect-writer-is-bound)
- [A merge request fails with a binding resolution error](#a-merge-request-fails-with-a-binding-resolution-error)
- [A lens URL answers 404](#a-lens-url-answers-404)
- [people_lens_url prints nothing](#people_lens_url-prints-nothing)
- [A person cannot be saved or edited](#a-person-cannot-be-saved-or-edited)
- [A field comes back to its old value after saving](#a-field-comes-back-to-its-old-value-after-saving)
- [A provider is missing from the Find a Doc API](#a-provider-is-missing-from-the-find-a-doc-api)
- [Fixed](#fixed)

## The provider layer needs a host binding

Reading the `regions` computed field, or rendering research publications, fails with:

```text
The clinical provider layer needs a host binding for <contract>, and none is bound.
Bind it in the host, or set PEOPLE_PROVIDER_LAYER=false to turn the layer off.
```

**Cause:** the host has not bound `LocationRegions` or `PublicationCitations`, and the add-on's default for each refuses to guess.

**Fix:** bind the named contract in the host. See [Host bindings](Host-bindings.md). To run without the clinical provider layer, set `PEOPLE_PROVIDER_LAYER=false`.

## A merge stops with "no redirect writer is bound"

The merge screen loads, and Preview or Merge fails with a message that begins:

```text
People merge cannot run: no redirect writer is bound.
```

**Cause:** the host has not bound `UAMSWeb\Aether\People\Contracts\MergeRedirectWriter`. The preview and the commit both refuse, answering 422, before anything is written.

**Fix:** bind a writer. A writer that writes nothing is a valid binding, and the add-on's `RedirectsGlobalWriter` writes to the `redirects` global set.

## A merge request fails with a binding resolution error

The merge screen or its preview and commit requests fail with an error from the container.

**Cause:** the host has not bound `EntryReferenceRepointer`. The merge service needs it, and the add-on never binds it.

**Fix:** bind `UAMSWeb\Aether\People\Contracts\EntryReferenceRepointer` in the host. See [Host bindings](Host-bindings.md).

## A lens URL answers 404

A URL such as `/provider/<slug>` or `/researcher/<slug>` answers 404 although the person exists.

**Cause:** the request is handed to Statamic's normal routing whenever any check fails, and a 404 follows when no page exists there. The checks are: the lens exists and is active on this site; the person is a published entry on the canonical site; `status_and_visibility` is not `private`; the person is not password protected; and the lens gate passes.

**Fix:** check each in turn. For `provider`, confirm the person meets the [eligibility rules](Find-a-Doc-API.md#eligibility-rules) and that `PEOPLE_PROVIDER_LAYER` is not `false`. For `researcher`, confirm a [signal field](Configuration.md#researcher_profile) has a value. For `academic` and `institutional`, confirm the host binds `DepartmentHubRoster`, the site is a department hub, and the person is on its roster.

## people_lens_url prints nothing

`{{ people_lens_url lens="provider" }}` renders an empty string.

**Cause:** the lens or slug is blank, the lens handle is unknown, or the lens is not active on the current site. The `provider` lens is active on the `health` site only by default.

**Fix:** pass `slug` explicitly where the context has none, check the handle against [Lenses](Lenses.md), and check the lens's `sites`.

## A person cannot be saved or edited

A save fails with:

```text
This person must stay linked to an organization you edit for. Add one of your organizations to a role or service line before saving.
```

**Cause:** the editor's user group is limited by the [people editing scope](Control-Panel-tools.md#people-editing-scope), and the saved person would link to none of the covered organizations.

**Fix:** add a covered organization to a role or service line on the person, or ask an administrator to change the person or the group's `organizations`.

The same scope produces "You cannot add or remove ...: they are not linked to an organization you edit for" when the editor saves a clinical collection entry that adds or removes an out-of-scope person, and a person missing from the listing altogether.

## A field comes back to its old value after saving

A change to the name, a worker type, a Workday id or `workday_gender` does not stick.

**Cause:** these fields are managed by Workday. For a user limited by the editing scope they are read-only, and a save restores the stored value. `trainee_type` is locked only when the entry's `workday_supplied` list names it.

**Fix:** change the value in Workday. The nickname, pronunciation and display format fields, and `gender`, stay editable. See [Workday-managed fields](Control-Panel-tools.md#workday-managed-fields).

## A provider is missing from the Find a Doc API

`GET /api/v1/find-a-doc/providers/<id>` answers 404, or the person is absent from the list.

**Cause:** a provider is exposed only when published, eligible and searchable. `searchable` is false for a person who is currently separated or marked `hidden_from_provider_list_query`. The list is also cached for `find_a_doc_api.index_cache_ttl` seconds, and a location that becomes dated later changes eligibility on the next scheduled refresh.

**Fix:** check each condition under [Find a Doc API](Find-a-Doc-API.md), then wait out the cache or set `FIND_A_DOC_API_INDEX_CACHE_TTL=0`.

## Fixed

Problems fixed in a release stay here, with the release that fixed them, instead of being deleted. None is recorded yet.
