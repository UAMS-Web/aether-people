<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\User;
use UAMSWeb\Aether\People\Contracts\OrganizationDisplayName;
use UAMSWeb\Aether\People\Support\RoleTitleDefinition;
use UAMSWeb\Aether\People\Support\RoleTitleScopeUnit;

/**
 * `RoleTitleScopeUnit`: reading a linked unit id, resolving it to an entry in an
 * allowed collection, naming it, and building the CP picker field (#130).
 *
 * @command  vendor/bin/pest tests/Integration/Support/RoleTitleScopeUnitTest.php
 */
beforeEach(function (): void {
    isolateStacheAndBlueprintsIn('role-title-scope-unit');
    RoleTitleDefinition::forgetInstance();

    Collection::make('organizations')->save();
    Collection::make('pages')->save();
});

afterEach(function (): void {
    RoleTitleDefinition::forgetInstance();
});

it('reads one linked id from a stored or posted value', function (mixed $raw, ?string $expected): void {
    expect(RoleTitleScopeUnit::normalizeId($raw))->toBe($expected);
})->with([
    'a stored id' => ['unit-1', 'unit-1'],
    'a padded id' => ['  unit-1  ', 'unit-1'],
    'a blank id' => ['   ', null],
    'a posted single-item list' => [['unit-1'], 'unit-1'],
    'a posted list with a padded id' => [[' unit-1 '], 'unit-1'],
    'an empty list' => [[], null],
    'a number' => [5, null],
    'nothing' => [null, null],
]);

it('reads the linked id from a scope payload', function (mixed $scope, ?string $expected): void {
    expect(RoleTitleScopeUnit::idFromScope($scope))->toBe($expected);
})->with([
    'a linked scope' => [['scope_unit' => ['unit-1']], 'unit-1'],
    'an unlinked scope' => [['scope_name' => 'Medicine'], null],
    'a payload that is not an array' => ['unit-1', null],
]);

it('resolves a link only to an entry in an allowed collection', function (): void {
    roleTitleScopeUnitEntry('unit-1', 'organizations');
    roleTitleScopeUnitEntry('page-1', 'pages');

    expect(RoleTitleScopeUnit::entry('unit-1')?->id())->toBe('unit-1')
        ->and(RoleTitleScopeUnit::entry('page-1'))->toBeNull()
        ->and(RoleTitleScopeUnit::entry('missing'))->toBeNull()
        ->and(RoleTitleScopeUnit::entry(null))->toBeNull();
});

it('names a unit by its formal name before its public name', function (): void {
    app()->instance(OrganizationDisplayName::class, new class implements OrganizationDisplayName
    {
        public function displayName(Entry $entry): string
        {
            return 'Public '.mustBeString($entry->id(), 'entry id');
        }
    });

    roleTitleScopeUnitEntry('formal', 'organizations', ['formal_name' => '  UAMS College of Medicine  ']);
    roleTitleScopeUnitEntry('blank formal', 'organizations', ['formal_name' => '   ']);
    roleTitleScopeUnitEntry('no formal', 'organizations');

    expect(RoleTitleScopeUnit::displayName('formal'))->toBe('UAMS College of Medicine')
        ->and(RoleTitleScopeUnit::displayName('blank formal'))->toBe('Public blank formal')
        ->and(RoleTitleScopeUnit::displayName('no formal'))->toBe('Public no formal')
        ->and(RoleTitleScopeUnit::displayName('missing'))->toBeNull();
});

it('has only the formal name when the host binds no public name', function (): void {
    roleTitleScopeUnitEntry('formal', 'organizations', ['formal_name' => 'College of Medicine']);
    roleTitleScopeUnitEntry('no formal', 'organizations');

    expect(app()->bound(OrganizationDisplayName::class))->toBeFalse()
        ->and(RoleTitleScopeUnit::displayName('formal'))->toBe('College of Medicine')
        ->and(RoleTitleScopeUnit::displayName('no formal'))->toBeNull();
});

it('reads the formal name trimmed, or null when it is empty or not text', function (mixed $formalName, ?string $expected): void {
    $entry = roleTitleScopeUnitEntry('unit', 'organizations', ['formal_name' => $formalName]);

    expect(RoleTitleScopeUnit::formalName($entry))->toBe($expected);
})->with([
    'a padded name' => ['  College of Medicine  ', 'College of Medicine'],
    'a blank name' => ['   ', null],
    'a number' => [5, null],
]);

it('allows only the string collection handles the field config lists', function (mixed $collections, array $expected): void {
    config()->set('role_title.subfields.scope.fields.scope_unit.field.collections', $collections);
    RoleTitleDefinition::forgetInstance();

    expect(RoleTitleScopeUnit::collections())->toBe($expected);
})->with([
    'handles with a number among them' => [['organizations', 5, 'departments'], ['organizations', 'departments']],
    'a list that is not an array' => ['organizations', []],
]);

it('builds an entries field config from the nested row', function (): void {
    expect(RoleTitleScopeUnit::fieldConfig())->toMatchArray(['type' => 'entries', 'collections' => ['organizations'], 'max_items' => 1]);

    config()->set('role_title.subfields.scope.fields.scope_unit.field', 'not a map');
    RoleTitleDefinition::forgetInstance();

    expect(RoleTitleScopeUnit::fieldConfig())->toBe(['type' => 'entries']);
});

it('builds the picker field holding the linked id as a list', function (): void {
    $linked = RoleTitleScopeUnit::field('unit-1');
    $unlinked = RoleTitleScopeUnit::field(null);

    expect($linked->handle())->toBe('scope_unit')
        ->and($linked->type())->toBe('entries')
        ->and($linked->value())->toBe(['unit-1'])
        ->and($unlinked->value())->toBe([]);
});

it('preloads picker meta only for a signed-in user', function (): void {
    roleTitleScopeUnitEntry('unit-1', 'organizations', ['title' => 'College of Medicine']);

    expect(RoleTitleScopeUnit::preloadMeta('unit-1'))->toBe([]);

    $user = User::make();
    Assert::assertInstanceOf(FileStatamicUser::class, $user);
    $user->id('scope-unit-user');
    $user->email('scope-unit@example.com');
    $user->makeSuper();
    $user->save();
    $this->actingAs($user);

    $meta = RoleTitleScopeUnit::preloadMeta('unit-1');

    expect($meta)->not->toBe([])
        ->and(data_get($meta, 'data.0.id'))->toBe('unit-1');
});
