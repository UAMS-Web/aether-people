<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Query\Builder as StatamicBuilder;
use Statamic\Stache\Query\EntryQueryBuilder;
use UAMSWeb\Aether\People\Support\ListNormalizer;

uses(MockeryPHPUnitIntegration::class);

covers(ListNormalizer::class);

/**
 * Pins `ListNormalizer` to the behavior of the host's `NormalizeList` modifier, whose job it does
 * for the Find-a-Doc eligibility modifier: empty sentinels are an empty list, a query builder is
 * resolved with `get()`, a collection or array is reindexed with any builder inside it expanded,
 * and anything else is a one-element list.
 *
 * @command  vendor/bin/pest tests/Integration/Support/ListNormalizerTest.php
 */

/**
 * A mock of a builder class whose `get()` returns the given value.
 *
 * @param  string  $class  The builder class to mock.
 * @param  mixed  $results  What `get()` returns.
 */
function normalizerBuilder(string $class, mixed $results): MockInterface
{
    $builder = Mockery::mock($class);
    $builder->shouldReceive('get')->andReturn($results);

    return $builder;
}

/**
 * A list with each non-scalar item named by its type, so a failing assertion prints short strings
 * rather than a dump of a builder double.
 *
 * @param  array<array-key, mixed>  $items  The normalized list.
 * @return array<array-key, mixed>
 */
function normalizedShape(array $items): array
{
    return array_map(fn (mixed $item): mixed => is_scalar($item) ? $item : get_debug_type($item), $items);
}

it('turns the empty sentinels into an empty list', function (mixed $value): void {
    expect((new ListNormalizer)->normalize($value))->toBe([]);
})->with(['null' => [null], 'false' => [false], 'empty string' => ['']]);

it('keeps the other falsy scalars as a one-element list', function (mixed $value): void {
    expect((new ListNormalizer)->normalize($value))->toBe([$value]);
})->with(['zero' => [0], 'zero string' => ['0'], 'zero float' => [0.0], 'true' => [true], 'text' => ['a']]);

it('wraps a scalar or an object that is not a list', function (): void {
    $object = new stdClass;

    expect((new ListNormalizer)->normalize('a'))->toBe(['a'])
        ->and((new ListNormalizer)->normalize($object))->toBe([$object]);
});

it('reindexes a plain array', function (): void {
    expect((new ListNormalizer)->normalize([3 => 'a', 'k' => 'b']))->toBe(['a', 'b']);
});

it('reindexes a collection', function (): void {
    expect((new ListNormalizer)->normalize(new Collection([5 => 'a', 'k' => 'b'])))->toBe(['a', 'b']);
});

it('resolves each kind of query builder with get(), reindexed', function (string $class): void {
    $builder = normalizerBuilder($class, new Collection([4 => 'a', 'k' => 'b']));

    expect(normalizedShape((new ListNormalizer)->normalize($builder)))->toBe(['a', 'b']);
})->with([
    'Statamic builder' => [EntryQueryBuilder::class],
    'Laravel database builder' => [DatabaseBuilder::class],
    'Laravel Eloquent builder' => [EloquentBuilder::class],
]);

it('expands a builder held in an array or a collection in place', function (string $wrap): void {
    $builder = normalizerBuilder(EntryQueryBuilder::class, new Collection(['x', 'y']));
    $items = ['a', $builder, 'b'];
    $value = $wrap === 'collection' ? new Collection($items) : $items;

    expect(normalizedShape((new ListNormalizer)->normalize($value)))->toBe(['a', 'x', 'y', 'b']);
})->with(['array' => ['array'], 'collection' => ['collection']]);

it('expands every builder in a list, not only the first', function (): void {
    $first = normalizerBuilder(EntryQueryBuilder::class, new Collection(['x']));
    $second = normalizerBuilder(DatabaseBuilder::class, new Collection(['y']));

    expect(normalizedShape((new ListNormalizer)->normalize([$first, 'm', $second])))->toBe(['x', 'm', 'y']);
});

it('does not expand a collection held inside an array', function (): void {
    $inner = new Collection(['x']);

    expect((new ListNormalizer)->normalize([$inner]))->toBe([$inner]);
});

it('reads a builder with no get() as an empty list, alone or in a list', function (): void {
    $builder = Mockery::mock(StatamicBuilder::class);

    expect(normalizedShape((new ListNormalizer)->normalize($builder)))->toBe([])
        ->and(normalizedShape((new ListNormalizer)->normalize(['a', $builder, 'b'])))->toBe(['a', 'b']);
});

it('reads a builder whose get() is not a collection as an empty list', function (): void {
    $builder = normalizerBuilder(EntryQueryBuilder::class, ['not', 'a', 'collection']);

    expect(normalizedShape((new ListNormalizer)->normalize($builder)))->toBe([])
        ->and(normalizedShape((new ListNormalizer)->normalize(['a', $builder])))->toBe(['a']);
});
