<?php

declare(strict_types=1);

/**
 * Control-flow type narrowing for the test suite, copied from `tests/Support/TypeNarrowing.php`
 * in UAMS-Web/uams-statamic (UAMS-Web/uams-statamic-archive#1865), less its form helper.
 *
 * Each helper narrows a value for PHPStan by control flow rather than `assert()`, which can be
 * compiled out and names no cause when it trips, so a regression reports what went wrong.
 */
if (! function_exists('mustBe')) {
    /**
     * Narrow a value to an expected class, failing by name when it is something else.
     *
     * Replaces `\assert($x instanceof Foo)`, roughly two thirds of the sweep.
     * The `@template` binding is what lets PHPStan carry the concrete type
     * through to the caller, so the narrowing is *verified* rather than asserted.
     *
     * ```php
     * $blueprint = mustBe(Blueprint::class, Blueprint::in($namespace)->get($handle), "the {$handle} blueprint");
     * ```
     *
     * @template TExpected of object
     *
     * @param  class-string<TExpected>  $expected  The class or interface required.
     * @param  mixed  $value  The value to narrow.
     * @param  string  $subject  What the value is, named for the failure message.
     * @return TExpected The value, typed as the expected class.
     */
    function mustBe(string $expected, mixed $value, string $subject): object
    {
        if (! $value instanceof $expected) {
            throw new RuntimeException(sprintf(
                'Expected %s to be a %s, got %s.',
                $subject,
                $expected,
                get_debug_type($value),
            ));
        }

        return $value;
    }
}

if (! function_exists('mustBeString')) {
    /**
     * Narrow a value to a string, failing by name when it is not one.
     *
     * Replaces `\assert(\is_string($x))`, which typically guards a getter whose
     * union includes `null` (`Email::getHtmlBody()`, `Entry::slug()`).
     *
     * @param  mixed  $value  The value to narrow.
     * @param  string  $subject  What the value is, named for the failure message.
     * @return string The value, typed as a string.
     */
    function mustBeString(mixed $value, string $subject): string
    {
        if (! is_string($value)) {
            throw new RuntimeException(sprintf(
                'Expected %s to be a string, got %s.',
                $subject,
                get_debug_type($value),
            ));
        }

        return $value;
    }
}

if (! function_exists('mustBeArray')) {
    /**
     * Narrow a value to an array, failing by name when it is not one.
     *
     * @param  mixed  $value  The value to narrow.
     * @param  string  $subject  What the value is, named for the failure message.
     * @return array<array-key, mixed> The value, typed as an array.
     */
    function mustBeArray(mixed $value, string $subject): array
    {
        if (! is_array($value)) {
            throw new RuntimeException(sprintf(
                'Expected %s to be an array, got %s.',
                $subject,
                get_debug_type($value),
            ));
        }

        return $value;
    }
}

if (! function_exists('mustBeStringKeyed')) {
    /**
     * Narrow a value to an array keyed only by strings, failing by name when it is not one.
     *
     * @param  mixed  $value  The value to narrow.
     * @param  string  $subject  What the value is, named for the failure message.
     * @return array<string, mixed> The value, typed as a string-keyed array.
     */
    function mustBeStringKeyed(mixed $value, string $subject): array
    {
        $array = mustBeArray($value, $subject);
        $narrowed = [];

        foreach ($array as $key => $item) {
            if (! is_string($key)) {
                throw new RuntimeException(sprintf('Expected every key of %s to be a string, got %s.', $subject, get_debug_type($key)));
            }

            $narrowed[$key] = $item;
        }

        return $narrowed;
    }
}

if (! function_exists('mustExist')) {
    /**
     * Narrow a nullable value to its non-null type, failing by name when absent.
     *
     * Replaces `\assert($x !== null)`. Several call sites paired that with an
     * `expect($x)->not->toBeNull()` immediately above; the expectation was the
     * real check and the assertion existed only to satisfy PHPStan, so both
     * collapse into this one call.
     *
     * @template TValue
     *
     * @param  TValue|null  $value  The value to narrow.
     * @param  string  $subject  What the value is, named for the failure message.
     * @return TValue The value, typed as non-null.
     */
    function mustExist(mixed $value, string $subject): mixed
    {
        if ($value === null) {
            throw new RuntimeException(sprintf('Expected %s to exist, got null.', $subject));
        }

        return $value;
    }
}

if (! function_exists('mustNotBeFalse')) {
    /**
     * Narrow a `string|false` result to a string, failing by name on `false`.
     *
     * Replaces `\assert($x !== false)` around `realpath()` / `file_get_contents()`,
     * whose `false` means "the fixture path or file is missing", a cause worth
     * naming, since the next line otherwise fails on an empty string.
     *
     * @param  string|false  $value  The result to narrow.
     * @param  string  $subject  What the value is, named for the failure message.
     * @return string The value, typed as a string.
     */
    function mustNotBeFalse(string|false $value, string $subject): string
    {
        if ($value === false) {
            throw new RuntimeException(sprintf('Expected %s to resolve, got false.', $subject));
        }

        return $value;
    }
}
