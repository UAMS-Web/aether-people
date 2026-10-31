# People

A Statamic add-on (`uams-web/aether-people`) for the `people` collection, its lenses and the clinical provider layer. It is the starting point for moving that code out of the host site (`UAMS-Web/uams-statamic#281`); it holds no feature code yet.

## Requirements

PHP 8.4 or later and Statamic 6.35 or later, matching `composer.json`. `tests/Unit/ReadmeTest.php` fails when these drift from it.

## Installation

```bash
composer require uams-web/aether-people
```

The service provider is discovered through `extra.laravel.providers`. The add-on's settings, once it has any, are published with:

```bash
php artisan vendor:publish --tag=aether-people-config
```

## Development

Developing against a host application is covered in [the development guide](docs/DEVELOPMENT.md), and the Packagist and Marketplace steps in [the submission checklist](docs/SUBMISSION.md). `composer ci` runs the whole gate.

## Namespace

The add-on owns the `UAMSWeb\Aether\People` prefix, mapped to `src/`. A companion add-on, `aether-listings-management`, nests its namespace inside it (`UAMSWeb\Aether\People\ListingsManagement\`), so `src/` holds no directory named `ListingsManagement`.

## License

No license is declared yet. The choice waits on the general decision from UAMS legal in [`UAMS-Web/uams-statamic#261`](https://github.com/UAMS-Web/uams-statamic/issues/261).
