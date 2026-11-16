<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Http\Controllers\CP\CpController;
use UAMSWeb\Aether\People\Merge\PeopleMergeException;
use UAMSWeb\Aether\People\Merge\PeopleMergeService;

/**
 * Drives the duplicate-people merge reconciliation screen (UAMS-Web/uams-statamic-archive#838).
 *
 * `show` renders the field-by-field reconciliation for the selected entries;
 * `preview` returns what a merge *would* do without writing; `commit` performs
 * the merge. Every action is gated by the `merge people entries` permission, and
 * the destructive `commit` only runs after the operator confirms on the screen.
 */
class MergeController extends CpController
{
    /**
     * The permission required for every merge endpoint.
     */
    private const string PERMISSION = 'merge people entries';

    /**
     * Render the reconciliation screen for the selected people entries.
     */
    public function show(Request $request, PeopleMergeService $service): InertiaResponse
    {
        $this->authorize(self::PERMISSION);

        $ids = $this->idsFromCsv($request->query('ids'));

        // Build the reconciliation; surface a problem (too few candidates, a
        // non-people entry) to the screen rather than throwing a 500.
        try {
            $reconciliation = $service->buildReconciliation($ids)->toArray();
            $error = null;
        } catch (PeopleMergeException $peopleMergeException) {
            $reconciliation = null;
            $error = $peopleMergeException->getMessage();
        }

        return Inertia::render('aether-people::PeopleMerge', [
            'reconciliation' => $reconciliation,
            'error' => $error,
            'previewUrl' => cp_route('people-merge.preview'),
            'commitUrl' => cp_route('people-merge.commit'),
        ]);
    }

    /**
     * Return a dry-run preview of the merge (no writes).
     */
    public function preview(Request $request, PeopleMergeService $service): JsonResponse
    {
        $this->authorize(self::PERMISSION);

        $payload = $this->validateMerge($request);

        try {
            $result = $service->preview($payload['ids'], $payload['survivor'], $payload['selections']);
        } catch (PeopleMergeException $peopleMergeException) {
            return response()->json(['message' => $peopleMergeException->getMessage()], 422);
        }

        return response()->json($result->toArray());
    }

    /**
     * Perform the merge after the operator has confirmed.
     */
    public function commit(Request $request, PeopleMergeService $service): JsonResponse
    {
        $this->authorize(self::PERMISSION);

        $payload = $this->validateMerge($request);

        try {
            $result = $service->merge($payload['ids'], $payload['survivor'], $payload['selections']);
        } catch (PeopleMergeException $peopleMergeException) {
            return response()->json(['message' => $peopleMergeException->getMessage()], 422);
        }

        /** @var Entry|null $survivor */
        $survivor = EntryFacade::find($result->survivorId);

        return response()->json([
            'result' => $result->toArray(),
            'survivor_edit_url' => $survivor?->editUrl(),
        ]);
    }

    /**
     * Validate and normalize a preview/commit request body.
     *
     * @return array{ids: list<string>, survivor: string, selections: array<string, string>}
     */
    private function validateMerge(Request $request): array
    {
        /** @var array{ids: list<string>, survivor: string, selections?: array<string, string>} $validated */
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:2'],
            'ids.*' => ['required', 'string'],
            'survivor' => ['required', 'string'],
            'selections' => ['array'],
            'selections.*' => ['string'],
        ]);

        return [
            'ids' => $validated['ids'],
            'survivor' => $validated['survivor'],
            'selections' => $validated['selections'] ?? [],
        ];
    }

    /**
     * Parse a comma-separated `ids` query value into a clean list.
     *
     * @return list<string>
     */
    private function idsFromCsv(mixed $value): array
    {
        if (! \is_string($value) || $value === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $value)),
            static fn (string $id): bool => $id !== '',
        ));
    }
}
