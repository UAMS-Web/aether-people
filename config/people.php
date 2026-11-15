<?php

declare(strict_types=1);

/**
 * The `people` collection, as code reads it (UAMS-Web/uams-statamic-archive#3180).
 *
 * Merged under the `people` key rather than the add-on's own `aether-people` key, because every
 * config key keeps its name in the move (UAMS-Web/uams-statamic#281). A host's own
 * `config/people.php` overrides these values.
 *
 * The handle is not configurable in practice: content, blueprints, templates and
 * `UAMS-Web/wordpress-importer` all name `people`, and no handle is renamed.
 */
return [
    'collection' => 'people',

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | One switch per feature, on by default. Each takes true or false and reads
    | the environment variable named beside it.
    |
    | provider_layer  The clinical provider layer. Off, the provider eligibility
    |                 and listing computeds, their listeners, the scheduled
    |                 location-date re-index, the Find-a-Doc API routes and the
    |                 clinical provider lens are not registered: no provider
    |                 route answers and no clinical provider profile renders.
    |                 The `clinical_provider_eligible` modifier and scope stay
    |                 available, so a template that calls them still renders.
    |
    */
    'features' => [
        'provider_layer' => (bool) env('PEOPLE_PROVIDER_LAYER', true),
    ],
];
