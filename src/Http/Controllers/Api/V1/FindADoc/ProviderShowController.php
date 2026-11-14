<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Http\Controllers\Api\V1\FindADoc;

use Illuminate\Http\JsonResponse;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Api\FindADoc\FindADocProviderRepository;
use UAMSWeb\Aether\People\Api\FindADoc\ProviderPayloadTransformer;

/**
 * `GET /api/v1/find-a-doc/providers/{provider}` - one provider by entry id (UAMS-Web/uams-statamic-archive#1043).
 *
 * Returns 404 for any id that is not an exposable provider (missing, not a
 * person, ineligible, or not searchable), so the endpoint can never disclose a
 * provider the public directory itself would hide. The payload is the same
 * allow-listed, PII-free shape as the index.
 */
final class ProviderShowController
{
    public function __construct(
        private readonly FindADocProviderRepository $repository,
        private readonly ProviderPayloadTransformer $transformer
    ) {}

    /**
     * Return one provider as versioned JSON, or 404 when not exposable.
     */
    public function __invoke(string $provider): JsonResponse
    {
        $person = $this->repository->findEligible($provider);

        if (! $person instanceof Entry) {
            abort(404, 'Provider not found or not available.');
        }

        return response()->json([
            'version' => $this->version(),
            'data' => $this->transformer->toArray($person),
        ]);
    }

    /**
     * The configured payload contract version.
     */
    private function version(): string
    {
        $version = config('find_a_doc_api.version', 'v1');

        return \is_string($version) ? $version : 'v1';
    }
}
