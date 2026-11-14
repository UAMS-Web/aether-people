<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Contracts;

use Statamic\Entries\Entry;

/**
 * Whether a person qualifies for a UAMS Health clinical provider profile.
 *
 * Temporary: the host binds it while the eligibility class still lives in the host.
 * It is deleted when the class moves into the add-on (UAMS-Web/aether-people#56).
 */
interface ClinicalProviderEligibility
{
    /**
     * Whether the person qualifies for a clinical provider profile.
     *
     * @param  Entry  $entry  The canonical `people` entry.
     */
    public function qualifies(Entry $entry): bool;
}
