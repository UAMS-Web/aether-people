<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

/**
 * Maps `config/role_title.php` `schema` scenarios to stored field output for the
 * `role_title` fieldtype (`RoleTitle::process()`).
 *
 * The first scenario whose `conditions` all pass wins; each value under that row’s
 * `output` is normalized for persistence. There is no hardcoded handle list or
 * union-wide null padding: an unmatched payload yields an empty array, and a
 * matched row returns only the keys present in that row’s `output`.
 */
final class RoleTitleSchemaOutputComputer
{
    /**
     * Build null placeholders for every output handle discovered across schema rows
     * (merged into CP empty templates and preload meta).
     *
     * @return array<string, null> Output handle to null for default stored shape
     */
    public static function emptyPlaceholderSlice(): array
    {
        // Initialize the placeholder map for discovered output handles
        $out = [];

        // Record one null entry per configured output handle
        foreach (self::storedOutputSubfieldKeys() as $key) {
            $out[$key] = null;
        }

        // Unset local variables to free up memory
        unset(
            $key
        );

        // Return the placeholder map for discovered output handles
        return $out;
    }

    /**
     * Collect every distinct top-level `output` key across schema scenarios in
     * first-seen order.
     *
     * @return list<string> Ordered union of output handles in first-seen order
     */
    public static function storedOutputSubfieldKeys(): array
    {
        // Track handles already emitted for stable first-seen ordering
        $seen = [];

        // Collect ordered unique handles while scanning scenarios
        $ordered = [];

        // Scan each schema scenario row in config file order
        foreach (RoleTitleDefinition::instance()->schemaScenarioRows() as $row) {
            // Read the configured output map when present
            $output = $row['output'] ?? null;

            // Skip rows without an output associative map
            if (! \is_array($output)) {
                continue;
            }

            // Walk each output handle for this scenario
            foreach (\array_keys($output) as $handle) {
                // Keep string handles only
                if (! \is_string($handle)) {
                    continue;
                }

                // Skip handles already recorded from an earlier scenario
                if (isset($seen[$handle])) {
                    continue;
                }

                // Mark the handle seen and append to the ordered union
                // isset() is true for false as well, so the stored flag's value does not matter
                // @pest-mutate-ignore: TrueToFalse
                $seen[$handle] = true;
                $ordered[] = $handle;
            }

            // Unset local variables to free up memory
            unset(
                $handle
            );
        }

        // Unset local variables to free up memory
        unset(
            $seen
        );

        // Return the ordered union of output handles
        return $ordered;
    }

    /**
     * Whether a `conditions` map key names a top-level `checkboxes` subfield from
     * `config/role_title.php`.
     *
     * @param  string  $subfieldKey  Stored subfield name under `conditions`
     * @return bool True when the key matches a checkbox-map subfield
     */
    private static function conditionSubfieldIsCheckboxMap(
        string $subfieldKey
    ): bool {
        // Delegate membership to the definition’s checkbox subfield list
        return \in_array(
            $subfieldKey,
            RoleTitleValue::checkboxSubfieldKeys(),
            true
        );
    }

    /**
     * Resolve normalized `output` entries for the first matching schema scenario.
     *
     * @param  array<string, mixed>  $data  Normalized role_title payload after optional-radio and checkbox coercion
     * @return array<string, mixed> Normalized `output` slice for the first match, or an empty array when no
     *                              scenario matches
     */
    public static function computeFromNormalizedPayload(
        array $data
    ): array {
        // Scan schema scenarios until one matches every condition group
        foreach (RoleTitleDefinition::instance()->schemaScenarioRows() as $row) {
            // Read conditions and output for this scenario row
            /** @var array<string, mixed>|mixed $conditions */
            $conditions = $row['conditions'] ?? null;
            $output = $row['output'] ?? null;

            // Skip malformed rows missing either side
            if (
                ! \is_array($conditions)
                || ! \is_array($output)
            ) {
                // Unset local variables to free up memory
                unset(
                    $conditions,
                    $output
                );

                // Continue to the next scenario
                continue;
            }

            // Narrow the guarded conditions map to the expected shape
            /** @var array<string, mixed> $conditions */

            // Check if every condition group passes for this payload
            if (! self::schemaConditionsPass(
                $data,
                $conditions
            )) {
                // Unset local variables to free up memory
                unset(
                    $conditions,
                    $output
                );

                // Continue to the next scenario
                continue;
            }

            // Build normalized output entries for this scenario only
            $normalized = [];

            // Normalize each string handle present in this row’s output map
            foreach ($output as $handle => $node) {
                // Skip non-string handles defensively
                if (! \is_string($handle)) {
                    // Continue to the next handle
                    continue;
                }

                // Normalize the subtree for storage
                $normalized[$handle] = self::normalizeSchemaOutputStructure($node);
            }

            // Unset local variables to free up memory
            unset(
                $conditions,
                $handle,
                $node,
                $output,
                $row
            );

            // Return the normalized output entries for this scenario
            return $normalized;
        }

        // Return an empty array when no scenario matches
        return [];
    }

    /**
     * Require every `conditions` subfield group and rule to pass for the payload.
     *
     * @param  array<string, mixed>  $data  Normalized role_title payload
     * @param  array<string, mixed>  $conditions  `schema.*.conditions` map
     * @return bool True when all rules pass
     */
    private static function schemaConditionsPass(
        array $data,
        array $conditions
    ): bool {
        // Evaluate each subfield’s rule list with AND semantics
        foreach ($conditions as $subfieldKey => $rules) {
            // Reject non-array rule lists
            if (! \is_array($rules)) {
                // Unset local variables to free up memory
                unset(
                    $rules,
                    $subfieldKey
                );

                // Fail this scenario
                return false;
            }

            // Evaluate each rule row inside this subfield group
            foreach ($rules as $rule) {
                // Reject malformed rule rows
                // The body unsets $rule before returning, so falling through reads no operator and fails below too (RemoveEarlyReturn is equivalent)
                // @pest-mutate-ignore: RemoveEarlyReturn
                if (! \is_array($rule)) {
                    // Unset local variables to free up memory
                    unset(
                        $rule,
                        $rules,
                        $subfieldKey
                    );

                    // Fail this scenario
                    return false;
                }

                // Read operator and expected value from the rule row
                $operator = $rule['operator'] ?? null;
                $expected = $rule['value'] ?? null;

                // Reject rules without a non-empty string operator
                // An empty operator matches no rule below either (EmptyStringToNotEmpty is equivalent)
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                if (
                    ! \is_string($operator)
                    || $operator === ''
                ) {
                    // Unset local variables to free up memory
                    unset(
                        $expected,
                        $operator,
                        $rule,
                        $rules,
                        $subfieldKey
                    );

                    // Fail this scenario
                    return false;
                }

                // Resolve the stored value used for this condition subfield
                $actual = self::actualForConditionKey(
                    $data,
                    (string) $subfieldKey
                );

                // Fail this scenario when the rule does not hold
                if (! self::ruleMatches(
                    $actual,
                    $operator,
                    $expected,
                    (string) $subfieldKey
                )) {
                    // Unset local variables to free up memory
                    unset(
                        $actual,
                        $expected,
                        $operator,
                        $rule,
                        $rules,
                        $subfieldKey
                    );

                    // Fail this scenario
                    return false;
                }

                // Unset local variables to free up memory
                unset(
                    $actual,
                    $expected,
                    $operator,
                    $rule
                );
            }

            // Unset local variables to free up memory
            unset(
                $rules,
                $subfieldKey
            );
        }

        // Pass this scenario
        return true;
    }

    /**
     * Read the stored value or active checkbox keys used for one condition key.
     *
     * @param  array<string, mixed>  $data  Normalized role_title payload
     * @param  string  $subfieldKey  Key under `schema.*.conditions`
     * @return mixed Non-empty string, null for cleared optional radios, or a list of
     *               active checkbox keys in map iteration order
     */
    private static function actualForConditionKey(
        array $data,
        string $subfieldKey
    ): mixed {
        // Resolve checkbox maps via the definition’s checkbox subfield list
        if (self::conditionSubfieldIsCheckboxMap($subfieldKey)) {
            // Read the checkbox boolean map when present
            $map = $data[$subfieldKey] ?? null;

            // Treat a missing map as no active options
            if (! \is_array($map)) {
                // Unset local variables to free up memory
                unset(
                    $map
                );

                // Return an empty list when the map is missing
                return [];
            }

            // Collect truthy checkbox keys in iteration order
            $active = [];

            foreach ($map as $optionKey => $on) {
                // Skip keys that are not strings
                if (! \is_string($optionKey)) {
                    // Continue to the next option key
                    continue;
                }

                // Append keys that are enabled in the stored payload
                if (
                    in_array($on, [true, 1, '1'], true)
                ) {
                    // Append the option key to the active list
                    $active[] = $optionKey;
                }
            }

            // Unset local variables to free up memory
            unset(
                $map,
                $on,
                $optionKey
            );

            // Return the active checkbox keys
            return $active;
        }

        // Read scalar subfield storage when present
        $raw = $data[$subfieldKey] ?? null;

        // Treat absent storage as null for optional radios and text
        if ($raw === null) {
            // Unset local variables to free up memory
            unset(
                $raw
            );

            // Return null when the storage is absent
            return null;
        }

        // Normalize optional string radios and text to null when blank
        if (\is_string($raw)) {
            // Trim whitespace from stored string values
            $trimmed = \trim($raw);

            // Unset local variables to free up memory
            unset(
                $raw
            );

            // Return null when the storage is blank
            return $trimmed === '' ? null : $trimmed;
        }

        // Unset local variables to free up memory
        unset(
            $raw
        );

        // Return null when the storage is absent
        return null;
    }

    /**
     * Evaluate one condition rule against the resolved actual value.
     *
     * @param  mixed  $actual  Scalar, null, or list-shaped checkbox actuals
     * @param  string  $operator  Operator name from the schema rule row
     * @param  mixed  $expected  Operand stored under `value`
     * @param  string  $subfieldKey  Condition subfield handle
     * @return bool True when the operator holds
     */
    private static function ruleMatches(
        mixed $actual,
        string $operator,
        mixed $expected,
        string $subfieldKey
    ): bool {
        // Route checkbox-map actuals through set-style operators
        if (self::conditionSubfieldIsCheckboxMap($subfieldKey)) {
            // Reject non-list checkbox actual shapes
            // actualForConditionKey() always returns a list for a checkbox key, so this guard is unreachable
            // @pest-mutate-ignore: BooleanOrToBooleanAnd, FalseToTrue, RemoveEarlyReturn
            if (
                ! \is_array($actual)
                || ! \array_is_list($actual)
            ) {
                // Fail this scenario
                return false;
            }

            // Narrow the guarded actual to the active checkbox key list shape
            /** @var list<string> $actual */
            // Evaluate list-equality and subset operators for checkbox keys
            return self::checkboxMapRuleMatches(
                $actual,
                $operator,
                $expected
            );
        }

        // Evaluate scalar operators for radios, text, and similar subfields
        return self::scalarRuleMatches(
            $actual,
            $operator,
            $expected
        );
    }

    /**
     * Evaluate set-style operators for checkbox-map actuals.
     *
     * @param  list<string>  $activeKeys  Truthy checkbox option keys
     * @param  string  $operator  Operator name from the schema rule row
     * @param  mixed  $expected  Operand stored under `value`
     * @return bool True when the operator holds for the active key set
     */
    private static function checkboxMapRuleMatches(
        array $activeKeys,
        string $operator,
        mixed $expected
    ): bool {
        // Parse list operands as strict string lists from config
        $valueList = self::conditionStringListOperand($expected);

        // Reject operands that are not valid string lists
        if ($valueList === null) {
            // Unset local variables to free up memory
            unset(
                $valueList
            );

            // Fail this scenario
            return false;
        }

        // Build a membership set for the active checkbox keys
        $set = [];

        // Iterate over the active checkbox keys and add them to the membership set
        foreach ($activeKeys as $key) {
            // Add the option key to the membership set
            // isset() is true for false as well, so the stored flag's value does not matter
            // @pest-mutate-ignore: TrueToFalse
            $set[$key] = true;
        }

        // Dispatch operators supported on checkbox key sets
        return match ($operator) {
            'contains_any' => self::setIntersectsList(
                $set,
                $valueList
            ),
            'not_contains_any' => ! self::setIntersectsList(
                $set,
                $valueList
            ),
            'contains_all' => self::listIsSubsetOfSet(
                $valueList,
                $set
            ),
            'not_contains_all' => ! self::listIsSubsetOfSet(
                $valueList,
                $set
            ),
            'equals' => self::setsEqual(
                $activeKeys,
                $valueList
            ),
            'not_equals' => ! self::setsEqual(
                $activeKeys,
                $valueList
            ),
            default => false,
        };
    }

    /**
     * Evaluate scalar and list-operand operators for non-checkbox subfields.
     *
     * @param  mixed  $actual  Normalized scalar storage (typically string|null)
     * @param  string  $operator  Operator name from the schema rule row
     * @param  mixed  $expected  Operand stored under `value`
     * @return bool True when the operator holds for the scalar actual
     */
    private static function scalarRuleMatches(
        mixed $actual,
        string $operator,
        mixed $expected
    ): bool {
        // Parse string-list operands shared by several operators
        $list = self::conditionStringListOperand($expected);

        // Dispatch scalar equality and membership operators
        return match ($operator) {
            'equals' => $actual === $expected,
            'not_equals' => $actual !== $expected,
            'equals_any' => $list !== null
                && \in_array(
                    $actual,
                    $list,
                    true
                ),
            'not_equals_any' => $list !== null
                && ! \in_array(
                    $actual,
                    $list,
                    true
                ),
            'equals_all' => $list !== null
                && self::scalarEqualsAll(
                    $actual,
                    $list
                ),
            'not_equals_all' => $list !== null
                && ! self::scalarEqualsAll(
                    $actual,
                    $list
                ),
            default => false,
        };
    }

    /**
     * Require every operand string to equal the same actual scalar.
     *
     * @param  mixed  $actual  Stored scalar or null
     * @param  list<string>  $values  Operand strings from the rule row
     * @return bool True when every operand matches the actual value
     */
    private static function scalarEqualsAll(
        mixed $actual,
        array $values
    ): bool {
        // Treat empty operand lists as failing `equals_all`
        if ($values === []) {
            // Unset local variables to free up memory
            unset(
                $values
            );

            // Fail this scenario
            return false;
        }

        // Fail when any operand differs from the actual scalar
        foreach ($values as $v) {
            // Fail this scenario when any operand differs from the actual scalar
            if ($actual !== $v) {
                // Unset local variables to free up memory
                unset(
                    $v
                );

                // Fail this scenario
                return false;
            }
        }

        // Unset local variables to free up memory
        unset(
            $v,
            $values
        );

        // Pass this scenario
        return true;
    }

    /**
     * Parse a strict string list operand from a rule row `value`.
     *
     * @param  mixed  $expected  Raw `value` payload from config
     * @return list<string>|null Non-null only when `$expected` is a list of strings
     */
    private static function conditionStringListOperand(
        mixed $expected
    ): ?array {
        // Require an array operand for list-style rules
        if (! \is_array($expected)) {
            return null;
        }

        // Initialize an empty dense string list
        $out = [];

        // Iterate over the expected list and build a dense string list
        foreach ($expected as $item) {
            // Reject operands that include non-string elements
            if (! \is_string($item)) {
                // Unset local variables to free up memory
                unset(
                    $item
                );

                // Return null when the operand is not a string
                return null;
            }

            // Append the item to the dense string list
            $out[] = $item;
        }

        // Unset local variables to free up memory
        unset(
            $item
        );

        // Return the dense string list
        return $out;
    }

    /**
     * Whether the active set intersects the operand string list.
     *
     * @param  array<string, true>  $set  Map of active checkbox keys
     * @param  list<string>  $needles  Operand strings from the rule row
     * @return bool True when at least one needle is active
     */
    private static function setIntersectsList(
        array $set,
        array $needles
    ): bool {
        // Stop at the first needle present in the active set
        foreach ($needles as $needle) {
            // Check if the needle is present in the active set
            if (isset($set[$needle])) {
                // Unset local variables to free up memory
                unset(
                    $needle
                );

                // Return true to signal a match
                return true;
            }
        }

        // Unset local variables to free up memory
        unset(
            $needle
        );

        // Return false to signal no match
        return false;
    }

    /**
     * Whether every operand string exists in the active set.
     *
     * @param  list<string>  $needles  Operand strings from the rule row
     * @param  array<string, true>  $set  Map of active checkbox keys
     * @return bool True when all needles are active
     */
    private static function listIsSubsetOfSet(
        array $needles,
        array $set
    ): bool {
        // Fail subset checks when any needle is missing from the active set
        foreach ($needles as $needle) {
            // Check if the needle is present in the active set
            if (! isset($set[$needle])) {
                // Unset local variables to free up memory
                unset(
                    $needle
                );

                // Fail this scenario when any needle is missing from the active set
                return false;
            }
        }

        // Unset local variables to free up memory
        unset(
            $needle
        );

        // Pass this scenario
        return true;
    }

    /**
     * Compare two string multisets for equality ignoring order.
     *
     * @param  list<string>  $a  First operand list (typically active keys)
     * @param  list<string>  $b  Second operand list from config
     * @return bool True when the sorted lists match
     */
    private static function setsEqual(
        array $a,
        array $b
    ): bool {
        // ---
        // Sort copies so order differences do not affect equality
        // ---

        // Initialize the left and right lists
        $left = $a;
        $right = $b;

        // Sort the left list
        \sort(
            $left,
            \SORT_STRING
        );

        // Sort the right list
        \sort(
            $right,
            \SORT_STRING
        );

        // Return true when the sorted lists match
        return $left === $right;
    }

    /**
     * Normalize a subtree using the same rules as a matched schema row’s `output`
     * (dense string lists, recursive maps).
     *
     * Used for unstructured `occupation` / `occupational_field` storage so their
     * shape matches augmented structured schema output.
     *
     * @param  mixed  $raw  User or stored slice under `occupation` or `occupational_field`
     * @return mixed Normalized subtree safe for YAML storage
     */
    public static function normalizeSchemaOutputFragment(
        mixed $raw
    ): mixed {
        return self::normalizeSchemaOutputStructure($raw);
    }

    /**
     * Normalize a schema `output` subtree for persistence by coercing list leaves to
     * dense string lists and recursing associative arrays.
     *
     * @param  mixed  $raw  Fragment from `schema.*.output`
     * @return mixed Normalized subtree safe for YAML storage
     */
    private static function normalizeSchemaOutputStructure(
        mixed $raw
    ): mixed {
        // Leave non-array fragments unchanged
        if (! \is_array($raw)) {
            return $raw;
        }

        // Preserve empty arrays without coercing children
        // An empty array is a list, and the list branch below returns [] for it too (RemoveEarlyReturn is equivalent)
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($raw === []) {
            return [];
        }

        // Coerce list-shaped arrays at any depth to dense string lists
        if (\array_is_list($raw)) {
            return self::normalizeStringList($raw);
        }

        // Initialize an empty associative map
        $out = [];

        // Recurse associative subtrees without interpreting key names
        foreach ($raw as $key => $value) {
            // Ignore non-string keys inside associative maps
            if (! \is_string($key)) {
                // Continue to the next key
                continue;
            }

            // Normalize the child subtree in key order
            $out[$key] = self::normalizeSchemaOutputStructure($value);
        }

        // Unset local variables to free up memory
        unset(
            $key,
            $value
        );

        // Return the normalized associative map
        return $out;
    }

    /**
     * Coerce a list-shaped config array to dense strings for storage.
     *
     * @param  array<int|string, mixed>  $list  List-shaped output fragment
     * @return list<string> Normalized string list
     */
    private static function normalizeStringList(
        array $list
    ): array {
        // Build a dense list of string tokens for list leaves
        $out = [];

        // Walk each list entry and coerce supported scalars to strings
        foreach ($list as $item) {
            // Skip null and boolean placeholders from partial config rows
            if ($item === null) {
                // Continue to the next item
                continue;
            }

            if (\is_bool($item)) {
                // Continue to the next item
                continue;
            }

            /** @var int|float|string $item */
            // Append string tokens, casting numeric scalars to strings when needed
            $out[] = \is_string($item)
                ? $item
                : (string) $item;
        }

        // Unset local variables to free up memory
        unset(
            $item
        );

        // Return the dense list of string tokens
        return $out;
    }
}
