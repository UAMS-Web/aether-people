<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Merge;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\GlobalSet as GlobalSetItem;
use Statamic\Globals\Variables;
use UAMSWeb\Aether\People\Contracts\MergeRedirectWriter;

/**
 * Writes merge redirects into the `redirects` globals set, one localization per site.
 *
 * This is the behavior the people merge had in the site before redirect writing moved behind
 * {@see MergeRedirectWriter}. The add-on does not bind it: a host whose redirects live in that global
 * binds it, and a host that keeps them elsewhere binds its own writer. When the set does not exist it
 * logs a warning and reports zero, for the preview as for the commit.
 */
final class RedirectsGlobalWriter implements MergeRedirectWriter
{
    /**
     * Handle of the globals replicator that stores site redirects.
     */
    private const string REDIRECTS_GLOBAL = 'redirects';

    public function count(array $plan): int
    {
        $set = $this->redirectsSet($plan);

        if (! $set instanceof GlobalSetItem) {
            return 0;
        }

        $count = 0;

        foreach ($this->rowsBySite($plan) as $site => $rows) {
            $count += \count($this->newRows($this->variablesFor($set, $site), $rows));
        }

        return $count;
    }

    /**
     * Append the planned redirects to the redirects global and persist them on
     * the localization of the site each redirect serves.
     *
     * The redirects field is non-localizable, but `find_redirect_rule` (the 404
     * serving tag) reads `$global->in($currentSite)->get('redirects')` for the
     * site that 404'd: a raw, per-site read that does NOT cascade from the
     * origin localization (verified: UAMS-Web/uams-statamic-archive#1088). So a redirect for a loser reachable
     * only on a non-default site must be written to *that site's* localization,
     * or it would never be served there. Each planned row is therefore grouped by
     * its `site` and appended to that site's redirects (a default-site loser still
     * lands on the origin/default localization, where editors manage the list).
     *
     * The same per-site, no-cascade read means editor-managed redirects on the
     * default site do not serve on non-default sites either; that broader gap is
     * tracked in UAMS-Web/uams-statamic-archive#1110 (out of scope here: this only fixes merge-created rows).
     */
    public function write(array $plan): int
    {
        $set = $this->redirectsSet($plan);

        if (! $set instanceof GlobalSetItem) {
            return 0;
        }

        $created = 0;

        // Write each site's redirects to that site's own localization so the
        // serving tag (which reads the 404'ing site) finds them.
        foreach ($this->rowsBySite($plan) as $site => $rows) {
            $variables = $this->variablesFor($set, $site);
            $new = $this->newRows($variables, $rows);

            // Nothing new to write: every planned source already redirected here
            if ($new === []) {
                continue;
            }

            $existing = $variables->get(self::REDIRECTS_GLOBAL, []);
            $redirects = \is_array($existing) ? $existing : [];

            foreach ($new as $row) {
                $redirects[] = [
                    'id' => (string) Str::uuid(),
                    'type' => 'redirect',
                    'source' => $row['source'],
                    'target_type' => 'url',
                    'target_url' => $row['target'],
                    'response' => $row['response'],
                    'order' => 30,
                    'enabled' => true,
                ];
            }

            $variables->set(self::REDIRECTS_GLOBAL, $redirects);
            $variables->save();

            $created += \count($new);
        }

        return $created;
    }

    /**
     * The redirects set, or null when there is nothing to write or the set is missing.
     *
     * @param  list<array{site: string, source: string, target: string, response: string}>  $plan
     */
    private function redirectsSet(array $plan): ?GlobalSetItem
    {
        if ($plan === []) {
            return null;
        }

        /** @var GlobalSetItem|null $set */
        $set = GlobalSet::findByHandle(self::REDIRECTS_GLOBAL);

        // No redirects global (e.g. an environment without content): log and skip
        // rather than fail the merge; the re-pointing already protects inbound links.
        if ($set === null) {
            Log::warning('People merge could not create redirects: redirects global set is missing.', [
                'planned' => \count($plan),
            ]);
        }

        return $set;
    }

    /**
     * Group planned rows by the site they must serve on, preserving order.
     *
     * @param  list<array{site: string, source: string, target: string, response: string}>  $plan
     * @return array<string, list<array{site: string, source: string, target: string, response: string}>>
     */
    private function rowsBySite(array $plan): array
    {
        $rowsBySite = [];

        foreach ($plan as $row) {
            $rowsBySite[$row['site']][] = $row;
        }

        return $rowsBySite;
    }

    private function variablesFor(GlobalSetItem $set, string $site): Variables
    {
        $inSite = $set->in($site);

        /** @var Variables $variables */
        $variables = $inSite instanceof Variables ? $inSite : $set->makeLocalization($site);

        return $variables;
    }

    /**
     * The rows of one site that are not yet redirected there.
     *
     * Indexes the existing sources so repeated or overlapping merges (e.g. a new duplicate reusing a
     * freed slug) never append a second row for a path that already redirects on this site, which keeps
     * the global from bloating.
     *
     * @param  list<array{site: string, source: string, target: string, response: string}>  $rows
     * @return list<array{site: string, source: string, target: string, response: string}>
     */
    private function newRows(Variables $variables, array $rows): array
    {
        $existingValue = $variables->get(self::REDIRECTS_GLOBAL, []);
        $redirects = \is_array($existingValue) ? $existingValue : [];

        $existingSources = [];
        foreach ($redirects as $existing) {
            if (\is_array($existing) && isset($existing['source']) && \is_string($existing['source'])) {
                // isset() is true for a stored false too, so the flag is never read.
                // @pest-mutate-ignore: TrueToFalse
                $existingSources[$existing['source']] = true;
            }
        }

        $new = [];

        foreach ($rows as $row) {
            // Skip a source that already redirects on this site
            if (isset($existingSources[$row['source']])) {
                continue;
            }

            // isset() is true for a stored false too, so the flag is never read.
            // @pest-mutate-ignore: TrueToFalse
            $existingSources[$row['source']] = true;

            $new[] = $row;
        }

        return $new;
    }
}
