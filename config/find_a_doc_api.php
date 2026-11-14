<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Find-a-Doc read API (UAMS-Web/uams-statamic-archive#1043)
    |--------------------------------------------------------------------------
    |
    | The read-only JSON API that mirrors the WordPress UAMSWP-Find-a-Doc
    | `provider_meta` payload for the shipped "UAMS Health" native app
    | (edu.uams.uamsnow). Only providers eligible for a clinical-provider profile
    | (UAMS-Web/uams-statamic-archive#919) and currently searchable (not separated/hidden) are exposed, and the
    | payload is an explicit allow-list - no `people` PII (#47) ever leaves.
    |
    */

    // The payload contract version. Bumped only for a breaking shape change; the
    // app is an uncontrolled consumer, so v1 stays backward-compatible.
    'version' => 'v1',

    // Seconds to cache the eligible-provider index (a full-collection eligibility
    // scan). Entry edits become visible after at most this long. Set 0 to disable.
    'index_cache_ttl' => (int) env('FIND_A_DOC_API_INDEX_CACHE_TTL', 300),

    // Per-minute request cap applied to every endpoint (Laravel throttle).
    'rate_limit_per_minute' => (int) env('FIND_A_DOC_API_RATE_LIMIT', 60),

];
