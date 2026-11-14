<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Lenses\Gates;

/**
 * The academic people lens gate (UAMS-Web/uams-statamic-archive#1636): a person qualifies for the
 * `people/academic/show` lens on an academic department or division hub site when
 * they are in that organization's derived people roster (faculty + academic
 * leadership).
 */
final class AcademicLensGate extends OrgRoleLensGate
{
    protected function orgRoles(): array
    {
        return ['department_academic', 'division_academic'];
    }
}
