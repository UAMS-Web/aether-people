Written against v0.5.4.

The collections, blueprints and fieldsets the add-on serves. Field-level help text lives in the blueprint and fieldset files themselves (`resources/collection-blueprints/` and `resources/fieldsets/`), where the Control Panel reads it, so this page maps the structure and does not repeat it.

## Where they come from

- Statamic serves the two collection blueprint directories from the add-on. A blueprint saved in the Control Panel is written to the host's `resources/blueprints/vendor/collections/<collection>/` and wins over the add-on's file. See [Host bindings](Host-bindings.md#blueprints-and-fieldsets).
- Fieldsets in `resources/fieldsets/` are served under the `aether-people` namespace, so a host references one as `aether-people::<handle>`.
- Many fields import host fieldsets such as `uamsweb_common.*` and `uamsweb_entry_sitemap`, which the add-on does not define.

## people_groups

The blueprint `people_group` ("People, Group") has a Main tab with a required `title`, and a Sidebar tab with a required `slug` (at most 200 characters).

## people

The blueprint `person` has these tabs:

| Tab | Holds |
| --- | --- |
| General | Person name, alternate names, personal details (gender, pronouns, language, nationality), the Workday identity fields, job titles, work location, people groups, priority and the deceased details. |
| Portraits | `portrait` and `portrait_wide`. |
| Hometown Details | Home country and the hometown fields. |
| Bio | The biography and its short, institutional, clinical, academic, research and creator variants. |
| Associated Media | Video links per role, podcast guest name and `same_as`. |
| Contact Information | Email, telephone, extension, mobile, fax and location. |
| Links | Clinical, academic and research profile URLs with link text, and `twitter`. |
| Professional Background | Education, training, certifications, development activities and memberships. |
| Resident Role | Academic department, chief and academic year. Shown when `trainee_type` is `resident`. |
| Fellow Role | Academic department. |
| Institutional Role | No fields of its own in this release. |
| Academic Role | No fields of its own in this release. |
| Clinical Role | Eligibility flags, identifiers (NPI, Epic SER id), specializations, service line, locations, expertise, conditions, treatments, resources, Google Business Profile fields and continuity of care. |
| Research Role | Research interests, profile identifiers (ORCID, ResearcherID, Scopus, Google Scholar, PubMed) and publication display. |
| Recognition and Awards | Recognition lists and awards. |
| Computed Fields | The read-only [computed fields](Computed-fields.md). |
| Sidebar | Status and visibility, publish and unpublish dates, password protection, the sitemap fieldset and the slug. |

## Fieldsets

| Fieldset | Purpose |
| --- | --- |
| `uamsweb_common_people_name` | Legal name, nickname, name pronunciation, preferred display format and credentials. |
| `uamsweb_common_people` | Alternate names, gender, pronouns, work location, language, job titles and awards. |
| `uamsweb_common_people_background` | Education, training, certifications and development activities. |
| `uamsweb_common_people_background_certification`, `..._certification_board`, `..._certification_general` | The fields of one certification. |
| `uamsweb_common_people_block_set` | The fields shared by the people page-builder block and its Bard set. |
| `uamsweb_block_people` | The people page-builder block. |
| `uamsweb_set_people` | The people Bard set. |
| `uamsweb_block_fad_providers` | The Find a Doc providers block. |
| `uamsweb_common_people_continuity` | Continuity of care: separation flags, new location and dates. |
| `uamsweb_common_people_google_clinical` | Google Business Profile fields for clinical locations. |
| `uamsweb_common_people_research` | Research interests, profile URL and publication selection. |
| `uamsweb_computed_person` | Declarations of the computed fields. |

## Views and Control Panel assets

- A copy of a view in the host's `resources/views` wins over the add-on's. The add-on ships the lens templates under `people/`, the people and Find a Doc page-builder blocks, the `people_groups/show` archive, the public form field views for `person_name` and `text_phonetic_pair`, a people card for the search views and two dashboard widgets.
- The Control Panel scripts and styles build from `resources/js/cp.js` and `resources/css/cp.css` into the committed `resources/dist` directory.
