<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as DatabaseQueryBuilder;
use Illuminate\Support\Collection;
use Statamic\Contracts\Query\Builder as StatamicQueryBuilder;

/**
 * Turns a field value (a collection, a lazy relationship query or an array) into a flat list.
 *
 * The host's `normalize_list` modifier does the same job for its own templates and stays in the
 * host, where several modifiers share it. The Find-a-Doc eligibility modifier needs only this
 * much of it, so the add-on carries its own copy rather than reaching into the host.
 */
final class ListNormalizer
{
    /**
     * The value as a flat list, with each query builder expanded to its results.
     *
     * Null, `false` and the empty string are an empty list; a scalar is a one-element list.
     *
     * @param  mixed  $value  A collection, a query builder, an array or a scalar.
     * @return list<mixed> The items, reindexed.
     */
    public function normalize(mixed $value): array
    {
        // Treat the empty sentinels as an empty list
        if (\in_array($value, [null, false, ''], true)) {
            return [];
        }

        // Resolve a query builder to its results
        if ($this->isQueryBuilder($value)) {
            return $this->results($value);
        }

        // Unwrap a collection, then expand any builder inside it
        if ($value instanceof Collection) {
            return $this->flatten(array_values($value->all()));
        }

        // Reindex a plain array, then expand any builder inside it
        if (\is_array($value)) {
            return $this->flatten(array_values($value));
        }

        return [$value];
    }

    /**
     * Expand each query builder in a list to its results.
     *
     * @param  list<mixed>  $items  The items to flatten.
     * @return list<mixed> The items with each builder replaced by its results.
     */
    private function flatten(array $items): array
    {
        $out = [];

        foreach ($items as $item) {
            if ($this->isQueryBuilder($item)) {
                $out = [...$out, ...$this->results($item)];
            } else {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * The results of a query builder.
     *
     * Statamic's query-builder contract omits `get()`, which every concrete builder provides, so
     * the call is checked at run time.
     *
     * @param  object  $builder  A Statamic or Laravel query builder.
     * @return list<mixed> The result items.
     */
    private function results(object $builder): array
    {
        if (! method_exists($builder, 'get')) {
            return [];
        }

        $results = $builder->get();

        return $results instanceof Collection ? array_values($results->all()) : [];
    }

    /**
     * Whether the value is a Statamic or Laravel query builder.
     *
     * @param  mixed  $value  The value to inspect.
     *
     * @phpstan-assert-if-true StatamicQueryBuilder|DatabaseQueryBuilder|EloquentQueryBuilder<Model> $value
     */
    private function isQueryBuilder(mixed $value): bool
    {
        return $value instanceof StatamicQueryBuilder
            || $value instanceof DatabaseQueryBuilder
            || $value instanceof EloquentQueryBuilder;
    }
}
