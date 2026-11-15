<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Researcher people-lens eligibility signals (UAMS-Web/uams-statamic-archive#1667)
    |--------------------------------------------------------------------------
    |
    | A person qualifies for the `researcher` people lens when ANY of these
    | `people` fields is non-empty - i.e. the person has a public research
    | identity (a UAMS TRI research profile, a scholarly author identifier, or
    | declared research interests).
    |
    | Unlike the org-derived academic / institutional lenses (gated by a site's
    | organization roster over the org-to-people bridge), the researcher signal is
    | PERSON-LEVEL: researchers are scattered across many sites with no single
    | research organization, and the org-role map (`config/department_hub.php`)
    | has no research role. So the lens gates on the person, not the site - it is
    | registered on every site and this signal is the filter (mirroring the
    | person-level clinical eligibility gate, `config/clinical_provider_profile`).
    |
    | Add a handle here to widen what counts as a researcher.
    |
    */
    'signals' => [
        'research_profile_url',     // UAMS TRI research profile link
        'research_interests',       // declared research-interest taxonomy terms
        'orcid_id',                 // ORCID iD
        'researcherid',             // Web of Science ResearcherID
        'scopus_author_id',         // Scopus Author ID
        'google_scholar_author_id', // Google Scholar author ID
        'pubmed_author_id',         // PubMed author identifier
    ],
];
