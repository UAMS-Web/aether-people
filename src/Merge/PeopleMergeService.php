<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Merge;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use UAMSWeb\Aether\People\Contracts\EntryReferenceRepointer;
use UAMSWeb\Aether\People\Contracts\MergeRedirectWriter;
use UAMSWeb\Aether\People\Support\PeopleCollection;
use UAMSWeb\Aether\People\Support\PeopleEditingScope;

/**
 * Merges two or more duplicate `people` entries into one canonical survivor with
 * per-field source selection (UAMS-Web/uams-statamic-archive#838).
 *
 * The survivor keeps its identity (id, slug, public URL); the operator chooses,
 * field by field, which candidate's value wins. On commit the service writes the
 * composite onto the survivor, re-points every inbound reference from the
 * retired entries to the survivor, leaves a redirect from each retired URL, and
 * only then deletes the retired entries — so nothing is destroyed before all
 * other writes have succeeded, and a preview can show exactly what will happen.
 *
 * Multisite rule (documented): every substantive `people` field is global
 * (non-localizable — only `slug` is localizable), so field reconciliation is a
 * single global decision per field. A site's presence is never silently dropped:
 * for each site where a retired entry was reachable, a redirect is created from
 * that site's retired URL to the survivor's URL (the survivor's URL in the same
 * site when it exists there, otherwise the survivor's canonical URL). Finer
 * per-site localization propagation is out of scope for v1.
 *
 * A signed-in user the people editing scope covers (UAMS-Web/uams-statamic-archive#3182) may merge only
 * entries in their scope, and only into a survivor that stays in it (UAMS-Web/uams-statamic-archive#3197).
 * The survivor is written with `saveQuietly()`, so the save listener that
 * enforces the scope never sees it; the service applies the same rule itself.
 */
class PeopleMergeService
{
    /**
     * The collection this tool operates on. Merging across collections is out of scope.
     */
    public static function peopleCollection(): string
    {
        return PeopleCollection::handle();
    }

    /**
     * Field handles never offered for reconciliation: identity (kept on the
     * survivor), system bookkeeping, and the derived title.
     *
     * @var list<string>
     */
    private const array NON_RECONCILABLE_HANDLES = [
        'id',
        'slug',
        'title',
        'blueprint',
        'date',
        'published',
        'updated_at',
        'updated_by',
    ];

    /**
     * @param  EntryReferenceRepointer  $repointer  Re-points inbound references from the retired entries.
     * @param  MergeRedirectWriter|null  $redirects  Writes and counts the redirects from the retired URLs. Null when the host has not bound one, which refuses every merge and preview.
     * @param  PeopleEditingScope  $scope  Limits a scoped user to the people in their organizations.
     */
    public function __construct(
        private readonly EntryReferenceRepointer $repointer,
        private readonly ?MergeRedirectWriter $redirects = null,
        private readonly PeopleEditingScope $scope = new PeopleEditingScope,
    ) {}

    /**
     * Build the field-by-field reconciliation view across the candidate entries.
     *
     * @param  list<string>  $entryIds  Ids of the people entries to merge (2+).
     * @param  string|null  $survivorId  Preferred survivor; defaults to the first candidate.
     *
     * @throws PeopleMergeException When fewer than two valid people candidates resolve.
     */
    public function buildReconciliation(array $entryIds, ?string $survivorId = null): MergeReconciliation
    {
        $entries = $this->loadCandidates($entryIds);
        $survivorId = $this->resolveSurvivorId($entries, $survivorId);

        return new MergeReconciliation(
            $survivorId,
            $this->candidateRows($entries, $survivorId),
            $this->reconcilableFields($entries, $survivorId),
            $this->siteFootprint($entries, $survivorId),
        );
    }

    /**
     * Preview a merge without writing anything (drives the confirm screen).
     *
     * @param  list<string>  $entryIds  Ids of the people entries to merge (2+).
     * @param  string  $survivorId  The chosen survivor.
     * @param  array<string, string>  $fieldSelections  Field handle => source entry id.
     *
     * @throws PeopleMergeException
     */
    public function preview(array $entryIds, string $survivorId, array $fieldSelections): MergeResult
    {
        return $this->execute($entryIds, $survivorId, $fieldSelections, dryRun: true);
    }

    /**
     * Execute the merge: write the survivor, re-point references, create
     * redirects, then retire the losers.
     *
     * @param  list<string>  $entryIds  Ids of the people entries to merge (2+).
     * @param  string  $survivorId  The chosen survivor.
     * @param  array<string, string>  $fieldSelections  Field handle => source entry id.
     *
     * @throws PeopleMergeException
     */
    public function merge(array $entryIds, string $survivorId, array $fieldSelections): MergeResult
    {
        return $this->execute($entryIds, $survivorId, $fieldSelections, dryRun: false);
    }

    /**
     * Shared preview/commit path. Validates, composes the survivor data, then
     * either reports the plan (dry run) or applies it in a safe order.
     *
     * @param  list<string>  $entryIds
     * @param  array<string, string>  $fieldSelections
     *
     * @throws PeopleMergeException
     */
    private function execute(array $entryIds, string $survivorId, array $fieldSelections, bool $dryRun): MergeResult
    {
        // Refuse before anything is read or written: without a writer the preview cannot count
        // redirects and the commit would retire entries whose URLs then 404
        $redirects = $this->redirects ?? throw new PeopleMergeException(
            'People merge cannot run: no redirect writer is bound. Bind '.MergeRedirectWriter::class
            .' in the host (a writer that writes nothing is a valid binding) and try again.',
        );

        $entries = $this->loadCandidates($entryIds);

        // The survivor must be one of the candidates so its identity is preserved
        if (! isset($entries[$survivorId])) {
            throw new PeopleMergeException('The survivor must be one of the selected people entries.');
        }

        $survivor = $entries[$survivorId];
        $loserIds = array_values(array_diff(array_keys($entries), [$survivorId]));

        [$mergedData, $appliedFields] = $this->composeSurvivorData($entries, $survivorId, $fieldSelections);

        // Refuse a merge that would leave a scoped user's survivor outside their scope
        $this->assertSurvivorStaysInScope($survivor, $mergedData);

        // loser id => survivor id, for the reference re-pointer
        $idMap = array_fill_keys($loserIds, $survivorId);

        // Plan redirects from the still-present loser localizations (URLs must
        // resolve, so this is computed before anything is deleted)
        $redirectPlan = $this->planRedirects($survivor, $loserIds);

        if ($dryRun) {
            return new MergeResult(
                $survivorId,
                true,
                $appliedFields,
                $this->repointer->preview($idMap),
                $redirects->count($redirectPlan),
                $redirectPlan,
                $loserIds,
            );
        }

        // Commit in a safe order: write the survivor, re-point references, write
        // redirects, and only then retire the losers — so a failure before the
        // final step leaves the duplicates intact rather than losing data.
        $survivor->data($mergedData);
        $survivor->saveQuietly();

        $references = $this->repointer->repoint($idMap);
        $redirectsCreated = $redirects->write($redirectPlan);
        $this->retireLosers($loserIds);

        return new MergeResult(
            $survivorId,
            false,
            $appliedFields,
            $references,
            $redirectsCreated,
            $redirectPlan,
            $loserIds,
        );
    }

    /**
     * Compose the survivor's post-merge data.
     *
     * Every reconcilable field is resolved to a source: the operator's explicit
     * selection when given, otherwise the automatic default (the survivor when it
     * has a value, else the first candidate that does — so a value held by only
     * one entry is never silently dropped). The selected value is written onto
     * the survivor; an empty selected value clears the field.
     *
     * @param  array<string, Entry>  $entries
     * @param  array<string, string>  $fieldSelections
     * @return array{0: array<array-key, mixed>, 1: list<string>} The merged data and the handles taken from a non-survivor source.
     *
     * @throws PeopleMergeException
     */
    private function composeSurvivorData(array $entries, string $survivorId, array $fieldSelections): array
    {
        // Start from the automatic defaults, then layer the operator's choices on top
        $selections = $this->defaultSelections($entries, $survivorId);

        foreach ($fieldSelections as $handle => $sourceId) {
            // A selection must name a real candidate, even before we know the field
            if (! isset($entries[$sourceId])) {
                throw new PeopleMergeException(sprintf('Unknown source entry [%s] for field [%s].', $sourceId, $handle));
            }

            // Ignore identity/system handles and anything outside the reconcilable set
            if (! \array_key_exists($handle, $selections)) {
                continue;
            }

            $selections[$handle] = $sourceId;
        }

        $merged = $this->entryData($entries[$survivorId]);
        $applied = [];

        foreach ($selections as $handle => $sourceId) {
            $value = Arr::get($this->entryData($entries[$sourceId]), $handle);

            // Taking an empty value means clearing the field on the survivor
            if ($this->hasValue($value)) {
                Arr::set($merged, $handle, $value);
            } else {
                Arr::forget($merged, $handle);
            }

            // Record only the fields whose value came from a non-survivor entry
            if ($sourceId !== $survivorId) {
                $applied[] = $handle;
            }
        }

        return [$merged, $applied];
    }

    /**
     * The automatic winning source for every reconcilable field.
     *
     * @param  array<string, Entry>  $entries
     * @return array<string, string> Field handle => default source entry id.
     */
    private function defaultSelections(array $entries, string $survivorId): array
    {
        $survivor = $entries[$survivorId];

        $handles = $this->reconcilableHandles($entries, $this->blueprintFieldMeta($survivor), $this->nonReconcilableHandles($survivor));

        $defaults = [];

        foreach ($handles as $handle) {
            $defaults[$handle] = $this->defaultSourceForHandle($entries, $survivorId, $handle);
        }

        return $defaults;
    }

    /**
     * Default winning source for one field: the survivor when it has a value,
     * else the first candidate that does (so a lone value is preserved), else
     * the survivor as a no-op fallback.
     *
     * @param  array<string, Entry>  $entries
     */
    private function defaultSourceForHandle(array $entries, string $survivorId, string $handle): string
    {
        $fallback = null;

        foreach ($entries as $id => $entry) {
            if (! $this->hasValue(Arr::get($this->entryData($entry), $handle))) {
                continue;
            }

            if ($id === $survivorId) {
                return $survivorId;
            }

            $fallback ??= $id;
        }

        return $fallback ?? $survivorId;
    }

    /**
     * Load, validate, and key the candidate entries by id (input order kept).
     *
     * @param  list<string>  $entryIds
     * @return array<string, Entry>
     *
     * @throws PeopleMergeException
     */
    private function loadCandidates(array $entryIds): array
    {
        // Unique, non-empty ids, order preserved
        // A blank id resolves to no entry and a repeated one to the same keyed entry, so dropping the
        // blank test, the filter, the unique or the reindex changes nothing a caller can see.
        // @pest-mutate-ignore: UnwrapArrayFilter, UnwrapArrayUnique, UnwrapArrayValues, EmptyStringToNotEmpty
        $ids = array_values(array_unique(array_filter($entryIds, static fn (string $id): bool => $id !== '')));

        $entries = [];

        foreach ($ids as $id) {
            /** @var Entry|null $entry */
            $entry = EntryFacade::find($id);

            // Silently skip ids that no longer resolve (e.g. concurrently deleted)
            if ($entry === null) {
                continue;
            }

            // Refuse anything that is not a people entry — never merge across collections
            if ($entry->collectionHandle() !== self::peopleCollection()) {
                throw new PeopleMergeException(sprintf('Entry [%s] is not a people entry and cannot be merged.', $id));
            }

            // Refuse an entry the signed-in user's scope does not reach, naming it
            $this->assertInScope($entry);

            $entries[$id] = $entry;
        }

        if (\count($entries) < 2) {
            throw new PeopleMergeException('Select at least two people entries to merge.');
        }

        return $entries;
    }

    /**
     * Refuse a candidate outside the signed-in user's people editing scope.
     *
     * @throws PeopleMergeException When a scoped user may not act on the entry.
     */
    private function assertInScope(Entry $entry): void
    {
        $user = User::current();

        // Administrators, console runs and imports are never scoped
        if ($user === null || ! $this->scope->appliesTo($user)) {
            return;
        }

        if ($this->scope->allows($user, $entry)) {
            return;
        }

        throw new PeopleMergeException(sprintf(
            'You cannot merge %s: they are not linked to an organization you edit for.',
            $this->entryLabel($entry),
        ));
    }

    /**
     * Refuse a merge whose survivor would name none of a scoped user's
     * organizations, as the save listener refuses it on an ordinary save.
     *
     * @param  array<array-key, mixed>  $mergedData  The survivor's data after the merge.
     *
     * @throws PeopleMergeException When the composed survivor falls out of scope.
     */
    private function assertSurvivorStaysInScope(Entry $survivor, array $mergedData): void
    {
        $user = User::current();

        // Administrators, console runs and imports are never scoped
        if ($user === null || ! $this->scope->appliesTo($user)) {
            return;
        }

        // Check a copy, so a refused preview leaves the loaded survivor unchanged
        $composed = clone $survivor;
        $composed->data($mergedData);

        if ($this->scope->allows($user, $composed)) {
            return;
        }

        throw new PeopleMergeException(sprintf(
            'The merged %s must stay linked to an organization you edit for. '
            .'Keep one of your organizations on a role or service line.',
            $this->entryLabel($survivor),
        ));
    }

    /**
     * An entry's title with its id, for naming it in a refusal.
     */
    private function entryLabel(Entry $entry): string
    {
        $id = $entry->id();
        $title = $this->entryTitle($entry);

        return \is_string($id) && $id !== $title ? sprintf('%s [%s]', $title, $id) : sprintf('[%s]', $title);
    }

    /**
     * Pick the survivor: the requested id when valid, else the first candidate.
     *
     * @param  array<string, Entry>  $entries
     */
    private function resolveSurvivorId(array $entries, ?string $survivorId): string
    {
        if ($survivorId !== null && isset($entries[$survivorId])) {
            return $survivorId;
        }

        return (string) array_key_first($entries);
    }

    /**
     * Build one display row per candidate entry for the reconciliation header.
     *
     * @param  array<string, Entry>  $entries
     * @return list<array{id: string, title: string, slug: ?string, site: string, url: ?string, edit_url: ?string, is_survivor: bool}>
     */
    private function candidateRows(array $entries, string $survivorId): array
    {
        $rows = [];

        foreach ($entries as $id => $entry) {
            $rows[] = [
                'id' => $id,
                'title' => $this->entryTitle($entry),
                'slug' => $this->stringOrNull($entry->slug()),
                'site' => $this->localeString($entry),
                'url' => $this->stringOrNull($entry->url()),
                'edit_url' => $this->stringOrNull($entry->editUrl()),
                'is_survivor' => $id === $survivorId,
            ];
        }

        return $rows;
    }

    /**
     * Build the reconcilable field rows: every stored/known field except
     * identity, with each candidate's value, a conflict flag, and a sensible
     * default source that never silently drops a value.
     *
     * @param  array<string, Entry>  $entries
     * @return list<array{handle: string, display: string, type: string, conflict: bool, values: list<array{entry_id: string, value: mixed, display: string, has_value: bool}>, default_source: string}>
     */
    private function reconcilableFields(array $entries, string $survivorId): array
    {
        $survivor = $entries[$survivorId];
        $blueprintFields = $this->blueprintFieldMeta($survivor);

        $handles = $this->reconcilableHandles($entries, $blueprintFields, $this->nonReconcilableHandles($survivor));

        $fields = [];

        foreach ($handles as $handle) {
            $values = [];
            $distinct = [];

            foreach ($entries as $id => $entry) {
                $raw = Arr::get($this->entryData($entry), $handle);
                $hasValue = $this->hasValue($raw);

                $values[] = [
                    'entry_id' => $id,
                    'value' => $raw,
                    'display' => $this->displayValue($raw),
                    'has_value' => $hasValue,
                ];

                if ($hasValue) {
                    // Track distinct non-empty values to detect a conflict
                    // A distinct value is counted by its key alone, so the stored flag is never read.
                    // @pest-mutate-ignore: TrueToFalse
                    $distinct[$this->normalize($raw)] = true;
                }
            }

            $fields[] = [
                'handle' => $handle,
                'display' => $blueprintFields[$handle]['display'] ?? $this->humanize($handle),
                'type' => $blueprintFields[$handle]['type'] ?? 'unknown',
                'conflict' => \count($distinct) > 1,
                'values' => $values,
                'default_source' => $this->defaultSourceForHandle($entries, $survivorId, $handle),
            ];
        }

        return $fields;
    }

    /**
     * The set of handles to reconcile: the union of the survivor blueprint's
     * non-computed fields and every stored data key across candidates, minus the
     * identity/system denylist. The union guarantees no differing stored value
     * is hidden, even if the blueprint changed.
     *
     * @param  array<string, Entry>  $entries
     * @param  array<string, array{display: string, type: string}>  $blueprintFields
     * @param  list<string>  $excludedHandles  Identity/system + computed handles to omit.
     * @return list<string>
     */
    private function reconcilableHandles(array $entries, array $blueprintFields, array $excludedHandles): array
    {
        $handles = array_keys($blueprintFields);

        foreach ($entries as $entry) {
            $handles = array_merge($handles, array_keys($this->entryData($entry)));
        }

        // The list is iterated and re-indexed below; its keys are never read.
        // @pest-mutate-ignore: UnwrapArrayValues
        $handles = array_values(array_unique($handles));

        // Callers only iterate the result, and a handle is compared by string equality against
        // non-numeric names, where loose and strict comparison agree.
        // @pest-mutate-ignore: UnwrapArrayValues, TrueToFalse
        return array_values(array_filter(
            $handles,
            fn (string $handle): bool => ! \in_array($handle, $excludedHandles, true),
        ));
    }

    /**
     * Handles never reconciled: the identity/system denylist plus the survivor
     * blueprint's computed fields (derived at read time — a stale stored value
     * must not resurface as a mergeable field).
     *
     * @return list<string>
     */
    private function nonReconcilableHandles(Entry $survivor): array
    {
        // The list is only searched for a handle, so a repeated entry and its keys change nothing.
        // @pest-mutate-ignore: UnwrapArrayUnique, UnwrapArrayValues
        return array_values(array_unique([
            ...self::NON_RECONCILABLE_HANDLES,
            ...$this->computedHandles($survivor),
        ]));
    }

    /**
     * The survivor blueprint's computed field handles.
     *
     * @return list<string>
     */
    private function computedHandles(Entry $survivor): array
    {
        $blueprint = $survivor->blueprint();

        if (! $blueprint instanceof Blueprint) {
            return [];
        }

        $computed = [];

        /** @var Collection<string, Field> $fields */
        $fields = $blueprint->fields()->all();

        foreach ($fields as $handle => $field) {
            if ($field->visibility() === 'computed') {
                // A handle is a string; the cast only matters for an all-digit handle PHP turned into an int key.
                // @pest-mutate-ignore: RemoveStringCast
                $computed[] = (string) $handle;
            }
        }

        return $computed;
    }

    /**
     * Display + type metadata for the survivor blueprint's non-computed fields.
     *
     * @return array<string, array{display: string, type: string}>
     */
    private function blueprintFieldMeta(Entry $survivor): array
    {
        $blueprint = $survivor->blueprint();

        if (! $blueprint instanceof Blueprint) {
            return [];
        }

        $meta = [];

        /** @var Collection<string, Field> $fields */
        $fields = $blueprint->fields()->all();

        foreach ($fields as $handle => $field) {
            // Computed fields are derived at read time and have nothing to merge
            if ($field->visibility() === 'computed') {
                continue;
            }

            // A handle is a string; the casts only matter for an all-digit handle PHP turned into an int key,
            // and an array key reads the same either way.
            // @pest-mutate-ignore: RemoveStringCast
            $meta[(string) $handle] = [
                'display' => $this->stringOrNull($field->display()) ?? $this->humanize((string) $handle),
                'type' => $this->stringOrNull($field->type()) ?? 'unknown',
            ];
        }

        return $meta;
    }

    /**
     * Build the per-site footprint so the operator can see multisite presence.
     *
     * @param  array<string, Entry>  $entries
     * @return array<string, list<array{entry_id: string, slug: ?string, url: ?string, is_survivor: bool}>>
     */
    private function siteFootprint(array $entries, string $survivorId): array
    {
        $sites = [];

        foreach ($entries as $id => $entry) {
            foreach ($this->localizationsOf($entry) as $localization) {
                $sites[$this->localeString($localization)][] = [
                    'entry_id' => $id,
                    'slug' => $this->stringOrNull($localization->slug()),
                    'url' => $this->stringOrNull($localization->url()),
                    'is_survivor' => $id === $survivorId,
                ];
            }
        }

        return $sites;
    }

    /**
     * Plan a redirect from each retired localization URL to the survivor's URL
     * in that site (or its canonical URL when the survivor is absent there).
     * Self-redirects (identical paths) are skipped.
     *
     * @param  list<string>  $loserIds
     * @return list<array{site: string, source: string, target: string, response: string}>
     */
    private function planRedirects(Entry $survivor, array $loserIds): array
    {
        $plan = [];

        foreach ($loserIds as $loserId) {
            /** @var Entry|null $loser */
            $loser = EntryFacade::find($loserId);

            if ($loser === null) {
                continue;
            }

            foreach ($this->localizationsOf($loser) as $localization) {
                $site = $this->localeString($localization);
                $source = $this->stringOrNull($localization->url());

                if ($source === null) {
                    continue;
                }

                // Survivor's URL in the same site, else its canonical URL
                $survivorInSite = $survivor->in($site);
                $target = $this->stringOrNull(($survivorInSite instanceof Entry ? $survivorInSite : $survivor)->url());
                if ($target === null) {
                    continue;
                }

                if ($source === $target) {
                    continue;
                }

                $plan[] = [
                    'site' => $site,
                    'source' => $source,
                    'target' => $target,
                    'response' => '301',
                ];
            }
        }

        return $plan;
    }

    /**
     * Delete the retired entries. Runs last, after every other write succeeds.
     *
     * @param  list<string>  $loserIds
     */
    private function retireLosers(array $loserIds): void
    {
        foreach ($loserIds as $loserId) {
            /** @var Entry|null $entry */
            $entry = EntryFacade::find($loserId);

            if ($entry === null) {
                continue;
            }

            // Statamic refuses to delete an entry that still has localizations, so
            // remove the per-site localizations first, then the origin — quietly,
            // so the merge never fires entry-deleted listeners.
            $entry->deleteDescendants(false);
            $entry->deleteQuietly();
        }
    }

    /**
     * An entry plus its localizations in other sites (origin first).
     *
     * @return list<Entry>
     */
    private function localizationsOf(Entry $entry): array
    {
        $localizations = [$entry];

        try {
            /** @var iterable<Entry> $descendants */
            $descendants = $entry->descendants();

            foreach ($descendants as $descendant) {
                $localizations[] = $descendant;
            }
        } catch (\Throwable) {
            // Single-site installs (or stubs without descendants) just use origin
        }

        return $localizations;
    }

    /**
     * An entry's stored data as an array (the `data()` getter is untyped).
     *
     * @return array<string, mixed>
     */
    private function entryData(Entry $entry): array
    {
        /** @var Collection<string, mixed> $data */
        $data = $entry->data();

        return $data->all();
    }

    /**
     * The entry's stored title, falling back to its id for display.
     */
    private function entryTitle(Entry $entry): string
    {
        $title = Arr::get($this->entryData($entry), 'title');

        if (\is_string($title) && $title !== '') {
            return $title;
        }

        $id = $entry->id();

        return \is_string($id) ? $id : '';
    }

    /**
     * The entry's site handle as a string (the `locale()` getter is untyped).
     */
    private function localeString(Entry $entry): string
    {
        $locale = $entry->locale();

        return \is_string($locale) ? $locale : '';
    }

    /**
     * Whether a stored value counts as present (not null, empty string, or []).
     */
    private function hasValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return $value !== [];
    }

    /**
     * A stable string key for comparing values (including arrays) for conflicts.
     *
     * Comparison is deliberately strict (raw `json_encode`): a difference in list
     * order or scalar type counts as a conflict. For a destructive merge this
     * errs toward over-flagging — surfacing a borderline field for the operator to
     * decide is safe, whereas under-flagging could hide a real divergence. The
     * operator always sees both values and chooses, so a false conflict is noise,
     * not data loss.
     */
    private function normalize(mixed $value): string
    {
        return (string) json_encode($value);
    }

    /**
     * A short human-readable rendering of a value for the comparison table.
     */
    private function displayValue(mixed $value): string
    {
        // An empty string renders as an empty string whichever branch it takes, so its entry is redundant.
        // @pest-mutate-ignore: RemoveArrayItem, EmptyStringToNotEmpty
        if (in_array($value, [null, '', []], true)) {
            return '';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_scalar($value)) {
            return Str::limit((string) $value, 120);
        }

        return Str::limit((string) json_encode($value), 120);
    }

    /**
     * Title-case a handle for display when no blueprint label is available.
     */
    private function humanize(string $handle): string
    {
        return Str::of($handle)->replace(['_', '-'], ' ')->title()->toString();
    }

    /**
     * Narrow a possibly-non-string value (Stringable, null) to a string or null.
     */
    private function stringOrNull(mixed $value): ?string
    {
        // Falling through reaches the same final return null.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            return $value;
        }

        // Since PHP 8 a class with __toString is Stringable, so the method_exists clause adds nothing.
        // @pest-mutate-ignore: InstanceOfToFalse, BooleanOrToBooleanAnd
        if ($value instanceof \Stringable || (\is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return null;
    }
}
