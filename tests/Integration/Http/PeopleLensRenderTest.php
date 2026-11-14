<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UAMSWeb\Aether\People\Http\Controllers\PeopleLensController;
use UAMSWeb\Aether\People\Lenses\LensGate;

covers(PeopleLensController::class);

/**
 * The lens controller end to end over a probe lens: it renders a gated, published person
 * through the lens template and hands every other request to the front end, which has no
 * page there and answers 404 (UAMS-Web/uams-statamic-archive#1635 and UAMS-Web/uams-statamic-archive#1636).
 *
 * @command vendor/bin/pest tests/Integration/Http/PeopleLensRenderTest.php
 */
beforeEach(function (): void {
    // The probe lens's template and the layout live in the fixtures directory
    app('view')->addLocation(dirname(__DIR__, 2).'/Fixtures/views');
    Collection::make('probe_people')->save();
    config()->set('people_lenses.lenses', [
        'probe' => ['template' => 'people/probe/show', 'route' => 'probe/{slug}', 'gate' => LensGate::class, 'sites' => ['*']],
    ]);
});

/**
 * Make the canonical entry lookup answer with a person (or none).
 */
function lensPersonFound(?Entry $person): void
{
    $query = Mockery::mock();
    $query->shouldReceive('where')->andReturnSelf();
    $query->shouldReceive('first')->andReturn($person);
    EntryFacade::partialMock()->shouldReceive('query')->andReturn($query);
}

/**
 * A published `people` entry with the given title.
 *
 * @param  array<string, mixed>  $data
 */
function lensPerson(array $data = []): Entry
{
    $entry = new Entry;
    // A collection of its own, so the person blueprint and the host fieldsets it imports stay out of the render
    $entry->collection('probe_people');
    $entry->id('p1');
    $entry->slug('spock');
    $entry->locale('default');
    $entry->published(true);
    $entry->data(['title' => 'Spock'] + $data);

    return $entry;
}

/**
 * Make the gate answer.
 */
function lensGateAnswers(bool $passes): void
{
    $gate = Mockery::mock(LensGate::class);
    $gate->shouldReceive('passes')->andReturn($passes);
    app()->instance(LensGate::class, $gate);
}

/**
 * A request for the probe lens, carrying its handle as the route default the provider sets.
 */
function lensRequest(?string $lens = 'probe'): Request
{
    $request = Request::create('http://localhost/probe/spock');
    $route = new Route('GET', 'probe/{slug}', []);

    if ($lens !== null) {
        $route->defaults('lens', $lens);
    }

    $request->setRouteResolver(static fn (): Route => $route);

    return $request;
}

it('renders a gated, published person through the lens template with the lens URL as the permalink', function (): void {
    lensPersonFound(lensPerson());
    lensGateAnswers(true);

    $response = mustBe(Response::class, app(PeopleLensController::class)(lensRequest(), 'spock'), 'the lens response');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('text/html; charset=UTF-8')
        ->and($response->getContent())->toBe('<html><p>Spock|http://localhost/probe/spock</p></html>');
});

it('hands the request to the front end, which answers 404, when the lens does not apply', function (string $case): void {
    $person = match ($case) {
        'no person' => null,
        'draft person' => tap(lensPerson(), fn (Entry $entry) => $entry->published(false)),
        'private person' => lensPerson(['status_and_visibility' => 'private']),
        default => lensPerson(),
    };
    lensPersonFound($person);
    lensGateAnswers($case !== 'gate refuses');
    $request = lensRequest($case === 'unknown lens' ? 'nope' : ($case === 'no lens handle' ? null : 'probe'));

    expect(fn () => app(PeopleLensController::class)($request, 'spock'))->toThrow(NotFoundHttpException::class);
})->with(['no person', 'draft person', 'private person', 'gate refuses', 'unknown lens', 'no lens handle']);

it('treats a request with no matched route as no lens', function (): void {
    lensPersonFound(lensPerson());
    lensGateAnswers(true);
    $request = Request::create('http://localhost/probe/spock');

    expect(fn () => app(PeopleLensController::class)($request, 'spock'))->toThrow(NotFoundHttpException::class);
});

it('treats a lens default that is not a string as no lens', function (): void {
    lensPersonFound(lensPerson());
    lensGateAnswers(true);
    $request = Request::create('http://localhost/probe/spock');
    $route = new Route('GET', 'probe/{slug}', []);
    $route->defaults('lens', ['probe']);

    $request->setRouteResolver(static fn (): Route => $route);

    expect(fn () => app(PeopleLensController::class)($request, 'spock'))->toThrow(NotFoundHttpException::class);
});
