Written against v0.5.4.

`uams-web/aether-people` is a Statamic add-on for UAMS sites. It holds the `people` collection and its blueprints, the lenses that render one canonical person differently on each site, the fieldtypes for names and role titles, the tools editors use to keep the collection clean, and the clinical provider layer behind the Find a Doc API.

This wiki holds the documentation: a walkthrough, guides by task, the reference and troubleshooting. The [README](https://github.com/UAMS-Web/aether-people#readme) keeps the requirements and installation.

## Getting started

- [Installation (README)](https://github.com/UAMS-Web/aether-people#installation): add the package to a Statamic install.
- [Walkthrough](Walkthrough.md): publish a researcher profile and see the checks behind it.

## Guides

- [Merging duplicate people](Merging-duplicate-people.md): retire duplicates and redirect them to one entry.
- [Reviewing gender overrides](Reviewing-gender-overrides.md): decide whether an editor's gender value stands against Workday's.
- [Limiting an editor to their organizations](Limiting-an-editor-to-their-organizations.md): scope a user group to the people in certain organizations.

## Troubleshooting

- [Troubleshooting](Troubleshooting.md): one entry per symptom, with its cause and fix.

## Reference

- [Reference](Reference.md): the map of the reference pages below.
- [Configuration](Configuration.md): every config key and environment variable.
- [Lenses](Lenses.md): routes, gates and templates.
- [Fieldtypes](Fieldtypes.md): the five fieldtypes.
- [Tags and modifiers](Tags-and-modifiers.md): the Antlers tag, modifiers and query scope.
- [Computed fields](Computed-fields.md): the derived people fields.
- [Control Panel tools](Control-Panel-tools.md): merge, widgets, permissions, editing scope and Workday locks.
- [Find a Doc API](Find-a-Doc-API.md): the provider endpoints and eligibility rules.
- [Blueprints and fieldsets](Blueprints-and-fieldsets.md): the blueprints, tabs, fieldsets and views.
- [Host bindings](Host-bindings.md): what the host application supplies.

## Project

- [Project](Project.md): where the add-on is developed and how to report a problem.
