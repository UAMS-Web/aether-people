<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use UAMSWeb\Aether\People\Support\PersonNameComposite;

/**
 * A new, unsaved entry holding the given field values.
 *
 * @param  array<string, mixed>  $data  The field values.
 * @return Entry The entry.
 */
function personNameEntry(array $data): Entry
{
    // Make the entry and set its values
    $entry = mustBe(Entry::class, EntryFacade::make(), 'a new entry');
    $entry->data($data);

    return $entry;
}

/**
 * Tests for PersonNameComposite::fromEntry(), which reads the four name fields through
 * an entry, so it needs Statamic booted.
 *
 * @command  vendor/bin/pest tests/Integration/Support/PersonNameCompositeEntryTest.php
 */
it('joins the four name fields read from an entry', function (): void {
    $entry = personNameEntry([
        'person_name' => [
            'first_name' => ['text' => 'Leonard'],
            'middle_name' => ['text' => 'Horatio'],
            'last_name' => ['text' => 'McCoy'],
        ],
        'nickname' => ['text' => 'Bones', 'phonetic_text' => 'boʊnz'],
        'name_pronunciation' => ['last_name' => 'məˈkɔɪ'],
        'name_display_format' => 'full_first_only_nickname@ns',
    ]);

    expect(PersonNameComposite::fromEntry($entry))->toBe([
        'first_name' => ['text' => 'Leonard'],
        'middle_name' => ['text' => 'Horatio'],
        'last_name' => ['text' => 'McCoy', 'phonetic_text' => 'məˈkɔɪ'],
        'no_nickname' => false,
        'nickname' => ['text' => 'Bones', 'phonetic_text' => 'boʊnz'],
        'display_format' => 'full_first_only_nickname@ns',
    ]);
});

it('keeps the stored value of a field the entry holds nothing for, and reads the fields after it', function (): void {
    $entry = personNameEntry([
        'person_name' => [
            'first_name' => ['text' => 'Leonard'],
            'last_name' => ['text' => 'McCoy'],
            'no_nickname' => false,
            'nickname' => ['text' => 'Bones'],
        ],
        'name_pronunciation' => ['last_name' => 'məˈkɔɪ'],
    ]);

    expect(PersonNameComposite::fromEntry($entry))->toBe([
        'first_name' => ['text' => 'Leonard'],
        'last_name' => ['text' => 'McCoy', 'phonetic_text' => 'məˈkɔɪ'],
        'no_nickname' => false,
        'nickname' => ['text' => 'Bones'],
    ]);
});

it('returns null for an entry with no person name', function (): void {
    expect(PersonNameComposite::fromEntry(personNameEntry(['nickname' => ['text' => 'Bones']])))->toBeNull();
});
