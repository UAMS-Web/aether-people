<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route;
use Statamic\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Http\Controllers\FrontendController;
use Statamic\Sites\Site as SiteInstance;
use Statamic\View\View;
use UAMSWeb\Aether\People\Lenses\LensDescriptor;
use UAMSWeb\Aether\People\Lenses\LensGate;
use UAMSWeb\Aether\People\Lenses\PeopleLensRegistry;
use UAMSWeb\Aether\People\Support\CanonicalPeopleResolver;

/**
 * Renders a site-specific lens of one canonical `people` entry (UAMS-Web/uams-statamic-archive#1635 / UAMS-Web/uams-statamic-archive#1636).
 *
 * People are stored as a single entry on the canonical site with no per-site
 * localizations, so a lens route on another site (the clinical `/provider/{slug}`
 * on `health`, a department lens on a department site) cannot resolve the person
 * through Statamic's normal site-scoped pipeline. This controller resolves the
 * canonical entry cross-site, applies the lens gate, and renders the lens
 * template through the front-end View pipeline.
 *
 * It bypasses Statamic's DataResponse pipeline, so anything that is NOT a renderable gated lens
 * - an unknown lens, a slug with no canonical entry, a draft/restricted person, or a
 * person the gate rejects - is DELEGATED to the normal front end for the same URL.
 * A true non-provider URL then 404s exactly as it would without this route, while a
 * real page that happens to sit under the lens prefix still renders (no shadowing).
 */
final class PeopleLensController extends Controller
{
    public function __construct(
        private readonly CanonicalPeopleResolver $resolver,
        private readonly PeopleLensRegistry $registry,
    ) {}

    /**
     * Render the lens for `{slug}`, or fall through to the normal front end.
     *
     * The lens handle is carried on the matched route's `defaults` (set at
     * registration), NOT as a route parameter - Laravel fills non-class controller
     * arguments positionally from the route parameters, so a `string $lens` argument
     * would be transposed with `{slug}`. Reading it from the route keeps the binding
     * order-independent.
     *
     * @param  string  $slug  The canonical people slug from the route.
     */
    public function __invoke(Request $request, string $slug): mixed
    {
        $lens = $this->lensHandle($request);

        // The registry holds no lens under an empty handle, so this shortcut is an
        // equivalent of asking it
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $descriptor = $lens === '' ? null : $this->registry->get($lens);
        $person = $this->resolver->resolve($slug);

        if (
            $descriptor instanceof LensDescriptor
            && $person instanceof Entry
            && $this->isPubliclyRenderable($person)
            && $this->passesGate($descriptor, $person)
        ) {
            return $this->renderLens($descriptor, $person, $request);
        }

        // Not a renderable gated lens - behave exactly like the Statamic catch-all
        // for the full URL: render a real page if one exists there, otherwise 404,
        // and never disclose a draft or restricted person.
        return app(FrontendController::class)->index($request);
    }

    /**
     * The lens handle carried on the matched route's `defaults`, or `''` when absent.
     */
    private function lensHandle(Request $request): string
    {
        $route = $request->route();
        // The `??` already reads a missing route as no lens, so the guard is an
        // equivalent of it
        // @pest-mutate-ignore: InstanceOfToTrue
        $handle = $route instanceof Route ? ($route->defaults['lens'] ?? null) : null;

        // No lens is registered under '' or any other unknown handle, so the empty
        // default is one of many equivalent values
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        return \is_string($handle) ? $handle : '';
    }

    /**
     * Whether the person may be rendered publicly - published and not private /
     * password-protected. Mirrors the front-end publish + visibility gate (UAMS-Web/uams-statamic-archive#223)
     * this custom route would otherwise bypass.
     */
    public function isPubliclyRenderable(Entry $person): bool
    {
        return $person->status() === 'published'
            && $person->value('status_and_visibility') !== 'private'
            && $person->value('password_protected') !== true;
    }

    /**
     * Whether the lens gate (if any) admits the person on the current site. An
     * ungated lens (no gate class) always passes.
     */
    private function passesGate(LensDescriptor $descriptor, Entry $person): bool
    {
        if ($descriptor->gate === null) {
            return true;
        }

        $gate = app($descriptor->gate);

        if (! $gate instanceof LensGate) {
            return false;
        }

        /** @var SiteInstance $site */
        $site = Site::current();

        return $gate->passes($person, $site);
    }

    /**
     * Render the lens template through the front-end View pipeline (layout +
     * cascade + augmentation of the resolved person), the way a normal front-end
     * response is built.
     *
     * The lens is self-canonical (each lens is its own page, never a duplicate of
     * `/people/{slug}`), so the request URL is passed as `permalink` for the
     * `<head>` in place of the person entry's own permalink.
     */
    private function renderLens(LensDescriptor $descriptor, Entry $person, Request $request): Response
    {
        $lensUrl = $request->url();

        /** @var View $view */
        $view = app(View::class);
        $view->template($descriptor->template);
        $view->layout(config('statamic.system.layout', 'layout'));
        $view->cascadeContent($person);
        // Override the cascaded person permalink (`/people/{slug}`) with the lens URL so
        // the lens is self-canonical - the `<head>` canonical + og:url resolve to THIS
        // page, not the base people route. `View::gatherData()` merges `with()` data over
        // the cascade, and the layout head reads that merged cascade, so this wins.
        $view->with([
            'permalink' => $lensUrl,
        ]);

        // Without this header the response is still prepared as HTML in UTF-8; only the
        // letter case of the charset name changes, which clients ignore
        // @pest-mutate-ignore: RemoveArrayItem
        return response($view->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
