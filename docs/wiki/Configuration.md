Written against v0.5.4.

Every setting the add-on reads. The provider merges six config files under their own keys, so a host overrides a value in its own `config/` directory or through the environment variable named below. The seventh file, `config/aether-people.php`, is the add-on's own settings file: it holds no settings, and `php artisan vendor:publish --tag=aether-people-config` copies that empty file to the host.

## people

| Key | Values | Default | Effect |
| --- | --- | --- | --- |
| `people.collection` | a collection handle | `people` | The handle `PeopleCollection::handle()` returns. The file comments say it is not configurable in practice, so leave it. |
| `people.features.provider_layer` | `true`, `false` | `true` (env `PEOPLE_PROVIDER_LAYER`) | Turns the clinical provider layer on or off. Only a value of exactly `false` turns it off. See [Provider layer switch](#provider-layer-switch). |

### Provider layer switch

With `PEOPLE_PROVIDER_LAYER=false`:

- the eligibility computed fields are not registered;
- the entry listeners that keep eligibility current are not subscribed;
- the scheduled location-date refresh is not registered;
- the [Find a Doc API](Find-a-Doc-API.md) routes are not registered;
- the provider lens never passes.

The editing scope, the Workday tooling, the merge feature and the lens routes stay on whatever the value. The `clinical_provider_eligible` modifier and the `clinical_provider_eligible_people` scope stay registered too.

## people_lenses

| Key | Values | Default | Effect |
| --- | --- | --- | --- |
| `people_lenses.canonical_site` | a site handle | `default` | The site that holds the one canonical entry for every person. Lens URLs resolve a slug against this site. |
| `people_lenses.lenses` | handle to `template`, `gate`, `route`, `sites` | five lenses | The lens registry. See [Lenses](Lenses.md). |

## clinical_provider_profile

| Key | Values | Default | Effect |
| --- | --- | --- | --- |
| `clinical_provider_profile.base` | a list of rules | nine rules | Every rule must pass for a person to be eligible. See [Eligibility rules](Find-a-Doc-API.md#eligibility-rules). |
| `clinical_provider_profile.paths` | a list of rule groups | two paths | At least one path must pass, with every rule inside that path passing. |
| `clinical_provider_profile.location_date_refresh.enabled` | `true`, `false` | `true` (env `PROVIDER_LOCATION_DATE_REFRESH_ENABLED`) | Registers the scheduled job. Anything other than `true` skips it. |
| `clinical_provider_profile.location_date_refresh.cron` | a cron expression | `*/15 * * * *` (env `PROVIDER_LOCATION_DATE_REFRESH_CRON`) | When the job runs, in the Statamic display timezone. |

## researcher_profile

| Key | Values | Default | Effect |
| --- | --- | --- | --- |
| `researcher_profile.signals` | a list of people field handles | `research_profile_url`, `research_interests`, `orcid_id`, `researcherid`, `scopus_author_id`, `google_scholar_author_id`, `pubmed_author_id` | A person counts as a researcher when any listed field is non-empty. The researcher lens uses it. |

## find_a_doc_api

| Key | Values | Default | Effect |
| --- | --- | --- | --- |
| `find_a_doc_api.version` | a string | `v1` | The `version` value in every response. The URL prefix is fixed at `api/v1/find-a-doc` and does not follow this key. |
| `find_a_doc_api.index_cache_ttl` | seconds | `300` (env `FIND_A_DOC_API_INDEX_CACHE_TTL`) | How long the provider list is cached. `0` or less turns the cache off. |
| `find_a_doc_api.rate_limit_per_minute` | requests | `60` (env `FIND_A_DOC_API_RATE_LIMIT`) | The throttle on both API routes. A value that is not an integer falls back to 60. |

## role_title

`config/role_title.php` defines the structured role titles the [`role_title` fieldtype](Fieldtypes.md#role_title) builds. It is data, not a list of switches: its top-level keys are `schema_version` (3), `sentinel`, `configurations`, `subfields` and `schema`.

- `configurations.handles` lists the configurations: `faculty`, `academic_leadership`, `institutional_leadership` and `clinical_leadership`. `configurations.default` is `faculty`.
- `subfields` defines the parts of a title, among them `title_options`, `scope_qualifier`, `hierarchical_modifier`, `object`, `positional_qualifier`, `role`, `honorific` and `scope`.
- `schema` is a list of scenario rows, each pairing field conditions with the schema.org output a title produces.

To change it, publish the file with `php artisan vendor:publish --tag=aether-people-role-title-config`, which copies it to `config/role_title.php` in the host.
