<?php

declare(strict_types=1);

if (! function_exists('throwableFrom')) {
    /**
     * Invoke `$callback` exactly once and return whatever it threw, or `null`.
     *
     * The idiom this exists to replace is `expect(fn () => …)->not->toThrow(…)`,
     * which is unsafe for a "nothing went wrong" assertion in two independent ways
     * (UAMS-Web/uams-statamic-archive#1759):
     *
     * 1. **It passes on the wrong throwable.** `not->toThrow(X)` inverts a `toThrow(X)`
     *    that fails its `assertInstanceOf` - so a throwable that is *not* an `X` reads
     *    as success. `->not->toThrow(Exception::class)` therefore stays green on a
     *    `TypeError` or a "call to a member function on null" `Error`, which is exactly
     *    the regression a bare call would have surfaced.
     * 2. **The obvious fix invokes twice.** Naming both `Exception::class` and
     *    `Error::class` closes the hole but calls the closure once per expectation,
     *    which breaks any Mockery `->once()` expectation the subject sets up.
     *
     * `Throwable::class` cannot paper over (1) either: it is an *interface*, so Pest's
     * `class_exists()` check fails and `toThrow()` silently degrades to a
     * message-substring match - passing against a real thrown `RuntimeException`.
     *
     * Capturing the throwable sidesteps all of it: one invocation, the whole `Throwable`
     * tree, and an assertion at the call site that can actually fail.
     *
     * ```php
     * expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
     * ```
     *
     * @param  callable():mixed  $callback  The operation expected to complete cleanly
     * @return Throwable|null The throwable it raised, or `null` when it completed
     */
    function throwableFrom(callable $callback): ?Throwable
    {
        // Run the subject once - a second invocation would double-count any Mockery
        // call-count expectation the caller set on it.
        try {
            $callback();
        } catch (Throwable $throwable) {
            return $throwable;
        }

        return null;
    }
}
