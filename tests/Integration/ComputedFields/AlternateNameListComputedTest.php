<?php

declare(strict_types=1);

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;

uses(MockeryPHPUnitIntegration::class);

covers(ProviderPeoplePersonNameComputedValues::class);

/**
 * Stub the name refinement fields kept beside `person_name` (UAMS-Web/uams-statamic-archive#3186) as unset,
 * so the stored name is read as written.
 *
 * @param  MockInterface  $entry  The mocked entry
 */
function altNameWithoutRefinements(MockInterface $entry): void
{
    foreach (['nickname', 'name_pronunciation', 'name_display_format'] as $handle) {
        $entry->shouldReceive('get')->with($handle)->andReturnNull();
    }
}

/**
 * Unit tests for `alternate_name_list` computed field on providers/people
 * collections.
 *
 * @command vendor/bin/pest tests/Integration/ComputedFields/AlternateNameListComputedTest.php
 */
it('returns rows for a simple person name', function (): void {
    /** @var ProviderPeoplePersonNameComputedValues $computer */
    $computer = app()->make(ProviderPeoplePersonNameComputedValues::class);

    $entry = Mockery::mock(Entry::class);
    altNameWithoutRefinements($entry);

    $personName = [
        'first_name' => [
            'text' => 'Test',
            'phonetic_text' => null,
        ],
        'middle_name' => [
            'text' => '',
            'phonetic_text' => null,
        ],
        'last_name' => [
            'text' => 'User',
            'phonetic_text' => null,
        ],
        'no_middle_name' => true,
        'no_nickname' => true,
        'nickname' => [
            'text' => null,
            'phonetic_text' => null,
        ],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => null,
    ];

    $entry->shouldReceive('get')
        ->with('person_name')
        ->andReturn($personName);

    $entry->shouldReceive('get')
        ->with(
            'alternate_name',
            []
        )
        ->andReturn([]);

    $result = $computer->getAlternateNameList(mustBe(Entry::class, $entry, 'the entry'));

    expect($result)->not->toBeEmpty();

    foreach ($result as $row) {
        expect($row)->toHaveKey('text');
        expect($row)->toHaveKey('phonetic_text');
    }
});

it('skips the stored format only for the primary nickname variant', function (): void {
    /** @var ProviderPeoplePersonNameComputedValues $computer */
    $computer = app()->make(ProviderPeoplePersonNameComputedValues::class);

    $entry = Mockery::mock(Entry::class);
    altNameWithoutRefinements($entry);

    $personName = [
        'first_name' => [
            'text' => 'Shaquille',
            'phonetic_text' => 'ʃəˈkil',
        ],
        'no_middle_name' => false,
        'middle_name' => [
            'text' => 'Rashaun',
            'phonetic_text' => 'rəˈʃɔːn',
        ],
        'no_nickname' => false,
        'nickname' => [
            'text' => 'Shaq',
            'phonetic_text' => 'ʃÃ¦k',
        ],
        'last_name' => [
            'text' => "O'Neal",
            'phonetic_text' => 'oʊˈnil',
        ],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => 'nickname_only@ns',
    ];

    $alternateName = [
        [
            'type' => 'nickname_set',
            'nickname' => [
                'text' => 'The Big Shaqtus',
                'phonetic_text' => 'Ã°ə bɪɡ ˈʃÃ¦ktəs',
            ],
        ],
    ];

    $entry->shouldReceive('get')
        ->with('person_name')
        ->andReturn($personName);

    $entry->shouldReceive('get')
        ->with(
            'alternate_name',
            []
        )
        ->andReturn($alternateName);

    $result = $computer->getAlternateNameList(mustBe(Entry::class, $entry, 'the entry'));

    $texts = array_column(
        $result,
        'text'
    );

    expect($texts)->not->toContain("Shaq O'Neal");
    expect($texts)->toContain("The Big Shaqtus O'Neal");
});

/**
 * A minimal, fully-specified primary person_name payload ("Test User") for the
 * free-text alternate-name cases (UAMS-Web/uams-statamic-archive#855).
 *
 * @return array<string, mixed>
 */
function altNameFreeTextPrimary(): array
{
    return [
        'first_name' => ['text' => 'Test', 'phonetic_text' => null],
        'middle_name' => ['text' => '', 'phonetic_text' => null],
        'last_name' => ['text' => 'User', 'phonetic_text' => null],
        'no_middle_name' => true,
        'no_nickname' => true,
        'nickname' => ['text' => null, 'phonetic_text' => null],
        'no_generational_suffix' => true,
        'generational_suffix' => null,
        'display_format' => null,
    ];
}

it('includes a free-text alternate name verbatim with a null phonetic (#855)', function (): void {
    /** @var ProviderPeoplePersonNameComputedValues $computer */
    $computer = app()->make(ProviderPeoplePersonNameComputedValues::class);

    $entry = Mockery::mock(Entry::class);
    altNameWithoutRefinements($entry);
    $entry->shouldReceive('get')->with('person_name')->andReturn(altNameFreeTextPrimary());
    $entry->shouldReceive('get')->with('alternate_name', [])->andReturn([
        ['type' => 'free_text', 'text' => 'Dr. T. User the Third'],
    ]);

    $result = $computer->getAlternateNameList(mustBe(Entry::class, $entry, 'the entry'));

    // A free_text set is a literal full name that bypasses structured expansion.
    $freeRow = mustExist(collect($result)->firstWhere('text', 'Dr. T. User the Third'), 'free row');
    expect($freeRow['phonetic_text'])->toBeNull();
});

it('skips blank, whitespace-only, and non-string free-text alternate names (#855)', function (): void {
    /** @var ProviderPeoplePersonNameComputedValues $computer */
    $computer = app()->make(ProviderPeoplePersonNameComputedValues::class);

    $entry = Mockery::mock(Entry::class);
    altNameWithoutRefinements($entry);
    $entry->shouldReceive('get')->with('person_name')->andReturn(altNameFreeTextPrimary());
    $entry->shouldReceive('get')->with('alternate_name', [])->andReturn([
        ['type' => 'free_text', 'text' => '   '],
        ['type' => 'free_text', 'text' => ''],
        ['type' => 'free_text'],
        ['type' => 'free_text', 'text' => 'Real Alternate'],
    ]);

    /** @var list<string|null> $texts */
    $texts = array_column($computer->getAlternateNameList(mustBe(Entry::class, $entry, 'the entry')), 'text');

    expect($texts)->toContain('Real Alternate');
    // No blank/whitespace/null row leaked through from the three skipped sets.
    expect(array_filter($texts, fn (?string $t): bool => $t === null || \trim($t) === ''))->toBeEmpty();
});

it('adds a free-text alternate name alongside structured sets without duplicating rows (#855)', function (): void {
    /** @var ProviderPeoplePersonNameComputedValues $computer */
    $computer = app()->make(ProviderPeoplePersonNameComputedValues::class);

    $entry = Mockery::mock(Entry::class);
    altNameWithoutRefinements($entry);
    $entry->shouldReceive('get')->with('person_name')->andReturn(altNameFreeTextPrimary());
    $entry->shouldReceive('get')->with('alternate_name', [])->andReturn([
        ['type' => 'nickname_set', 'nickname' => ['text' => 'Tester', 'phonetic_text' => null]],
        ['type' => 'free_text', 'text' => 'Public Persona Name'],
        ['type' => 'free_text', 'text' => 'Public Persona Name'],
    ]);

    $texts = array_column($computer->getAlternateNameList(mustBe(Entry::class, $entry, 'the entry')), 'text');

    // Structured nickname expansion still present (no regression).
    expect($texts)->toContain('Tester User');
    // Free-text present exactly once despite the duplicate set (dedupe folds it).
    expect($texts)->toContain('Public Persona Name');
    expect(count(array_keys($texts, 'Public Persona Name', true)))->toBe(1);
});
