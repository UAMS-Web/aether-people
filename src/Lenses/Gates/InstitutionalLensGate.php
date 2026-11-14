<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Lenses\Gates;

/**
 * The institutional people lens gate (UAMS-Web/uams-statamic-archive#1636): a person qualifies for the
 * `people/institutional/show` lens on an institutional department hub site when
 * they are in that organization's derived people roster (members + institutional
 * leadership).
 */
final class InstitutionalLensGate extends OrgRoleLensGate
{
    protected function orgRoles(): array
    {
        return ['department_institutional'];
    }
}
