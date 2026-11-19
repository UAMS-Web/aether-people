<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use UAMSWeb\Aether\People\Contracts\OrganizationDisplayName;
use UAMSWeb\Aether\People\Support\RoleTitleValue;
use UAMSWeb\Aether\People\Tests\Fixtures\HostStandIns\OrganizationDisplayNameStandIn;

/**
 * Object/scope exclusivity and the faculty scope slot (UAMS-Web/uams-statamic-archive#2145, UAMS-Web/uams-statamic-archive#2546).
 *
 * An object names an organizational unit generically (`department chair`) and a
 * scope can name the same unit specifically (`of the Department of Internal
 * Medicine`). The two must not both appear. A scope naming a *discipline*
 * (`of endocrinology`) names no unit and composes beside an object freely.
 *
 * The discriminator is whether the scope LINKS a unit (UAMS-Web/uams-statamic-archive#2546). A typed scope names
 * no unit, whatever its article or the capitalization of its name -- which is what
 * the casing and article tests below pin.
 *
 * @command  php artisan test --compact tests/Integration/Support/RoleTitleScopeExclusivityTest.php
 */

/**
 * @param  array<string, mixed>|null  $scope
 */
function composeAcademic(?string $object, ?array $scope, string $role = 'chair'): string
{
    return RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_ACADEMIC_LEADERSHIP,
        'title_options' => [],
        'hierarchical_modifier' => null,
        'object' => $object,
        'positional_qualifier' => null,
        'role' => $role,
        'scope' => $scope,
    ]);
}

/**
 * @param  array<string, mixed>|null  $scope
 */
function composeClinical(?string $object, ?array $scope, string $role = 'medical_director'): string
{
    return RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_CLINICAL_LEADERSHIP,
        'title_options' => [],
        'hierarchical_modifier' => null,
        'object' => $object,
        'role' => $role,
        'scope' => $scope,
    ]);
}

/**
 * A typed scope: a preposition, a name, and optionally the article.
 *
 * @return array<string, string|null>
 */
function scopeOf(string $joiner, string $name, ?string $article = null): array
{
    return ['scope_joiner' => $joiner, 'scope_article' => $article, 'scope_name' => $name];
}

/**
 * A scope linking the unit served by {@see serveExclusivityUnit()}.
 *
 * @return array<string, string|null>
 */
function linkedScopeOf(string $joiner): array
{
    return ['scope_joiner' => $joiner, 'scope_article' => null, 'scope_unit' => 'unit-internal-medicine', 'scope_name' => ''];
}

/**
 * Serve one `organizations` unit named `Department of Internal Medicine`.
 */
function serveExclusivityUnit(): void
{
    app()->bind(OrganizationDisplayName::class, OrganizationDisplayNameStandIn::class);

    $unit = new class extends Entry
    {
        public function __construct() {}

        /**
         * @param  string  $key
         * @return mixed
         */
        public function value($key)
        {
            return $key === 'public_label' ? 'Department of Internal Medicine' : null;
        }

        /**
         * @param  string|null  $id
         * @return string
         */
        public function id($id = null)
        {
            return 'unit-internal-medicine';
        }

        public function collectionHandle(): string
        {
            return 'organizations';
        }
    };

    EntryFacade::partialMock()
        ->shouldReceive('find')
        ->andReturnUsing(static fn (string $id): ?Entry => $id === 'unit-internal-medicine' ? $unit : null);
}

// ---
// The predicate itself
// ---

it('reports a linked scope as naming a unit', function (string $joiner): void {
    serveExclusivityUnit();

    expect(RoleTitleValue::scopeNamesAUnit(linkedScopeOf($joiner)))->toBeTrue();
})->with(['of', 'for', 'in']);

it('reports a typed scope as naming no unit, with or without the article', function (string $joiner, ?string $article): void {
    expect(RoleTitleValue::scopeNamesAUnit(scopeOf($joiner, 'Department of Internal Medicine', $article)))->toBeFalse();
})->with([
    ['of', null],
    ['of', 'the'],
    ['for', 'the'],
    ['in', 'the'],
]);

it('reports no unit for a scope with no name', function (): void {
    expect(RoleTitleValue::scopeNamesAUnit(scopeOf('of', '', 'the')))->toBeFalse();
    expect(RoleTitleValue::scopeNamesAUnit(scopeOf('of', '   ', 'the')))->toBeFalse();
});

it('reports no unit for a link that does not resolve', function (): void {
    serveExclusivityUnit();

    expect(RoleTitleValue::scopeNamesAUnit(['scope_joiner' => 'of', 'scope_unit' => 'missing-unit', 'scope_name' => '']))->toBeFalse();
});

it('reports no unit for a payload that is not an array', function (): void {
    expect(RoleTitleValue::scopeNamesAUnit(null))->toBeFalse();
    expect(RoleTitleValue::scopeNamesAUnit('of the Department'))->toBeFalse();
});

// ---
// Academic leadership
// ---

it('drops the academic object when the scope links a unit', function (string $joiner, string $expected): void {
    serveExclusivityUnit();

    expect(composeAcademic('department', linkedScopeOf($joiner)))->toBe($expected);
})->with([
    ['of', 'chair of the Department of Internal Medicine'],
    ['for', 'chair for the Department of Internal Medicine'],
    ['in', 'chair in the Department of Internal Medicine'],
]);

it('keeps the academic object beside a discipline scope', function (): void {
    expect(composeAcademic('section', scopeOf('of', 'endocrinology')))
        ->toBe('section chair of endocrinology');
});

it('keeps the academic object beside a typed scope that takes the article', function (): void {
    // A typed scope names no unit, so its article decides only the phrase
    expect(composeAcademic('department', scopeOf('for', 'research enterprise', 'the')))
        ->toBe('department chair for the research enterprise');
});

// ---
// Clinical leadership: UAMS-Web/uams-statamic-archive#2143 narrowed from any scope to a unit-naming scope
// ---

it('drops the clinical object when the scope links a unit', function (): void {
    serveExclusivityUnit();

    expect(composeClinical('service_line', linkedScopeOf('of')))
        ->toBe('medical director of the Department of Internal Medicine');
});

it('keeps the clinical object beside a discipline scope, which #2143 used to drop', function (): void {
    expect(composeClinical('service_line', scopeOf('of', 'breast surgery')))
        ->toBe('service line medical director of breast surgery');
});

// ---
// Casing is an authoring convention and changes no verdict
// ---

it('reads the link rather than the casing of a typed name', function (): void {
    $upper = composeAcademic('department', scopeOf('of', 'Department of Internal Medicine', 'the'));
    $lower = composeAcademic('department', scopeOf('of', 'department of internal medicine', 'the'));

    // Neither links a unit, so both keep the object; only the name text differs
    expect($upper)->toBe('department chair of the Department of Internal Medicine');
    expect($lower)->toBe('department chair of the department of internal medicine');
});

it('does not treat a capitalized discipline as a unit', function (): void {
    // Capitalizing a discipline must not start suppressing the object
    expect(composeAcademic('section', scopeOf('of', 'Endocrinology')))
        ->toBe('section chair of Endocrinology');
});

// ---
// Faculty carries a scope, composed after the honorific
// ---

it('composes a faculty scope after the honorific', function (): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'honorific' => 'emeritus',
        'scope_qualifier' => null,
        'hierarchical_modifier' => null,
        'role' => 'professor',
        'scope' => scopeOf('of', 'medicine'),
    ]);

    expect($out)->toBe('professor emeritus of medicine');
});

it('composes each preposition typed with and without the article', function (string $joiner, ?string $article, string $expected): void {
    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'title_options' => [],
        'scope_qualifier' => null,
        'hierarchical_modifier' => null,
        'role' => 'professor',
        'scope' => scopeOf($joiner, 'Department of Internal Medicine', $article),
    ]);

    expect($out)->toBe($expected);
})->with([
    ['of', 'the', 'professor of the Department of Internal Medicine'],
    ['for', 'the', 'professor for the Department of Internal Medicine'],
    ['in', 'the', 'professor in the Department of Internal Medicine'],
    ['of', null, 'professor of Department of Internal Medicine'],
    ['for', null, 'professor for Department of Internal Medicine'],
    // Validation refuses this pairing; composition still reads it plainly
    ['in', null, 'professor in Department of Internal Medicine'],
]);

it('composes each preposition linked with the article, whatever the stored article says', function (string $joiner, ?string $article, string $expected): void {
    serveExclusivityUnit();

    $scope = ['scope_article' => $article] + linkedScopeOf($joiner);

    $out = RoleTitleValue::compose([
        'configuration' => RoleTitleValue::CONFIGURATION_FACULTY,
        'title_options' => [],
        'scope_qualifier' => null,
        'hierarchical_modifier' => null,
        'role' => 'professor',
        'scope' => $scope,
    ]);

    expect($out)->toBe($expected);
})->with([
    ['of', null, 'professor of the Department of Internal Medicine'],
    ['for', null, 'professor for the Department of Internal Medicine'],
    ['in', null, 'professor in the Department of Internal Medicine'],
    ['of', 'the', 'professor of the Department of Internal Medicine'],
]);

// ---
// A title with no scope is unchanged in every configuration
// ---

it('composes the academic object unchanged when there is no scope', function (?array $scope): void {
    /** @var array<string, mixed>|null $scope */
    expect(composeAcademic('department', $scope))->toBe('department chair');
})->with([
    'null scope' => [null],
    'blank name' => [['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_name' => '']],
    'whitespace name' => [['scope_joiner' => 'in', 'scope_article' => 'the', 'scope_name' => '   ']],
]);

it('composes the clinical object unchanged when there is no scope', function (?array $scope): void {
    /** @var array<string, mixed>|null $scope */
    expect(composeClinical('service_line', $scope))->toBe('service line medical director');
})->with([
    'null scope' => [null],
    'blank name' => [['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_name' => '']],
]);

it('no longer preloads a unit-naming joiner list for the Control Panel', function (): void {
    // Joiners are prepositions now (UAMS-Web/uams-statamic-archive#2546), so there is no list to narrow by
    expect(RoleTitleValue::fieldtypePreloadMetaFromDefinition())->not->toHaveKey('unit_naming_scope_joiners');
});

it('ignores a scope name that is not a string', function (mixed $name): void {
    // A non-string name must compose nothing rather than reaching the phrase
    // template, which would render whatever the payload happened to carry
    $scope = ['scope_joiner' => 'of', 'scope_article' => 'the', 'scope_name' => $name];

    expect(RoleTitleValue::scopeNamesAUnit($scope))->toBeFalse();
    expect(composeAcademic('department', $scope))->toBe('department chair');
})->with([
    'integer' => [123],
    'array' => [['Department of Internal Medicine']],
    'null' => [null],
    'boolean' => [true],
]);

it('treats an article other than the as none', function (mixed $article): void {
    expect(composeAcademic('section', ['scope_joiner' => 'of', 'scope_article' => $article, 'scope_name' => 'endocrinology']))
        ->toBe('section chair of endocrinology');
})->with([
    'a' => ['a'],
    'blank' => [''],
    'integer' => [1],
]);
