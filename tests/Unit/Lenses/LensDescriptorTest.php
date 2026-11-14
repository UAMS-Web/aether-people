<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Lenses\LensDescriptor;

covers(LensDescriptor::class);

/**
 * @covers \UAMSWeb\Aether\People\Lenses\LensDescriptor
 *
 * @command  php artisan test --compact tests/Unit/Support/People/Lenses/LensDescriptorTest.php
 */
it('is active on every site when the sites list is a star wildcard', function (): void {
    $lens = new LensDescriptor(
        'general',
        'people/general/show',
        null,
        'people/{slug}',
        ['*'],
    );

    expect($lens->activeOnSite('default'))->toBeTrue()
        ->and($lens->activeOnSite('other'))->toBeTrue();
});

it('is active only on listed site handles', function (): void {
    $lens = new LensDescriptor(
        'provider',
        'people/provider/show',
        null,
        'providers/{slug}',
        ['medicine', 'nursing'],
    );

    expect($lens->activeOnSite('medicine'))->toBeTrue()
        ->and($lens->activeOnSite('nursing'))->toBeTrue()
        ->and($lens->activeOnSite('default'))->toBeFalse();
});

it('compares the wildcard strictly so a loose true does not match', function (): void {
    // ``'*' == true`` under loose equality; strict must keep the wildcard inert here
    $lens = new LensDescriptor(
        'x',
        'people/x/show',
        null,
        'x/{slug}',
        // @phpstan-ignore argument.type (deliberately off-contract: pins the strict wildcard comparison against a loosely equal non-string site)
        [true],
    );

    expect($lens->activeOnSite('default'))->toBeFalse();
});

it('compares site handles strictly so loosely equal values do not match', function (): void {
    // Seed a non-string site that loosely equals "1" but is not identical
    $lens = new LensDescriptor(
        'x',
        'people/x/show',
        null,
        'x/{slug}',
        // @phpstan-ignore argument.type (deliberately off-contract: pins the strict handle comparison against a loosely equal non-string site)
        [1],
    );

    expect($lens->activeOnSite('1'))->toBeFalse();
});
