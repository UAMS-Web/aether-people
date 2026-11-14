<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;

covers(ProviderPeoplePersonNameComputedValues::class);

/**
 * Person-name computeds on providers/people entries: the empty result for an incomplete
 * `person_name`, the text and phonetic strings per display format, and the doctor honorific.
 *
 * @command vendor/bin/pest tests/Integration/ComputedFields/ProviderPeoplePersonNameComputedValuesTest.php
 */
it('returns empty name computeds when person_name is incomplete', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Steven',
                            'phonetic_text' => '',
                        ],
                        'no_middle_name' => false,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => false,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_generational_suffix' => false,
                        'generational_suffix' => null,
                        'display_format' => null,
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $display = $reflection->getMethod('getDisplayNameFull');
    $full = $reflection->getMethod('getFullName');

    expect($display->invoke($provider, $entry))->toBe([
        'text' => '',
        'phonetic_text' => null,
    ]);
    expect($full->invoke($provider, $entry))->toBe([
        'text' => '',
        'phonetic_text' => null,
    ]);
});

it('uses the text subfield for group name fields in getDisplayNameFull', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Jane',
                            'phonetic_text' => '',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => '',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => null,
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getDisplayNameFull');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe([
        'text' => 'Jane Doe',
        'phonetic_text' => null,
    ]);
});

it('ignores initial display formats for first and middle in getFullName', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'John Paul',
                            'phonetic_text' => '',
                        ],
                        'no_middle_name' => false,
                        'middle_name' => [
                            'text' => 'Robert',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => '',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'first_initial_full_middle@ns',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getFullName');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe([
        'text' => 'John Paul Robert Doe',
        'phonetic_text' => null,
    ]);
});

it('joins phonetic subfields in getDisplayNameFull per display format', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Leslie',
                            'phonetic_text' => 'ˈlɛsli',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Taylor',
                            'phonetic_text' => 'ˈteɪlər',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'full_first_only@ns',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getDisplayNameFull');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe([
        'text' => 'Leslie Taylor',
        'phonetic_text' => 'ˈlɛsli ˈteɪlər',
    ]);
});

it('uses letter IPA for the initial first-name segment in getDisplayNameFull phonetic text', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'John Paul',
                            'phonetic_text' => 'ignored_for_this_pattern',
                        ],
                        'no_middle_name' => false,
                        'middle_name' => [
                            'text' => 'Robert',
                            'phonetic_text' => 'ˈrɑbərt',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'first_initial_full_middle@ns',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('resolveDisplayNameFullPhoneticText');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe('ʤeɪ pi ˈrɑbərt doʊ');
});

it('uses stored subfield phonetics in getFullNamePhoneticText for initial formats', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'John Paul',
                            'phonetic_text' => 'ˈʤɑn pɔl',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'first_initial_full_middle@ns',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('resolveFullNamePhoneticText');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe('ˈʤɑn pɔl doʊ');
});

it('maps the pedigree Jr. label in getDisplayNameFull phonetic text', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'John',
                            'phonetic_text' => 'ʤɑn',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => false,
                        'generational_suffix' => 'Jr.',
                        'display_format' => 'full_first_only@ws',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('resolveDisplayNameFullPhoneticText');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe('ʤɑn doʊ ˈʤunjər');
});

it('prefers the pedigree label map IPA in getDisplayNameFull phonetic text', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'John',
                            'phonetic_text' => 'ʤɑn',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => false,
                        'generational_suffix' => 'Jr.',
                        'display_format' => 'full_first_only@ws',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('resolveDisplayNameFullPhoneticText');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe('ʤɑn doʊ ˈʤunjər');
});

it('prepends doctor IPA in display_name_full_with_prefix phonetic text when md credential', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Jane',
                            'phonetic_text' => 'ʤeɪn',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'full_first_only@ns',
                    ],
                    'credentials' => [
                        'md',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getDisplayNameFullWithPrefix');

    $result = $method->invoke($provider, $entry);

    expect(data_get($result, 'phonetic_text'))->toBe('ˈdɑktər ʤeɪn doʊ');
});

it('prepends doctor IPA with last phonetic in display_name_short_with_prefix phonetic text', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Jane',
                            'phonetic_text' => '',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Taylor',
                            'phonetic_text' => 'ˈteɪlər',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => null,
                    ],
                    'credentials' => [
                        'md',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getDisplayNameShortWithPrefix');

    $result = $method->invoke($provider, $entry);

    expect(data_get($result, 'phonetic_text'))->toBe('ˈdɑktər ˈteɪlər');
});

it('returns doctor IPA in prefix phonetic text when md credential', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'credentials' => [
                        'md',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getPrefixValue');

    $result = $method->invoke($provider, $entry);

    expect(data_get($result, 'phonetic_text'))->toBe('ˈdɑktər');
});

it('returns empty prefix phonetic text without a doctor credential', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'credentials' => [],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getPrefixValue');

    $result = $method->invoke($provider, $entry);

    expect(data_get($result, 'phonetic_text') ?? '')->toBe('');
});

it('omits doctor IPA in display_name_full_with_credentials phonetic text even with md credential', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Jane',
                            'phonetic_text' => 'ʤeɪn',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'full_first_only@ns',
                    ],
                    'credentials' => [
                        'md',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getDisplayNameFullWithCredentials');

    $result = $method->invoke($provider, $entry);
    $phonetic = data_get($result, 'phonetic_text');

    expect($phonetic)->toBeString();
    /** @var string $phonetic */
    expect(\str_starts_with(
        $phonetic,
        'ˈdɑktər'
    ))->toBeFalse();
    expect($phonetic)->toContain('ʤeɪn');
    expect($phonetic)->toContain('doʊ');
    expect($phonetic)->not->toContain(', ');
});
