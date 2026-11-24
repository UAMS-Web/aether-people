Written against v0.5.4.

The add-on boots without a host binding, and refuses a missing one by name the first time it is needed. This page lists what the host application supplies. The decision behind it is recorded in the [decision log](https://github.com/UAMS-Web/aether-people/blob/main/docs/DECISIONS.md).

## Contracts

All contracts are in `UAMSWeb\Aether\People\Contracts`.

| Contract | Method | The host supplies | Without a binding |
| --- | --- | --- | --- |
| `LocationRegions` | `resolveRegions(Entry $location)` | The stored region terms of a clinical location. | The default binding throws `MissingHostBindingException` when the `regions` computed field is read. |
| `PublicationCitations` | `enrichResearchPublicationSelectSets(array)` | Citation HTML for the selected publications, from a cache only, with no HTTP request. | The default binding throws `MissingHostBindingException`. |
| `EntryReferenceRepointer` | `repoint(array $idMap)`, `preview(array $idMap)` | Rewrites references to retired people entries in the host's blueprints, globals and terms, and returns a `ReferenceRepointSummary`. | A merge request fails with a container `BindingResolutionException`. The merge bulk action is still listed. |
| `MergeRedirectWriter` | `count(array $plan)`, `write(array $plan)` | Writes a redirect from each retired URL to the survivor. A writer that writes nothing is a valid binding. | A merge stops before writing anything: "People merge cannot run: no redirect writer is bound." |
| `OrganizationDisplayName` | `displayName(Entry)` | An organization's public name when it has no `formal_name`. Optional. | A linked scope unit without a `formal_name` has no name, and the role title rule refuses it. |
| `DepartmentHubRoster` | `organization(string $siteHandle)`, `resolvePeople(Entry)` | The organization a hub site belongs to, and that organization's roster. | The academic and institutional lenses cannot resolve their gate. |

`LocationRegions` and `PublicationCitations` are bound with `bindIf`, so the host's own binding replaces the add-on's refusing default. The add-on binds `EntryPolicy` to its `ScopedEntryPolicy`.

`MissingHostBindingException` reads: "The clinical provider layer needs a host binding for `<contract>`, and none is bound. Bind it in the host, or set PEOPLE_PROVIDER_LAYER=false to turn the layer off."

The add-on includes `RedirectsGlobalWriter`, an implementation of `MergeRedirectWriter` that appends rows to the `redirects` global set, one localization per site. It is not bound by default; a host binds it, or its own writer, in a service provider.

## Validation rules

`name` and `international_phonetic_alphabet` are Laravel validation rules that `person_name` and `text_phonetic_pair` apply. The host defines both.

## Blueprints and fieldsets

The `people` blueprint imports the host fieldset `uamsweb_entry_sitemap`, and many of its fields come from host `uamsweb_common` fieldsets. The ISCO-08 lists come from `uams-web/aether-enums`.

Blueprints for `people` and `people_groups` are served from the add-on's `resources/collection-blueprints/`. A blueprint saved in the Control Panel is written to the host's `resources/blueprints/vendor/collections/<collection>/` and wins. Listing the add-on's blueprints needs the `statamic-blueprint-namespace-slash.patch` `statamic/cms` patch from the `uams-web/patches-statamic-cms` package: on Statamic 6.40.0 unpatched, a collection that lists blueprints from an added namespace fails with "Undefined array key 0".

## Views

A copy of a view in the host's `resources/views` wins over the add-on's. This covers the lens templates, the page-builder people and Find a Doc blocks, the form field views and the search card.
