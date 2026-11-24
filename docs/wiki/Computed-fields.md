Written against v0.5.4.

With the provider layer on, the add-on registers computed fields on the `people` collection and on the `clinical_providers` collection. A computed field is derived when the entry is read or indexed and is not stored. The field declarations a blueprint imports are in the `uamsweb_computed_person` fieldset.

With `PEOPLE_PROVIDER_LAYER=false` none of these is registered. See [Provider layer switch](Configuration.md#provider-layer-switch).

## Names

| Handle | Value |
| --- | --- |
| `display_name_full`, `display_name_full_with_prefix`, `display_name_full_with_credentials`, `display_name_full_with_clinical_credentials`, `display_name_short_with_prefix` | The person's name in the display format they chose, with the prefix or credentials the handle names. |
| `full_name`, `full_name_with_credentials`, `full_name_with_prefix` | The legal-order full name with the same variations. |
| `nickname_list`, `alternate_name_list` | The nicknames and alternate names. |
| `legal_name_pairs` | The legal name as text and phonetic pairs. |
| `prefix` | The name prefix. |

## Clinical provider

| Handle | Value |
| --- | --- |
| `clinical_provider_profile_eligible_query` | Whether the person meets the [eligibility rules](Find-a-Doc-API.md#eligibility-rules). People collection only. |
| `searchable` | `true` unless the person is currently separated (see below) or `hidden_from_provider_list_query` is set. |
| `clinical_title` | The title of the person's first `clinical_specializations` entry. It is that entry's `code_name` when its `title_specialization_query` is on and `code_name` is not empty. Otherwise it is the first non-empty of `title_specialization_patient`, `title_occupation` and the specialization entry's own title. Empty when the person has no specialization. |
| `appointment_context` | Eligibility, the published locations, the portal and the appointment line. |
| `provider_profile_body_rendered` | Whether anything renders between the two appointment calls to action. |
| `provider_resources_url` | The provider resources archive URL, or an empty string when the archive is not mounted, its mount entry is unpublished, or the person has no slug. |
| `hide_medical_ontology` | Whether Find a Doc removal rules suppress the medical ontology for this provider. A rule row that names both a service line and a region needs both to match, and a row naming neither is skipped. |
| `asp_filter` | The titles of the person's expertise, conditions and treatments, joined with a semicolon and a space. |
| `brand_organizations` | The `brand_organization` of each linked clinical location. |
| `regions` | The regions of each linked clinical location. Needs the host's [`LocationRegions` binding](Host-bindings.md). |
| `portal` | The `portals` value of the first linked clinical location. |
| `separated_active_query` | `true` when `clinical_separated_query` is set, today falls between `separated_start_date` and `separated_end_date`, and the entry is published. |
| `separated_unpublish_query` | `true` when the person is separated and `separated_end_date` is before today. |
| `research_publication_select` | The selected publications, enriched with citation HTML by the host's [`PublicationCitations` binding](Host-bindings.md). |

## Keeping eligibility current

Eligibility depends on other entries, such as clinical locations, and Statamic refreshes a computed index only when the indexed entry itself is saved. Two pieces close the gap:

- A listener on `EntrySaved` and `EntryDeleted` for `clinical_locations` entries refreshes the eligibility index of every person linked to that location.
- A queued, unique job, `RefreshProviderEligibilityForDatedLocations`, runs on the `clinical_provider_profile.location_date_refresh.cron` schedule. It finds published locations whose date has passed since its last run and refreshes the people linked to them, so a location dated in the future switches on without a save.

Before an entry saves, a listener removes the virtual `pubmed_information` values from `research_publication_select` sets, and the virtual certification activity values from `certifications` sets, so those never reach storage.
