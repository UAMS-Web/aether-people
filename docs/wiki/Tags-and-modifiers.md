Written against v0.5.4.

The Antlers tag, modifiers and query scope the add-on registers. Statamic discovers each from `src/Tags/`, `src/Modifiers/` and `src/Scopes/`.

## people_lens_url

Builds the URL of a [lens](Lenses.md) for the current site.

| Parameter | Effect |
| --- | --- |
| `lens` | The lens handle, such as `provider`. |
| `slug` | The person's slug. When omitted, the tag reads `slug` from the current context. |

```antlers
{{ people_lens_url lens="provider" slug="leonard-mccoy" }}
```

On a site where the `provider` lens is active under the prefix `/health`, this prints `/health/provider/leonard-mccoy`. The slug is URL-encoded.

The tag prints nothing when the lens or slug is blank, the lens does not exist, or the lens is not active on the current site. It does not check the lens gate, so a link can point at a URL that answers 404 for a person who does not qualify.

## clinical_provider_eligible

A modifier that filters a list of people entries, or entry ids, down to those that are clinical-provider eligible and searchable.

```antlers
{{ physician_locations | clinical_provider_eligible }}
```

It takes no parameters. Values that do not resolve to an entry are dropped.

## clinical_titles_carried

A modifier that turns a list of provider entries into the distinct ids of their `clinical_specializations` titles, in first-seen order. It takes no parameters, and a null input gives an empty list. The Find a Doc providers block uses it to filter by title:

```antlers
:fad_filter_title_ids="items | clinical_titles_carried"
```

## clinical_provider_eligible_people

A query scope for an entries field. It limits the choices to people who are clinical-provider eligible. It ignores any scope value, and it does not require `searchable`. It is meant for the `query_scopes` setting of a field that selects people, such as the providers field of the host's Find a Doc fieldset.

When the eligible set is empty, the field offers no people.
