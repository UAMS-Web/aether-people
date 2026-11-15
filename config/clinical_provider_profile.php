<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | UAMS Health clinical provider profile eligibility
    |--------------------------------------------------------------------------
    |
    | Declarative criteria for when a person may have a UAMS Health clinical
    | provider profile (people collection). Intended for PHP consumers that
    | evaluate entry data against these rules.
    |
    | Logic (all must hold):
    |   1. Every rule in `base` passes (AND).
    |   2. At least one of `paths` passes (OR between paths).
    |
    | Within each path, every rule must pass (AND).
    |
    | Operators:
    |   - equals: field value must strictly equal `value` (booleans as shown).
    |   - not_equals: field value must not equal `value`.
    |   - not_empty: field must be present and non-empty (arrays/terms lists).
    |   - contains_any: field value (array of slugs/IDs/strings) must
    |     intersect `values` with at least one match after normalization
    |     appropriate to your consumer (e.g. slug compare).
    |   - has_published_entry: field value (array of entry IDs) must name at
    |     least one entry that exists and whose status is published. Drafts and
    |     IDs that resolve to no entry do not count, and a scheduled entry (one
    |     published with a future date in a collection that keeps future
    |     entries private, as `clinical_locations` does) counts from its date.
    |
    | Every `field` must be a field the people `person` blueprint defines.
    |
    */

    'base' => [
        [
            'field' => 'uams_worker_query',
            'operator' => 'equals',
            'value' => true,
        ],
        [
            'field' => 'uams_worker_subtype',
            'operator' => 'equals',
            'value' => 'employee',
        ],
        [
            'field' => 'uams_employee_subtype',
            'operator' => 'equals',
            'value' => 'regular',
        ],
        [
            'field' => 'resident_query',
            'operator' => 'equals',
            'value' => false,
        ],
        [
            'field' => 'fellow_query',
            'operator' => 'equals',
            'value' => false,
        ],
        [
            'field' => 'clinical_role_query',
            'operator' => 'equals',
            'value' => true,
        ],
        [
            'field' => 'direct_patient_care_query',
            'operator' => 'equals',
            'value' => true,
        ],
        // The person has not opted out of a profile (UAMS-Web/uams-statamic-archive#3123: applies on every path)
        [
            'field' => 'clinical_profile_disable_query',
            'operator' => 'not_equals',
            'value' => true,
        ],
        // At least one practice location with a published profile (UAMS-Web/uams-statamic-archive#3123); a scheduled
        // location counts from its date (UAMS-Web/uams-statamic-archive#3178)
        [
            'field' => 'clinical_locations',
            'operator' => 'has_published_entry',
        ],
    ],

    'paths' => [
        'appointments_and_locations' => [
            [
                'field' => 'appointments_query',
                'operator' => 'equals',
                'value' => true,
            ],
            [
                'field' => 'appointments_individual_query',
                'operator' => 'equals',
                'value' => true,
            ],
        ],
        'clinical_credentials' => [
            [
                'field' => 'credentials',
                'operator' => 'contains_any',
                'values' => [
                    // Doctor of Medicine (M.D.)
                    'md',
                    'm-d',

                    // Doctor of Osteopathic Medicine (D.O.)
                    'do',
                    'd-o',

                    // Doctor of Podiatric Medicine (DPM)
                    'dpm',
                    'd-p-m',

                    // Doctor of Optometry (O.D.)
                    'od',
                    'o-d',

                    // Doctor of Dental Surgery (D.D.S.)
                    'dds',
                    'd-d-s',

                    // Doctor of Medicine in Dentistry (D.M.D.)
                    'dmd',
                    'd-m-d',

                    // Certified Nurse Midwife (CNM)
                    'cnm',
                    'c-n-m',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled location refresh (UAMS-Web/uams-statamic-archive#3178)
    |--------------------------------------------------------------------------
    |
    | `App\Jobs\RefreshProviderEligibilityForDatedLocations` re-indexes the
    | people linked to each `clinical_locations` entry whose date has passed
    | since its last run, so a provider whose only location was scheduled
    | becomes eligible once that date arrives, without anyone saving the person
    | or the location. Saves and deletes are handled by a listener instead.
    |
    | The default runs every fifteen minutes, the cadence scheduled publishing
    | uses, so a location's date takes effect within a quarter hour. The cron
    | expression is read in `statamic.system.display_timezone`. `enabled` lets
    | an environment carry the code without the schedule. Not an eligibility
    | rule: the evaluator reads only `base` and `paths`.
    |
    */

    'location_date_refresh' => [
        'enabled' => (bool) env('PROVIDER_LOCATION_DATE_REFRESH_ENABLED', true),
        'cron' => (string) env('PROVIDER_LOCATION_DATE_REFRESH_CRON', '*/15 * * * *'),
    ],
];
