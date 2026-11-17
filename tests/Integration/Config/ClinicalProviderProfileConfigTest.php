<?php

declare(strict_types=1);

/**
 * The shape of `config/clinical_provider_profile.php`.
 *
 * The config has no backing class, so the array is the only surface to assert. Its consumers
 * (`ClinicalProviderEligible`, `ClinicalProviderEligiblePeople`, the location listener and the
 * dated-location job) each pass against whatever the rules hold, so a renamed field, a changed
 * operator or a dropped rule would change which people are eligible without failing them.
 *
 * @command  vendor/bin/pest tests/Integration/Config/ClinicalProviderProfileConfigTest.php
 */
it('holds exactly the base rules, the paths and the scheduled refresh settings', function (): void {
    expect(array_keys(config()->array('clinical_provider_profile')))->toBe(['base', 'paths', 'location_date_refresh']);
});

it('holds the base rules, in order, with their fields, operators and values', function (): void {
    expect(config('clinical_provider_profile.base'))->toBe([
        ['field' => 'uams_worker_query', 'operator' => 'equals', 'value' => true],
        ['field' => 'uams_worker_subtype', 'operator' => 'equals', 'value' => 'employee'],
        ['field' => 'uams_employee_subtype', 'operator' => 'equals', 'value' => 'regular'],
        ['field' => 'trainee_type', 'operator' => 'not_equals', 'value' => 'resident'],
        ['field' => 'trainee_type', 'operator' => 'not_equals', 'value' => 'fellow'],
        ['field' => 'clinical_role_query', 'operator' => 'equals', 'value' => true],
        ['field' => 'direct_patient_care_query', 'operator' => 'equals', 'value' => true],
        ['field' => 'clinical_profile_disable_query', 'operator' => 'not_equals', 'value' => true],
        ['field' => 'clinical_locations', 'operator' => 'has_published_entry'],
    ]);
});

it('holds the two paths with their rules and the credential slugs that qualify', function (): void {
    expect(config('clinical_provider_profile.paths'))->toBe([
        'appointments_and_locations' => [
            ['field' => 'appointments_query', 'operator' => 'equals', 'value' => true],
            ['field' => 'appointments_individual_query', 'operator' => 'equals', 'value' => true],
        ],
        'clinical_credentials' => [
            [
                'field' => 'credentials',
                'operator' => 'contains_any',
                'values' => [
                    'md', 'm-d',
                    'do', 'd-o',
                    'dpm', 'd-p-m',
                    'od', 'o-d',
                    'dds', 'd-d-s',
                    'dmd', 'd-m-d',
                    'cnm', 'c-n-m',
                ],
            ],
        ],
    ]);
});

it('holds the scheduled refresh settings as a boolean and a cron string, whatever the environment sets', function (): void {
    $refresh = config()->array('clinical_provider_profile.location_date_refresh');

    expect(array_keys($refresh))->toBe(['enabled', 'cron'])
        ->and($refresh['enabled'])->toBeBool()
        ->and($refresh['cron'])->toBeString()->not->toBe('');
});
