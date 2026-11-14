<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tags;

use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Fields\Value;
use Statamic\Sites\Site as SiteObject;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;
use UAMSWeb\Aether\People\Lenses\LensDescriptor;
use UAMSWeb\Aether\People\Lenses\PeopleLensRegistry;

/**
 * `{{ people_lens_url lens="provider" :slug="slug" }}` - the URL a canonical
 * `people` entry renders at through a named lens on the CURRENT site (UAMS-Web/uams-statamic-archive#571).
 *
 * A people lens is registered as `(template, gate, route, sites)` in
 * `config/people_lenses.php` and routed under each active site's own URL prefix
 * (see `ServiceProvider::registerLensRoutes()`). A template that wants to link to a lens - the clinical
 * directory card pointing at `/provider/{slug}`, say - must therefore not
 * hard-code the route, or a config change silently breaks the link. This tag
 * asks the registry instead, so the link and the route share one source.
 *
 * Returns an EMPTY STRING when the lens is unknown, when it is not active on the
 * current site, or when no slug is available, so the caller can fall back to the
 * entry's own permalink rather than emit a URL that would 404. The tag resolves
 * the ROUTE only; it deliberately does not consult the lens gate, because a card
 * list has already filtered to eligible people and per-person gate evaluation
 * would cost a query per card.
 */
final class PeopleLensUrl extends Tags
{
    /**
     * @param  PeopleLensRegistry  $registry  The single source of the lens set (route + active sites)
     */
    public function __construct(
        private readonly PeopleLensRegistry $registry
    ) {}

    /**
     * Resolve the lens URL for a slug on the current site, or an empty string.
     */
    public function index(): string
    {
        /** @var Parameters $params */
        $params = $this->params;

        $lens = $this->stringify($params->get('lens'));
        $slug = $this->stringify($params->get('slug') ?? $this->contextSlug());

        // The registry holds no lens under an empty handle, so a blank lens would end in the same empty
        // string at the lookup below; the blank slug is the check that matters.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if ($lens === '' || $slug === '') {
            return '';
        }

        $descriptor = $this->registry->get($lens);

        if (! $descriptor instanceof LensDescriptor) {
            return '';
        }

        /** @var SiteObject $site */
        $site = Site::current();

        $handle = $site->handle();
        $siteUrl = $site->url();

        // Statamic's Site accessors are untyped, so a malformed site definition could
        // yield a non-string; treat that as "no lens URL" rather than building a
        // half-formed path.
        if (! \is_string($handle) || ! \is_string($siteUrl)) {
            return '';
        }

        if (! $descriptor->activeOnSite($handle)) {
            return '';
        }

        return $this->buildUrl($descriptor, $siteUrl, $slug);
    }

    /**
     * The `slug` in the surrounding scope (a card loop's entry), if any.
     */
    private function contextSlug(): mixed
    {
        /** @var Context $context */
        $context = $this->context;

        return $context->get('slug');
    }

    /**
     * Normalize a param/context value - which may arrive as an augmented
     * {@see Value} rather than a scalar - to a trimmed string.
     */
    private function stringify(mixed $value): string
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        if (! \is_string($value) && ! \is_numeric($value)) {
            return '';
        }

        return \trim((string) $value);
    }

    /**
     * Join the site's relative prefix to the lens route with the slug filled in.
     *
     * @param  string  $siteUrl  The current site's absolute URL, reduced to a relative prefix
     *
     * The slug is `rawurlencode`d so a malformed imported slug cannot break out
     * of the path (Statamic slugs are already URL-safe, so this is a no-op for
     * every well-formed one).
     */
    private function buildUrl(LensDescriptor $descriptor, string $siteUrl, string $slug): string
    {
        $path = \str_replace('{slug}', \rawurlencode($slug), $descriptor->route);

        $prefix = \rtrim(URL::makeRelative($siteUrl), '/');

        return $prefix.'/'.\ltrim($path, '/');
    }
}
