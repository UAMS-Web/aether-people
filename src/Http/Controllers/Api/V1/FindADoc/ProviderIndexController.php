<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Http\Controllers\Api\V1\FindADoc;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Statamic\Entries\Entry;
use UAMSWeb\Aether\People\Api\FindADoc\FindADocProviderRepository;
use UAMSWeb\Aether\People\Api\FindADoc\ProviderPayloadTransformer;

/**
 * `GET /api/v1/find-a-doc/providers` - the versioned, read-only list of every
 * clinical provider exposable through the Find-a-Doc API (UAMS-Web/uams-statamic-archive#1043).
 *
 * The repository enforces the eligibility + searchable gate, so only providers
 * the public directory would list appear; the transformer emits an allow-listed,
 * PII-free payload. The full eligibility scan is cached for a short TTL
 * (`find_a_doc_api.index_cache_ttl`).
 */
final class ProviderIndexController
{
    public function __construct(
        private readonly FindADocProviderRepository $repository,
        private readonly ProviderPayloadTransformer $transformer
    ) {}

    /**
     * Return the eligible-provider list as versioned JSON.
     */
    public function __invoke(): JsonResponse
    {
        $version = $this->version();

        $ttlConfig = config('find_a_doc_api.index_cache_ttl', 300);
        $ttl = \is_int($ttlConfig) ? $ttlConfig : 300;

        $build = fn (): array => $this->repository->eligibleProviders()
            ->map(fn (Entry $person): array => $this->transformer->toArray($person))
            ->all();

        // A time of zero gives the same response with or without the cache, which stores nothing for a time of zero
        // or less, so the lower bound of this comparison is an equivalent mutant
        // @pest-mutate-ignore: GreaterToGreaterOrEqual, DecrementInteger
        $data = $ttl > 0
            ? Cache::remember(sprintf('find_a_doc_api:%s:providers', $version), $ttl, $build)
            : $build();

        return response()->json([
            'version' => $version,
            'data' => $data,
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
