<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

/**
 * The handle of the `people` collection, read from `people.collection` (UAMS-Web/uams-statamic-archive#3180).
 *
 * Code that names the collection reads it through here rather than as a literal, in this add-on
 * and in the host site alike (UAMS-Web/uams-statamic#281).
 */
final class PeopleCollection
{
    /**
     * The configured handle of the `people` collection.
     */
    public static function handle(): string
    {
        return config()->string('people.collection');
    }
}
