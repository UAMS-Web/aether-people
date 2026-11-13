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
];
