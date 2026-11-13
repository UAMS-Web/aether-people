<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Tests\Support\PersonNameDisplayFormatFixtures;

/**
 * UAMS-Web/uams-statamic-archive#3053: the committed display-format fixture is what
 * `PersonNameDisplayFormats::buildOptions()` returns today. The Vitest spec
 * `personNameDisplayFormats.fixtures.test.js` holds the JavaScript to the same
 * file, so a PHP change that is not regenerated fails here, and a JavaScript
 * change that no longer matches fails there.
 */
it('matches the committed display-format fixture', function (): void {
    $path = PersonNameDisplayFormatFixtures::fixturePath();
    $committed = is_file($path) ? file_get_contents($path) : false;

    // A missing or unreadable fixture fails rather than skips
    expect($committed)->toBeString();

    expect($committed === PersonNameDisplayFormatFixtures::encode())->toBeTrue(
        'tests/Fixtures/PersonNameDisplayFormats/display-format-options.json is stale. Run: '
            .PersonNameDisplayFormatFixtures::REGENERATE_COMMAND
    );
});

it('covers both outcomes of the readiness gate and the nickname-off mapping', function (): void {
    $cases = PersonNameDisplayFormatFixtures::build()['cases'];

    $withOptions = array_filter($cases, fn (array $case): bool => $case['options'] !== []);
    $withoutOptions = array_filter($cases, fn (array $case): bool => $case['options'] === []);
    $nicknameOff = array_filter($cases, fn (array $case): bool => ! $case['include_nickname'] && $case['options'] !== []);

    expect($withOptions)->not->toBeEmpty()
        ->and($withoutOptions)->not->toBeEmpty()
        ->and($nicknameOff)->not->toBeEmpty();
});

it('builds no nickname option for a field that hides the nickname', function (): void {
    $value = [
        'first_name' => ['text' => 'Ann'],
        'last_name' => ['text' => 'Lee'],
        'no_middle_name' => true,
        'nickname' => ['text' => 'Annie'],
        'no_generational_suffix' => true,
    ];

    $keys = array_column(PersonNameDisplayFormatFixtures::options($value, false), 'key');
    $keysWithNickname = array_column(PersonNameDisplayFormatFixtures::options($value, true), 'key');

    expect($keys)->toBe(['full_first_only@ns'])
        ->and($keysWithNickname)->toContain('full_first_only_nickname@ns');
});
