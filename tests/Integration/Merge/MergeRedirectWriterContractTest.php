<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as EntryClass;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use UAMSWeb\Aether\People\Contracts\MergeRedirectWriter;
use UAMSWeb\Aether\People\Merge\MergeResult;
use UAMSWeb\Aether\People\Merge\PeopleMergeException;
use UAMSWeb\Aether\People\Merge\PeopleMergeService;
use UAMSWeb\Aether\People\Merge\RedirectsGlobalWriter;
use UAMSWeb\Aether\People\Tests\Support\HostFakes;

uses(MockeryPHPUnitIntegration::class);

covers(PeopleMergeService::class);

/**
 * The people merge reaches redirects only through the host-bound {@see MergeRedirectWriter}
 * (UAMS-Web/aether-people#100): the preview and the commit report the same count, a merge with no
 * binding is refused before anything changes, and a writer that writes nothing lets the merge complete.
 *
 * @command vendor/bin/pest tests/Integration/Merge/MergeRedirectWriterContractTest.php
 */

/**
 * A writer that records the plans it is handed and reports a fixed count.
 *
 * @return object{counted: list<list<array<string, string>>>, written: list<list<array<string, string>>>}&MergeRedirectWriter
 */
function mrwRecordingWriter(int $reports): MergeRedirectWriter
{
    return new class($reports) implements MergeRedirectWriter
    {
        /** @var list<list<array<string, string>>> */
        public array $counted = [];

        /** @var list<list<array<string, string>>> */
        public array $written = [];

        public function __construct(private readonly int $reports) {}

        public function count(array $plan): int
        {
            $this->counted[] = $plan;

            return $this->reports;
        }

        public function write(array $plan): int
        {
            $this->written[] = $plan;

            return $this->reports;
        }
    };
}

function mrwPerson(string $id, string $slug): EntryClass
{
    /** @var EntryClass $entry */
    $entry = Entry::make();
    $entry->collection(PeopleMergeService::peopleCollection());
    $entry->id($id);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['bio' => 'Bio of '.$slug]);
    $entry->locale('default');
    $entry->saveQuietly();

    return $entry;
}

function mrwService(?MergeRedirectWriter $writer): PeopleMergeService
{
    return new PeopleMergeService(HostFakes::entryReferenceRepointer(), $writer);
}

beforeEach(function (): void {
    isolateStacheAndBlueprintsIn('merge-redirect-writer-contract');

    config(['statamic.system.multisite' => true, 'statamic.editions.pro' => true]);
    Site::setSites([
        'default' => ['name' => 'Default', 'locale' => 'en_US', 'url' => '/'],
        'health' => ['name' => 'Health', 'locale' => 'en_US', 'url' => '/health/'],
    ]);

    $people = CollectionFacade::make(PeopleMergeService::peopleCollection());
    $people->title('People');
    $people->sites(['default', 'health']);
    $people->routes(['default' => '/people/{slug}', 'health' => '/people/{slug}']);
    $people->save();

    $blueprint = Blueprint::makeFromFields(['bio' => ['type' => 'text']]);
    $blueprint->setHandle('person');
    $blueprint->setNamespace('collections.'.PeopleMergeService::peopleCollection());
    $blueprint->save();

    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::make('redirects');
    $set->title('Redirects');
    $set->sites(['default' => null, 'health' => null]);
    $set->save();

    /** @var Variables $vars */
    $vars = $set->makeLocalization('default');
    $vars->data(['redirects' => []]);
    $vars->save();
});

it('reports the writer count for the preview and for the commit, from the same plan', function (): void {
    mrwPerson('surv', 'jane');
    mrwPerson('lose', 'old-jane');
    $writer = mrwRecordingWriter(7);

    $preview = mrwService($writer)->preview(['surv', 'lose'], 'surv', []);
    $result = mrwService($writer)->merge(['surv', 'lose'], 'surv', []);

    expect($preview->redirectsCreated)->toBe(7)
        ->and($result->redirectsCreated)->toBe(7)
        ->and($preview->redirects)->toBe([['site' => 'default', 'source' => '/people/old-jane', 'target' => '/people/jane', 'response' => '301']])
        ->and($writer->counted)->toBe([$preview->redirects])
        ->and($writer->written)->toBe([$result->redirects]);
});

it('refuses a preview on a host that has not bound a writer, naming the binding', function (): void {
    mrwPerson('surv', 'jane');
    mrwPerson('lose', 'old-jane');

    expect(fn (): MergeResult => mrwService(null)->preview(['surv', 'lose'], 'surv', []))
        ->toThrow(PeopleMergeException::class, MergeRedirectWriter::class);
});

it('refuses a commit on a host that has not bound a writer and changes no entry', function (): void {
    mrwPerson('surv', 'jane');
    mrwPerson('lose', 'old-jane');

    expect(fn (): MergeResult => mrwService(null)->merge(['surv', 'lose'], 'surv', ['bio' => 'lose']))
        ->toThrow(PeopleMergeException::class, MergeRedirectWriter::class);

    /** @var EntryClass $survivor */
    $survivor = Entry::find('surv');

    expect(Entry::find('lose'))->not->toBeNull()
        ->and($survivor->get('bio'))->toBe('Bio of jane');
});

it('refuses on an unbound host even when there is nothing to redirect', function (): void {
    expect(fn (): MergeResult => mrwService(null)->merge(['surv', 'lose'], 'surv', []))
        ->toThrow(
            PeopleMergeException::class,
            'People merge cannot run: no redirect writer is bound. Bind UAMSWeb\Aether\People\Contracts\MergeRedirectWriter'
            .' in the host (a writer that writes nothing is a valid binding) and try again.',
        );
});

it('completes a merge with a writer that writes nothing', function (): void {
    mrwPerson('surv', 'jane');
    mrwPerson('lose', 'old-jane');

    $none = new class implements MergeRedirectWriter
    {
        public function count(array $plan): int
        {
            return 0;
        }

        public function write(array $plan): int
        {
            return 0;
        }
    };

    $result = mrwService($none)->merge(['surv', 'lose'], 'surv', []);

    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');
    /** @var Variables $variables */
    $variables = $set->in('default');

    expect($result->redirectsCreated)->toBe(0)
        ->and(Entry::find('lose'))->toBeNull()
        ->and($variables->get('redirects'))->toBe([]);
});

it('previews the same count the global writer then writes', function (): void {
    mrwPerson('surv', 'jane');
    mrwPerson('lose', 'old-jane');
    $service = mrwService(new RedirectsGlobalWriter);

    $preview = $service->preview(['surv', 'lose'], 'surv', []);
    $result = $service->merge(['surv', 'lose'], 'surv', []);

    expect($preview->redirectsCreated)->toBe(1)
        ->and($result->redirectsCreated)->toBe($preview->redirectsCreated);
});

it('previews zero redirects when the global writer has no redirects set, as the commit writes none', function (): void {
    mrwPerson('surv', 'jane');
    mrwPerson('lose', 'old-jane');

    /** @var Statamic\Globals\GlobalSet|null $set */
    $set = GlobalSet::findByHandle('redirects');
    $set?->delete();

    Log::partialMock()->shouldReceive('warning')->twice();

    $service = mrwService(new RedirectsGlobalWriter);
    $preview = $service->preview(['surv', 'lose'], 'surv', []);
    $result = $service->merge(['surv', 'lose'], 'surv', []);

    expect($preview->redirectsCreated)->toBe(0)
        ->and($result->redirectsCreated)->toBe(0);
});

it('previews the number of rows the commit then adds to the redirects global, across sites and past an existing source', function (): void {
    mrwPerson('surv', 'jane');
    $loser = mrwPerson('lose', 'old-jane');
    /** @var EntryClass $healthLoser */
    $healthLoser = $loser->makeLocalization('health');
    $healthLoser->slug('old-jane-health');
    $healthLoser->published(true);
    $healthLoser->saveQuietly();

    /** @var Statamic\Globals\GlobalSet $set */
    $set = GlobalSet::findByHandle('redirects');

    // One of the two planned sources already redirects, so only the other may be counted and written
    $existing = [['id' => 'seed', 'source_type' => 'url', 'source' => '/people/old-jane', 'target_type' => 'url', 'target' => '/people/jane', 'response' => '301', 'enabled' => true]];
    /** @var Variables $default */
    $default = $set->in('default');
    $default->data(['redirects' => $existing]);
    $default->save();

    /** @var Variables $health */
    $health = $set->makeLocalization('health');
    $health->data(['redirects' => []]);
    $health->save();

    $storedRows = static function (): int {
        /** @var Statamic\Globals\GlobalSet $redirects */
        $redirects = GlobalSet::findByHandle('redirects');

        return collect(['default', 'health'])
            ->sum(static function (string $site) use ($redirects): int {
                /** @var Variables $variables */
                $variables = $redirects->in($site);

                /** @var list<array<string, mixed>> $rows */
                $rows = $variables->get('redirects', []);

                return \count($rows);
            });
    };

    $service = mrwService(new RedirectsGlobalWriter);
    $before = $storedRows();

    $preview = $service->preview(['surv', 'lose'], 'surv', []);
    $afterPreview = $storedRows();

    $result = $service->merge(['surv', 'lose'], 'surv', []);
    $added = $storedRows() - $before;

    expect($preview->redirectsCreated)->toBe(1)
        ->and($afterPreview)->toBe($before)
        ->and($added)->toBe($preview->redirectsCreated)
        ->and($result->redirectsCreated)->toBe($added);
});
