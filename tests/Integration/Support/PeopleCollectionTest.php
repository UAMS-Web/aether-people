<?php

declare(strict_types=1);

use UAMSWeb\Aether\People\Support\PeopleCollection;

/**
 * Tests for PeopleCollection, the configured handle of the `people` collection.
 *
 * @command  vendor/bin/pest tests/Integration/Support/PeopleCollectionTest.php
 */
covers(PeopleCollection::class);

it('reads the people collection handle from the add-on config', function (): void {
    expect(PeopleCollection::handle())->toBe('people');
});

it('reads a handle the host has configured', function (): void {
    // Configure a different handle, as a host's own config/people.php would
    config(['people.collection' => 'staff']);

    expect(PeopleCollection::handle())->toBe('staff');
});
