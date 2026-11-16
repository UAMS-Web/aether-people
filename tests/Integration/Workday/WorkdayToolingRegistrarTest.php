<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Workday\WorkdayToolingRegistrar;

covers(WorkdayToolingRegistrar::class);

/**
 * The two Control Panel routes the Workday tooling registrar serves for the gender override widget.
 * The listeners it subscribes are pinned, with their order and the provider layer flag, in
 * `ProviderLayerFlagTest`.
 *
 * @command vendor/bin/pest tests/Integration/Workday/WorkdayToolingRegistrarTest.php
 */
it('serves the two actions the gender override widget links to', function (): void {
    expect(cp_route('people-workday-gender.confirm', ['id' => 'x']))->toEndWith('/people/workday-gender/x/confirm')
        ->and(cp_route('people-workday-gender.take', ['id' => 'x']))->toEndWith('/people/workday-gender/x/take');
});
