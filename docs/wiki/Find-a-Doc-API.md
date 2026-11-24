Written against v0.5.4.

The clinical provider layer decides which people are clinical providers and serves them through a read-only JSON API. It mirrors the payload the UAMS Health native app reads. The layer is on by default and `PEOPLE_PROVIDER_LAYER=false` turns it off (see [Provider layer switch](Configuration.md#provider-layer-switch)).

## Endpoints

Both routes use the middleware `api` and `throttle:N,1`, where `N` is `find_a_doc_api.rate_limit_per_minute`. The add-on applies no authentication to them.

| Method | Path | Returns |
| --- | --- | --- |
| GET | `/api/v1/find-a-doc/providers` | `{"version": "v1", "data": [provider, ...]}` |
| GET | `/api/v1/find-a-doc/providers/{provider}` | `{"version": "v1", "data": provider}` |

`{provider}` is the person's entry id. An id that is missing, not a person, not eligible, or not searchable answers 404 with "Provider not found or not available." The list is cached for `find_a_doc_api.index_cache_ttl` seconds; the single-provider route is not cached.

A person is exposed when the entry is published, [eligible](#eligibility-rules), and `searchable`.

## Provider payload

| Field | Type |
| --- | --- |
| `id`, `slug` | string, or null when empty |
| `url` | absolute URL string, or null when empty |
| `name` | object with `full`, `full_with_credentials`, `short_with_prefix`, `prefix`, each a string or null |
| `credentials` | list of strings |
| `clinical_title` | string or null |
| `specialties` | list of `clinical_specializations` entry slugs |
| `service_line` | list of strings |
| `pronouns` | string or null |
| `appointments` | object with `accepting_new_patients` and `referral_required`, both booleans |
| `is_resident` | boolean |
| `portrait` | object with `headshot` and `wide`, the stored asset paths |

The payload is an allow-list. It never includes an NPI, Epic SER id, Workday id, email, phone, fax or gender.

## Eligibility rules

`ClinicalProviderProfileEligibility` reads `config/clinical_provider_profile.php` on every call. A person is eligible when every rule in `base` passes and at least one entry in `paths` passes (all of its rules).

Base rules, all required:

- `uams_worker_query` is true
- `uams_worker_subtype` is `employee`
- `uams_employee_subtype` is `regular`
- `trainee_type` is not `resident`
- `trainee_type` is not `fellow`
- `clinical_role_query` is true
- `direct_patient_care_query` is true
- `clinical_profile_disable_query` is not true
- `clinical_locations` has at least one published entry (a location dated in the future counts from its date)

Paths, any one:

- `appointments_and_locations`: `appointments_query` and `appointments_individual_query` are both true.
- `clinical_credentials`: `credentials` contains any of `md`, `m-d`, `do`, `d-o`, `dpm`, `d-p-m`, `od`, `o-d`, `dds`, `d-d-s`, `dmd`, `d-m-d`, `cnm`, `c-n-m`.

Operators a rule may use: `equals`, `not_equals`, `not_empty`, `contains_any` and `has_published_entry`. Text comparisons ignore case, and an unknown operator fails the rule.

Eligibility is also what the `provider` [lens](Lenses.md) and the [`clinical_provider_eligible` modifier](Tags-and-modifiers.md#clinical_provider_eligible) test.
