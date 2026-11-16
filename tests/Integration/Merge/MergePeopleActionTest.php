<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use UAMSWeb\Aether\People\Actions\MergePeople;
use UAMSWeb\Aether\People\Merge\PeopleMergeService;

/**
 * Behavioral coverage for the `MergePeople` bulk action (UAMS-Web/uams-statamic-archive#838): it is offered
 * only for two-or-more `people` entries, gated by the merge permission, and
 * redirects to the reconciliation screen carrying the selected ids.
 *
 * @command  vendor/bin/pest tests/Integration/Merge/MergePeopleActionTest.php
 */
uses(MockeryPHPUnitIntegration::class);

// Explicitly define the target class for this file
covers(MergePeople::class);

const MERGE_ACTION_ARTICLES = 'merge_action_articles';

beforeEach(function (): void {
    isolateStacheAndBlueprintsIn('merge-merge-people-action');

    $people = CollectionFacade::make(PeopleMergeService::peopleCollection());
    $people->title('People');
    $people->save();

    $articles = CollectionFacade::make(MERGE_ACTION_ARTICLES);
    $articles->title('Articles');
    $articles->save();
});

function mergeActionPerson(string $id): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(PeopleMergeService::peopleCollection());
    $entry->id($id);
    $entry->slug($id);
    $entry->saveQuietly();

    return $entry;
}

function mergeActionArticle(string $id): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(MERGE_ACTION_ARTICLES);
    $entry->id($id);
    $entry->slug($id);
    $entry->saveQuietly();

    return $entry;
}

it('is visible only for individual people entries', function (): void {
    $action = new MergePeople;

    expect($action->visibleTo(mergeActionPerson('p1')))->toBeTrue()
        ->and($action->visibleTo(mergeActionArticle('a1')))->toBeFalse()
        ->and($action->visibleTo('not-an-entry'))->toBeFalse();
});

it('is visible in bulk only for two or more people entries', function (): void {
    $action = new MergePeople;

    $p1 = mergeActionPerson('p1');
    $p2 = mergeActionPerson('p2');
    $article = mergeActionArticle('a1');

    /** @var Collection<int, mixed> $single */
    $single = collect([$p1]);
    /** @var Collection<int, mixed> $pair */
    $pair = collect([$p1, $p2]);
    /** @var Collection<int, mixed> $mixedSelection */
    $mixedSelection = collect([$p1, $article]);

    expect($action->visibleToBulk($single))->toBeFalse()
        ->and($action->visibleToBulk($pair))->toBeTrue()
        // A non-people entry in the selection hides the action.
        ->and($action->visibleToBulk($mixedSelection))->toBeFalse();
});

it('authorizes only users holding the merge permission', function (): void {
    $action = new MergePeople;

    /** @var UserContract&Authorizable&MockInterface $allowed */
    $allowed = Mockery::mock(UserContract::class, Authorizable::class);
    $allowed->shouldReceive('can')->with('merge people entries')->andReturn(true);
    // A super user is outside the people editing scope (UAMS-Web/uams-statamic-archive#3197), whose own
    // cases are in ScopedPeopleMergeTest.
    $allowed->shouldReceive('isSuper')->andReturn(true);

    /** @var UserContract&Authorizable&MockInterface $denied */
    $denied = Mockery::mock(UserContract::class, Authorizable::class);
    $denied->shouldReceive('can')->with('merge people entries')->andReturn(false);

    expect($action->authorize($allowed, mergeActionPerson('p1')))->toBeTrue()
        ->and($action->authorize($denied, mergeActionPerson('p2')))->toBeFalse()
        ->and($action->authorize(null, mergeActionPerson('p3')))->toBeFalse();
});

it('redirects to the reconciliation screen with the selected ids', function (): void {
    $action = new MergePeople;

    $url = $action->redirect(collect([mergeActionPerson('alpha'), mergeActionPerson('beta')]), []);

    expect($url)->toContain('people/merge')
        ->and($url)->toContain('alpha')
        ->and($url)->toContain('beta')
        ->and($url)->not->toBe('');
});

it('does not confirm in a modal (confirmation happens on the screen)', function (): void {
    expect((new MergePeople)->toArray()['confirm'])->toBeFalse();
});

it('exposes the merge title and full-page navigation flags', function (): void {
    $action = new MergePeople;

    expect(MergePeople::title())->toBe('Merge duplicates…')
        ->and($action->triggersFullPageRefresh())->toBeTrue()
        ->and($action->bypassesDirtyWarning())->toBeTrue();
});
