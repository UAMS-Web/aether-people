<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use UAMSWeb\Aether\People\Contracts\OrganizationDisplayName;
use UAMSWeb\Aether\People\Rules\RoleTitleScopeJoinerRule;
use UAMSWeb\Aether\People\Rules\RoleTitleScopeUnitRule;
use UAMSWeb\Aether\People\Support\RoleTitleDefinition;

/**
 * `RoleTitleScopeJoinerRule` and `RoleTitleScopeUnitRule` run through Laravel's
 * validator, so each reads its siblings from the data bag the way a CP save
 * does (#130).
 *
 * @command  vendor/bin/pest tests/Integration/Rules/RoleTitleScopeRulesTest.php
 */
beforeEach(function (): void {
    RoleTitleDefinition::forgetInstance();
});

afterEach(function (): void {
    RoleTitleDefinition::forgetInstance();
});

/**
 * The messages a rule reports for one attribute of a data bag.
 *
 * @param  array<string, mixed>  $data  Data bag
 * @return list<string>
 */
function roleTitleScopeRuleMessages(
    array $data,
    string $attribute,
    object $rule
): array {
    /** @var list<string> $messages */
    $messages = Validator::make($data, [$attribute => [$rule]])->errors()->get($attribute);

    return $messages;
}

/**
 * The translated message for a `role_title.validation` key.
 */
function roleTitleScopeRuleMessage(
    string $key
): string {
    return __('aether-people::fieldtypes.role_title.validation.'.$key);
}

it('requires the article for a typed scope joined by in', function (array $scope, array $expected): void {
    $data = ['rows' => [['title' => ['scope' => $scope]]]];
    /** @var list<string> $expected */
    expect(roleTitleScopeRuleMessages($data, 'rows.0.title.scope.scope_joiner', new RoleTitleScopeJoinerRule('rows.0.title')))
        ->toBe(array_map(roleTitleScopeRuleMessage(...), $expected));
})->with([
    'a typed scope without the article' => [['scope_joiner' => 'in', 'scope_name' => 'Medicine'], ['scope_joiner_requires_article']],
    'a padded in joiner' => [['scope_joiner' => ' in ', 'scope_name' => 'Medicine'], ['scope_joiner_requires_article']],
    'a typed scope with another article' => [['scope_joiner' => 'in', 'scope_name' => 'Medicine', 'scope_article' => 'a'], ['scope_joiner_requires_article']],
    'a typed scope with an article that is not text' => [['scope_joiner' => 'in', 'scope_name' => 'Medicine', 'scope_article' => 5], ['scope_joiner_requires_article']],
    'a blank unit link' => [['scope_joiner' => 'in', 'scope_name' => 'Medicine', 'scope_unit' => '  '], ['scope_joiner_requires_article']],
    'a typed scope with the article' => [['scope_joiner' => 'in', 'scope_name' => 'Medicine', 'scope_article' => 'the'], []],
    'a padded article' => [['scope_joiner' => 'in', 'scope_name' => 'Medicine', 'scope_article' => ' the '], []],
    'a linked unit' => [['scope_joiner' => 'in', 'scope_name' => 'Medicine', 'scope_unit' => ['unit-1']], []],
    'no typed name' => [['scope_joiner' => 'in'], []],
    'a blank typed name' => [['scope_joiner' => 'in', 'scope_name' => '   '], []],
    'a typed name that is not text' => [['scope_joiner' => 'in', 'scope_name' => 5], []],
    'a joiner that does not require the article' => [['scope_joiner' => 'of', 'scope_name' => 'Medicine'], []],
    'a joiner that is not text' => [['scope_joiner' => 5, 'scope_name' => 'Medicine'], []],
]);

it('checks a linked scope unit exists, has a name, stands without an object, and matches the row', function (array $title, ?string $rowUnit, ?string $rowLinkHandle, ?string $expected): void {
    isolateStacheAndBlueprintsIn('role-title-scope-unit-rule');
    Collection::make('organizations')->save();
    Collection::make('pages')->save();
    roleTitleScopeUnitEntry('named', 'organizations', ['formal_name' => 'College of Medicine']);
    roleTitleScopeUnitEntry('other', 'organizations', ['formal_name' => 'College of Nursing']);
    roleTitleScopeUnitEntry('unnamed', 'organizations');
    roleTitleScopeUnitEntry('page', 'pages', ['formal_name' => 'A Page']);

    $data = ['rows' => [['title' => $title, 'unit' => $rowUnit]]];

    expect(roleTitleScopeRuleMessages(
        $data,
        'rows.0.title.scope.scope_unit',
        new RoleTitleScopeUnitRule('rows.0.title', $rowLinkHandle)
    ))->toBe($expected === null ? [] : [roleTitleScopeRuleMessage($expected)]);
})->with([
    'nothing linked' => [['scope' => ['scope_unit' => null]], 'other', 'unit', null],
    'a blank link' => [['scope' => ['scope_unit' => '  ']], 'other', 'unit', null],
    'a missing entry' => [['scope' => ['scope_unit' => 'missing']], null, 'unit', 'scope_unit_not_found'],
    'an entry outside the allowed collections' => [['scope' => ['scope_unit' => 'page']], null, 'unit', 'scope_unit_not_found'],
    'a unit with no name' => [['scope' => ['scope_unit' => 'unnamed']], null, 'unit', 'scope_unit_unnamed'],
    'a unit next to an object' => [['configuration' => 'academic_leadership', 'object' => 'dean', 'scope' => ['scope_unit' => 'named']], null, 'unit', 'scope_unit_object'],
    'a unit other than the row unit' => [['scope' => ['scope_unit' => ['named']]], 'other', 'unit', 'scope_unit_row_mismatch'],
    'the row unit' => [['scope' => ['scope_unit' => ['named']]], 'named', 'unit', null],
    'a row with no linked unit' => [['scope' => ['scope_unit' => 'named']], null, 'unit', null],
    'a row link handle that is not set' => [['scope' => ['scope_unit' => 'named']], 'other', null, null],
    'an empty row link handle' => [['scope' => ['scope_unit' => 'named']], 'other', '', null],
]);

it('reads the row link beside a top-level title', function (): void {
    isolateStacheAndBlueprintsIn('role-title-scope-unit-rule-top');
    Collection::make('organizations')->save();
    roleTitleScopeUnitEntry('named', 'organizations', ['formal_name' => 'College of Medicine']);

    $rule = new RoleTitleScopeUnitRule('title', 'unit');

    expect(roleTitleScopeRuleMessages(['title' => ['scope' => ['scope_unit' => 'named']], 'unit' => 'other'], 'title.scope.scope_unit', $rule))
        ->toBe([roleTitleScopeRuleMessage('scope_unit_row_mismatch')])
        ->and(roleTitleScopeRuleMessages(['title' => ['scope' => ['scope_unit' => 'named']], 'unit' => 'named'], 'title.scope.scope_unit', $rule))
        ->toBe([]);
});

it('names a unit through the host when it has no formal name', function (): void {
    isolateStacheAndBlueprintsIn('role-title-scope-unit-rule-host');
    Collection::make('organizations')->save();
    roleTitleScopeUnitEntry('public', 'organizations');

    app()->instance(OrganizationDisplayName::class, new class implements OrganizationDisplayName
    {
        public function displayName(Entry $entry): string
        {
            return 'Public Name';
        }
    });

    expect(roleTitleScopeRuleMessages(
        ['title' => ['scope' => ['scope_unit' => 'public']]],
        'title.scope.scope_unit',
        new RoleTitleScopeUnitRule('title', null)
    ))->toBe([]);
});
