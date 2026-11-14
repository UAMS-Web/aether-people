<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Contracts;

use Statamic\Entries\Entry;

/**
 * Whether a person has a public research identity.
 *
 * Temporary: the host binds it while the eligibility class still lives in the host.
 * It is deleted when the class moves into the add-on with the clinical one
 * (UAMS-Web/aether-people#56).
 */
interface ResearcherEligibility
{
    /**
     * Whether the person has a public research identity.
     *
     * @param  Entry  $entry  The canonical `people` entry.
     */
    public function qualifies(Entry $entry): bool;
}
