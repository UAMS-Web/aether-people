<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Lenses\Gates\AcademicLensGate;

covers(AcademicLensGate::class);

/**
 * @covers \UAMSWeb\Aether\People\Lenses\Gates\AcademicLensGate
 *
 * @command  php artisan test --compact tests/Unit/Lenses/AcademicLensGateTest.php
 */
it('admits both department_academic and division_academic hub roles', function (): void {
    // Avoid container boot: orgRoles() does not touch constructor deps
    $gate = new ReflectionClass(AcademicLensGate::class)
        ->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(AcademicLensGate::class, 'orgRoles');

    expect($method->invoke($gate))->toBe([
        'department_academic',
        'division_academic',
    ]);
});
