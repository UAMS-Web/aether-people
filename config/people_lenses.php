<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Lenses\Gates\AcademicLensGate;
use UAMSWeb\Aether\People\Lenses\Gates\ClinicalProviderLensGate;
use UAMSWeb\Aether\People\Lenses\Gates\InstitutionalLensGate;
use UAMSWeb\Aether\People\Lenses\Gates\ResearcherLensGate;

return [

    /*
    |--------------------------------------------------------------------------
    | Canonical people site (UAMS-Web/uams-statamic-archive#1635)
    |--------------------------------------------------------------------------
    |
    | Every person is stored as ONE `people` entry on this single site - no
    | per-site localizations across the ~255 sites. Every public appearance is a
    | cross-site render of that one entry through a lens (see `lenses` below), so
    | this is the site the substrate resolves an entry from regardless of which
    | site is rendering. The `people/provider/_listing` directory query pins the same site.
    |
    */
    'canonical_site' => 'default',

    /*
    |--------------------------------------------------------------------------
    | People lens registry (UAMS-Web/uams-statamic-archive#1636)
    |--------------------------------------------------------------------------
    |
    | A lens is a site-specific render of one canonical `people` entry -
    | (template, gate, route, sites) - for a particular audience. The clinical
    | provider lens is the first concrete instance (UAMS-Web/uams-statamic-archive#571); the org-gated lenses
    | derive their audience from the org-to-people bridge (UAMS-Web/uams-statamic-archive#1556 / UAMS-Web/uams-statamic-archive#1557). Each
    | descriptor:
    |
    |   - `template`  Antlers view under `resources/views/people/<lens>/` rendered
    |                 for this lens (base `people/show` for the ungated general lens).
    |   - `gate`      a `LensGate` class deciding whether a resolved person may be
    |                 rendered through this lens on the current site; `null` = no gate.
    |   - `route`     the URL pattern (with `{slug}`) at which the lens renders,
    |                 relative to each active site's own URL prefix.
    |   - `sites`     the site handles the lens is active on; `['*']` = every site.
    |
    | The clinical lens fits this registry with no special-casing - it is just the
    | descriptor whose gate is the UAMS-Web/uams-statamic-archive#919 eligibility filter.
    |
    */
    'lenses' => [

        'provider' => [
            'template' => 'people/provider/show',
            'gate' => ClinicalProviderLensGate::class,
            'route' => 'provider/{slug}',
            'sites' => ['health'],
        ],

        // Org-derived lenses (UAMS-Web/uams-statamic-archive#1636). Active on every site (`['*']`); the gate is the
        // real per-site filter - it renders only on a hub site whose organization has
        // the lens's role and only for a person in that org's derived roster, so a
        // non-hub or wrong-role site 404s (the controller delegates to the front end).
        'academic' => [
            'template' => 'people/academic/show',
            'gate' => AcademicLensGate::class,
            'route' => 'faculty/{slug}',
            'sites' => ['*'],
        ],

        'institutional' => [
            'template' => 'people/institutional/show',
            'gate' => InstitutionalLensGate::class,
            'route' => 'staff/{slug}',
            'sites' => ['*'],
        ],

        // Researcher lens (UAMS-Web/uams-statamic-archive#1667). Unlike the org-derived lenses above, its gate is
        // PERSON-LEVEL - a public research identity (see `config/researcher_profile.php`)
        // - because researchers are scattered across sites and the org-role map has no
        // research role. Registered on every site (`['*']`); the person-level gate is the
        // filter, so a non-researcher 404s the lens wherever it is requested.
        'researcher' => [
            'template' => 'people/researcher/show',
            'gate' => ResearcherLensGate::class,
            'route' => 'researcher/{slug}',
            'sites' => ['*'],
        ],

        // General lens (UAMS-Web/uams-statamic-archive#1667): the ungated base profile (`people/show`) as an explicit
        // registry entry. `gate: null` means every published, publicly-visible person
        // renders - so it ships OPT-IN with an explicit empty `sites` (registered, active
        // on NO site) rather than on all ~255 sites at once. A site that wants the plain
        // profile with no gate opts in by adding its handle here. Do NOT add the canonical
        // `default` site: people already render natively at `/people/{slug}` there, and a
        // general-lens route would shadow that native render.
        'general' => [
            'template' => 'people/show',
            'gate' => null,
            'route' => 'people/{slug}',
            'sites' => [],
        ],

    ],

];
