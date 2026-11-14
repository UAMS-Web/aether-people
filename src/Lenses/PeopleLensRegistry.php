<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Lenses;

/**
 * The people lens registry (UAMS-Web/uams-statamic-archive#1636): reads `config('people_lenses.lenses')` into
 * validated {@see LensDescriptor} objects.
 *
 * The registry is the single source the render mechanism and the route
 * registration both consult, so the clinical lens and every org-derived lens are
 * expressed as configuration with no special-cased branch. Malformed descriptors
 * are skipped rather than fatally breaking every lens route.
 */
final class PeopleLensRegistry
{
    /**
     * All configured lenses, keyed by handle.
     *
     * @return array<string, LensDescriptor>
     */
    public function all(): array
    {
        $configured = \config('people_lenses.lenses', []);

        if (! \is_array($configured)) {
            return [];
        }

        $lenses = [];

        foreach ($configured as $handle => $descriptor) {
            $built = $this->build((string) $handle, $descriptor);

            if ($built instanceof LensDescriptor) {
                // PHP turns a numeric string key into an integer key whether or not it is cast, so the cast
                // changes nothing in the result.
                // @pest-mutate-ignore: RemoveStringCast
                $lenses[(string) $handle] = $built;
            }
        }

        return $lenses;
    }

    /**
     * A single lens by handle, or null when it is unknown or malformed.
     */
    public function get(string $handle): ?LensDescriptor
    {
        return $this->all()[$handle] ?? null;
    }

    /**
     * The lenses active on a given site, keyed by handle.
     *
     * @return array<string, LensDescriptor>
     */
    public function activeOnSite(string $site): array
    {
        return \array_filter(
            $this->all(),
            static fn (LensDescriptor $descriptor): bool => $descriptor->activeOnSite($site),
        );
    }

    /**
     * Build a descriptor from a config row, or null when required keys are missing.
     *
     * @param  mixed  $descriptor  The raw config value for a lens handle.
     */
    private function build(string $handle, mixed $descriptor): ?LensDescriptor
    {
        if ($handle === '' || ! \is_array($descriptor)) {
            return null;
        }

        $template = $descriptor['template'] ?? null;
        $route = $descriptor['route'] ?? null;

        // A lens without a template and a slug-bearing route cannot render or route.
        if (! \is_string($template) || $template === '' || ! \is_string($route) || $route === '') {
            return null;
        }

        $gate = $descriptor['gate'] ?? null;

        // A gate must name a LensGate implementation; anything else is ignored (ungated).
        if (! \is_string($gate) || ! \is_a($gate, LensGate::class, true)) {
            $gate = null;
        }

        // An ABSENT `sites` key defaults to every site; a malformed (non-array) value
        // also falls back to every site. Only an EXPLICIT array is taken literally -
        // including an empty one, which registers the lens but activates it on no site
        // (opt-in: a site adds its handle to `sites` to turn the lens on there). This is
        // how the ungated general lens (UAMS-Web/uams-statamic-archive#1667) ships dormant until a site opts in,
        // instead of the ungated base profile rendering on all ~255 sites at once.
        if (! \array_key_exists('sites', $descriptor) || ! \is_array($descriptor['sites'])) {
            $sites = ['*'];
        } else {
            // Normalize the explicit sites list to a clean list of non-empty strings.
            $sites = \array_values(\array_filter(
                $descriptor['sites'],
                static fn ($site): bool => \is_string($site) && $site !== '',
            ));
        }

        /** @var class-string<LensGate>|null $gate */
        /** @var list<string> $sites */
        return new LensDescriptor(
            handle: $handle,
            template: $template,
            gate: $gate,
            route: $route,
            sites: $sites,
        );
    }
}
