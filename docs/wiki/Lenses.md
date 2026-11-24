Written against v0.5.4.

A lens is a site-specific rendering of one canonical `people` entry. Every person is stored once, on the canonical site (`people_lenses.canonical_site`, default `default`), and each lens decides which sites show that person, at which URL, with which template, and whether the person qualifies.

## How a request is handled

1. For each lens and each distinct URL prefix of the sites where the lens is active, the add-on registers a route ahead of Statamic's catch-all.
2. On a request, the controller resolves the slug against the canonical site.
3. It renders the lens template only when the person is a published entry, `status_and_visibility` is not `private`, `password_protected` is not `true`, and the lens gate passes.
4. In every other case it hands the request to Statamic's normal front-end handling, so a real page at that URL still renders, and otherwise the response is a 404. A draft, private or password-protected person is never disclosed.

On success the page is rendered with the lens template inside the site layout, and its `permalink` is the lens URL, so each lens URL is its own canonical URL.

## The lenses

| Handle | Route | Template | Active on | Gate |
| --- | --- | --- | --- | --- |
| `provider` | `provider/{slug}` | `people/provider/show` | the `health` site | the person is clinical-provider eligible, and the provider layer is on |
| `academic` | `faculty/{slug}` | `people/academic/show` | every site | the site is a department hub whose organization has the role `department_academic` or `division_academic`, and the person is on that hub's roster |
| `institutional` | `staff/{slug}` | `people/institutional/show` | every site | the same test with the role `department_institutional` |
| `researcher` | `researcher/{slug}` | `people/researcher/show` | every site | any `researcher_profile.signals` field is non-empty, on any site |
| `general` | `people/{slug}` | `people/show` | no site until configured | none: every published, publicly visible person renders |

Notes:

- `sites` of `['*']` means every site. An empty list means the lens is registered but active nowhere, which is how `general` ships. Do not add the `default` site to `general`: it would shadow the native `/people/{slug}` rendering.
- A lens whose configuration lacks a non-empty `template` or `route` is skipped without an error, and a `gate` that is not a lens gate class is treated as no gate.
- The academic and institutional gates need the host to bind `DepartmentHubRoster`. See [Host bindings](Host-bindings.md).
- Lens templates live in `resources/views/people/`. A host copy of a view wins over the add-on's.

## Linking to a lens

The `people_lens_url` tag builds the URL for a lens on the current site. See [Tags and modifiers](Tags-and-modifiers.md#people_lens_url).

## Adding or changing a lens

Override `people_lenses.lenses` in the host's `config/people_lenses.php`. Each entry takes `template`, `gate` (a class implementing `UAMSWeb\Aether\People\Lenses\LensGate`, or `null`), `route` (a pattern containing `{slug}`) and `sites`.
