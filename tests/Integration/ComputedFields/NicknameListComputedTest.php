<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;

covers(ProviderPeoplePersonNameComputedValues::class);

/**
 * Stub the name refinement fields kept beside `person_name` (UAMS-Web/uams-statamic-archive#3186) as unset,
 * so the stored name is read as written.
 *
 * @param  MockInterface  $entry  The mocked entry
 */
function nicknameListWithoutRefinements(MockInterface $entry): void
{
    foreach (['nickname', 'name_pronunciation', 'name_display_format'] as $handle) {
        $entry->shouldReceive('get')->with($handle)->andReturnNull();
    }
}

/**
 * Unit tests for `nickname_list` computed field on providers/people collections.
 *
 * @command vendor/bin/pest tests/Integration/ComputedFields/NicknameListComputedTest.php
 */

/**
 * Invoke {@see ProviderPeoplePersonNameComputedValues::getNicknameList()} for tests.
 *
 * @param  ProviderPeoplePersonNameComputedValues  $computer  Name computeds service under test
 * @param  Entry  $entry  Mocked Statamic entry
 * @return list<array{text: string, phonetic_text: string|null}>
 */
function invokeNicknameListGetNicknameList(
    ProviderPeoplePersonNameComputedValues $computer,
    Entry $entry
): array {
    return $computer->getNicknameList($entry);
}

it('collects person_name string nickname and alternate sets', function (): void {
    /** @var ProviderPeoplePersonNameComputedValues $computer */
    $computer = app()->make(ProviderPeoplePersonNameComputedValues::class);

    $entry = Mockery::mock(Entry::class);
    nicknameListWithoutRefinements($entry);
    $entry->shouldReceive('get')
        ->with('person_name')
        ->andReturnUsing(static fn (): array => [
            'nickname' => 'Foo',
        ]);
    $entry->shouldReceive('get')
        ->with(
            'alternate_name',
            []
        )
        ->andReturnUsing(static fn (): array => [
            [
                'type' => 'nickname_set',
                'nickname' => [
                    'text' => 'Bar',
                    'phonetic_text' => 'bɑr',
                ],
            ],
            [
                'type' => 'alternate_name_set',
                'person_name' => [],
            ],
        ]);

    $result = invokeNicknameListGetNicknameList(
        $computer,
        mustBe(Entry::class, $entry, 'the entry')
    );

    expect($result)->toBe([
        [
            'text' => 'Foo',
            'phonetic_text' => null,
        ],
        [
            'text' => 'Bar',
            'phonetic_text' => 'bɑr',
        ],
    ]);
});

it('uses first non-empty phonetic in encounter order for duplicate text', function (): void {
    /** @var ProviderPeoplePersonNameComputedValues $computer */
    $computer = app()->make(ProviderPeoplePersonNameComputedValues::class);

    $entry = Mockery::mock(Entry::class);
    nicknameListWithoutRefinements($entry);
    $entry->shouldReceive('get')
        ->with('person_name')
        ->andReturnUsing(static fn (): array => [
            'nickname' => [
                'text' => 'Foo',
                'phonetic_text' => 'first',
            ],
        ]);
    $entry->shouldReceive('get')
        ->with(
            'alternate_name',
            []
        )
        ->andReturnUsing(static fn (): array => [
            [
                'type' => 'nickname_set',
                'nickname' => [
                    'text' => 'Foo',
                    'phonetic_text' => 'second',
                ],
            ],
        ]);

    $result = invokeNicknameListGetNicknameList(
        $computer,
        mustBe(Entry::class, $entry, 'the entry')
    );

    expect($result)->toBe([
        [
            'text' => 'Foo',
            'phonetic_text' => 'first',
        ],
    ]);
});

it('fills phonetic from a later duplicate when earlier entries are empty', function (): void {
    /** @var ProviderPeoplePersonNameComputedValues $computer */
    $computer = app()->make(ProviderPeoplePersonNameComputedValues::class);

    $entry = Mockery::mock(Entry::class);
    nicknameListWithoutRefinements($entry);
    $entry->shouldReceive('get')
        ->with('person_name')
        ->andReturnUsing(static fn (): array => [
            'nickname' => [
                'text' => 'Foo',
                'phonetic_text' => null,
            ],
        ]);
    $entry->shouldReceive('get')
        ->with(
            'alternate_name',
            []
        )
        ->andReturnUsing(static fn (): array => [
            [
                'type' => 'nickname_set',
                'nickname' => [
                    'text' => 'Foo',
                    'phonetic_text' => 'filled',
                ],
            ],
        ]);

    $result = invokeNicknameListGetNicknameList(
        $computer,
        mustBe(Entry::class, $entry, 'the entry')
    );

    expect($result)->toBe([
        [
            'text' => 'Foo',
            'phonetic_text' => 'filled',
        ],
    ]);
});

it('skips a phonetic-only object with no text key', function (): void {
    /** @var ProviderPeoplePersonNameComputedValues $computer */
    $computer = app()->make(ProviderPeoplePersonNameComputedValues::class);

    $entry = Mockery::mock(Entry::class);
    nicknameListWithoutRefinements($entry);
    $entry->shouldReceive('get')
        ->with('person_name')
        ->andReturnUsing(static fn (): array => [
            'nickname' => [
                'phonetic_text' => 'orphan',
            ],
        ]);
    $entry->shouldReceive('get')
        ->with(
            'alternate_name',
            []
        )
        ->andReturnUsing(static fn (): array => []);

    $result = invokeNicknameListGetNicknameList(
        $computer,
        mustBe(Entry::class, $entry, 'the entry')
    );

    expect($result)->toBe([]);
});

it('yields null phonetic_text when an object has a text key but no phonetic key', function (): void {
    /** @var ProviderPeoplePersonNameComputedValues $computer */
    $computer = app()->make(ProviderPeoplePersonNameComputedValues::class);

    $entry = Mockery::mock(Entry::class);
    nicknameListWithoutRefinements($entry);
    $entry->shouldReceive('get')
        ->with('person_name')
        ->andReturnUsing(static fn (): array => [
            'nickname' => [
                'text' => 'Baz',
            ],
        ]);
    $entry->shouldReceive('get')
        ->with(
            'alternate_name',
            []
        )
        ->andReturnUsing(static fn (): array => []);

    $result = invokeNicknameListGetNicknameList(
        $computer,
        mustBe(Entry::class, $entry, 'the entry')
    );

    expect($result)->toBe([
        [
            'text' => 'Baz',
            'phonetic_text' => null,
        ],
    ]);
});
