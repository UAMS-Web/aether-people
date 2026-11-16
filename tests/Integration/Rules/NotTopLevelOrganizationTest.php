<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use UAMSWeb\Aether\People\Rules\NotTopLevelOrganization;

covers(NotTopLevelOrganization::class);

// Save into a directory of this test's own, never the tracked fixtures directory
beforeEach(function (): void {
    $this->notTopLevelDirectory = storage_path('framework/testing/not-top-level/'.uniqid('', true));
    isolateStacheIn($this->notTopLevelDirectory);
});

afterEach(function (): void {
    File::deleteDirectory((string) $this->notTopLevelDirectory);
});

/**
 * Behavior of the `not_top_level_organization` rule: an organization at the top of
 * the `organizations` tree is refused, named by its title (or its id when it has
 * no title), and anything below the top or not a string id passes. The rule
 * stops at the first top-level id it finds. An entry saved into a structured
 * collection joins its tree, so there is no organization outside the tree to test.
 *
 * @command  php artisan test --compact tests/Integration/Rules/NotTopLevelOrganizationTest.php
 */

/**
 * Persist an `organizations` entry, with a title unless one is not given.
 */
function notTopLevelOrganization(string $id, ?string $title): void
{
    /** @var EntryItem $organization */
    $organization = Entry::make();
    $organization->collection('organizations');
    $organization->locale('default');
    $organization->id($id);
    $organization->slug($id);
    $organization->data($title === null ? [] : ['title' => $title]);
    $organization->saveQuietly();
}

/**
 * Two top-level organizations, one with a college below it.
 */
function notTopLevelOrganizationTree(): void
{
    // Create the collection with a structure that has no root entry
    $collection = CollectionFacade::make('organizations');
    $collection->structureContents(['root' => false]);
    $collection->save();

    // Persist the organizations the tree arranges
    notTopLevelOrganization('org-uams', 'UAMS Health');
    notTopLevelOrganization('org-untitled', null);
    notTopLevelOrganization('org-medicine', 'College of Medicine');

    $structure = $collection->structure();
    Assert::assertInstanceOf(CollectionStructure::class, $structure);
    $tree = $structure->in('default');
    Assert::assertInstanceOf(Tree::class, $tree);
    $tree->tree([
        ['entry' => 'org-uams', 'children' => [['entry' => 'org-medicine']]],
        ['entry' => 'org-untitled'],
    ]);
    $tree->save();
}

/**
 * Run the rule and return every message it failed with.
 *
 * @return list<mixed>
 */
function notTopLevelOrganizationMessages(mixed $value): array
{
    $messages = [];

    (new NotTopLevelOrganization)->validate('organizations', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

it('refuses a top-level organization, naming it by its title', function (mixed $value): void {
    notTopLevelOrganizationTree();

    expect(notTopLevelOrganizationMessages($value))->toBe([
        __('aether-people::validation.not_top_level_organization.top_level', ['title' => 'UAMS Health']),
    ]);
})->with([
    'a single id' => ['org-uams'],
    'in a list after one below the top' => [['org-medicine', 'org-uams']],
    'in a list after a non-string' => [[7, 'org-uams']],
]);

it('names a top-level organization without a title by its id', function (): void {
    notTopLevelOrganizationTree();

    expect(notTopLevelOrganizationMessages(['org-untitled']))->toBe([
        __('aether-people::validation.not_top_level_organization.top_level', ['title' => 'org-untitled']),
    ]);
});

it('reports only the first top-level organization in a list', function (): void {
    notTopLevelOrganizationTree();

    expect(notTopLevelOrganizationMessages(['org-untitled', 'org-uams']))->toBe([
        __('aether-people::validation.not_top_level_organization.top_level', ['title' => 'org-untitled']),
    ]);
});

it('accepts organizations below the top, or values that are not string ids', function (mixed $value): void {
    notTopLevelOrganizationTree();

    expect(notTopLevelOrganizationMessages($value))->toBe([]);
})->with([
    'below the top' => ['org-medicine'],
    'an empty list' => [[]],
    'null' => [null],
    'an integer' => [[42]],
]);

it('matches top-level ids strictly', function (): void {
    // A numeric-looking top-level id, which a loose comparison would equate with '1e1'.
    $collection = CollectionFacade::make('organizations');
    $collection->structureContents(['root' => false]);
    $collection->save();
    notTopLevelOrganization('10', 'Numbered Organization');

    $structure = $collection->structure();
    Assert::assertInstanceOf(CollectionStructure::class, $structure);
    $tree = $structure->in('default');
    Assert::assertInstanceOf(Tree::class, $tree);
    $tree->tree([['entry' => '10']]);
    $tree->save();

    expect(notTopLevelOrganizationMessages('1e1'))->toBe([])
        ->and(notTopLevelOrganizationMessages('10'))->toBe([
            __('aether-people::validation.not_top_level_organization.top_level', ['title' => 'Numbered Organization']),
        ]);
});

it('accepts any organization while the collection has no tree', function (): void {
    // Create the collection without a structure
    CollectionFacade::make('organizations')->save();
    notTopLevelOrganization('org-uams', 'UAMS Health');

    expect(notTopLevelOrganizationMessages('org-uams'))->toBe([]);
});
