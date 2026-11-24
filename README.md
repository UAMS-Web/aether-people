# People

A Statamic add-on (`uams-web/aether-people`) for the `people` collection, its lenses and the clinical provider layer. It adds:

- the `people` and `people_groups` collections, with their blueprints and fieldsets;
- five lenses that give a person a public page (`provider`, `academic`, `institutional`, `researcher`, `general`);
- five fieldtypes, computed fields, and a `people_lens_url` tag with two modifiers and a query scope;
- the Find a Doc API (`/api/v1/find-a-doc/providers`);
- a merge screen for duplicate people, two dashboard widgets, an editing scope for user groups, and Workday field locks.

Every area has a page in [the wiki](https://github.com/UAMS-Web/aether-people/wiki); its source is [`docs/wiki/`](docs/wiki/Home.md).

## Requirements

PHP 8.4 or later and Statamic 6.35 or later, matching `composer.json`. `tests/Unit/ReadmeTest.php` fails when these drift from it.

## Installation

```bash
composer require uams-web/aether-people
```

The service provider is discovered through `extra.laravel.providers`. The add-on's settings are in config files it merges itself, so the defaults work with no step. To change the role title scenarios, publish that file:

```bash
php artisan vendor:publish --tag=aether-people-role-title-config
```

The other settings (`people`, `people_lenses`, `clinical_provider_profile`, `researcher_profile` and `find_a_doc_api`) are overridden in the host's own config or environment; see [Configuration](https://github.com/UAMS-Web/aether-people/wiki/Configuration).

## Development

Developing against a host application is covered in [the development guide](docs/DEVELOPMENT.md), and the Packagist and Marketplace steps in [the submission checklist](docs/SUBMISSION.md). `composer ci` runs the whole gate.

## Namespace

The add-on owns the `UAMSWeb\Aether\People` prefix, mapped to `src/`. A companion add-on, `aether-listings-management`, nests its namespace inside it (`UAMSWeb\Aether\People\ListingsManagement\`), so `src/` holds no directory named `ListingsManagement`.

## License

MIT. See [`LICENSE`](LICENSE).
