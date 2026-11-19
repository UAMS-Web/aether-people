<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests\Support;

use UAMSWeb\Aether\People\Support\RoleTitleSchemaOutputComputer;
use UAMSWeb\Aether\People\Support\RoleTitleValue;

/**
 * Derives the `role_title` schema-output matrix from `config/role_title.php`.
 *
 * The matrix exists to prove that a migration touching the honorific guards changed
 * no output. It is derived rather than enumerated so that a schema rule added later
 * is covered without a test edit, see
 * `tests/Integration/Support/RoleTitleSchemaMatrixTest.php` for the assertion and the
 * written-down inversion control.
 *
 * Config is untyped at its boundary, so every read here narrows before use rather
 * than casting: a malformed row is skipped, never guessed at.
 */
final class RoleTitleSchemaMatrix
{
    /**
     * The `title_options` keys whose presence the guards test.
     *
     * @var list<string>
     */
    public const array HONORIFIC_KEYS = ['emeritus', 'emerita'];

    /**
     * The scalar condition fields, in the order they appear in a case descriptor.
     *
     * @var list<string>
     */
    private const array SCALAR_CONDITION_FIELDS = [
        'role',
        'hierarchical_modifier',
        'positional_qualifier',
        'object',
    ];

    /**
     * Narrow an untyped config value to a list of string-keyed rows.
     *
     * The filtering is what MAKES the declared type true rather than asserting it:
     * a non-array row is dropped, and a non-string key inside a row is dropped, so
     * the returned shape is enforced rather than promised.
     *
     * @param  mixed  $value  A configured list of rows
     * @return list<array<string, mixed>> Rows carrying string keys only
     */
    private static function arrayRows(mixed $value): array
    {
        // A missing or malformed list contributes no rows
        if (! \is_array($value)) {
            return [];
        }

        // Collect the rows that survive narrowing
        $rows = [];

        // Walk each candidate row
        foreach ($value as $row) {
            // Skip entries that are not themselves rows
            if (! \is_array($row)) {
                continue;
            }

            // Keep string-keyed entries so the declared shape holds
            $narrowed = [];

            foreach ($row as $key => $entry) {
                if (\is_string($key)) {
                    $narrowed[$key] = $entry;
                }
            }

            // Record the narrowed row
            $rows[] = $narrowed;
        }

        // Return the narrowed rows in order
        return $rows;
    }

    /**
     * Narrow an untyped config value to a list of strings.
     *
     * @param  mixed  $value  A configured `value` entry, scalar or list
     * @return list<string> Only the string members, in order
     */
    private static function stringList(mixed $value): array
    {
        // A bare string is a one-element list
        if (\is_string($value)) {
            return [$value];
        }

        // Anything that is not a list of values contributes nothing
        if (! \is_array($value)) {
            return [];
        }

        // Keep string members only, discarding keys
        return \array_values(\array_filter($value, \is_string(...)));
    }

    /**
     * The configured schema rules, with non-array rows discarded.
     *
     * @return list<array<string, mixed>> Schema rows in config order
     */
    public static function schemaRules(): array
    {
        // Read the rows straight from configuration so the matrix tracks the config
        $rules = config('role_title.schema');

        // Narrow to string-keyed rows; a malformed key yields no rules
        return self::arrayRows($rules);
    }

    /**
     * The `title_options` condition rows for one rule.
     *
     * @param  array<string, mixed>  $rule  A schema rule
     * @return list<array<string, mixed>> Condition rows, malformed entries discarded
     */
    private static function honorificConditions(array $rule): array
    {
        // Read this rule's conditions map
        $conditions = $rule['conditions'] ?? null;

        // A rule without a conditions map constrains nothing
        if (! \is_array($conditions)) {
            return [];
        }

        // Read the `honorific` entry from that map. The honorific left `title_options`
        // for a single-choice radios slot (UAMS-Web/uams-statamic-archive#2138), so every condition guarding it now
        // names this field directly -- an `equals => null` guard or an `equals_any`
        // positive -- rather than a `not_contains_any` / `contains_any` over the
        // checkbox map.
        $rows = $conditions['honorific'] ?? null;

        // Narrow to string-keyed condition rows
        return self::arrayRows($rows);
    }

    /**
     * Schema rules carrying at least one honorific-touching `title_options` condition,
     * keyed by their index in config order.
     *
     * @return array<int, array<string, mixed>> Config index to the schema rule
     */
    public static function honorificGuardedRules(): array
    {
        // Collect the rules whose output a honorific migration could move
        $guarded = [];

        // Walk the configured schema rows in config order
        foreach (self::schemaRules() as $index => $rule) {
            // Keep the rule as soon as it carries a `honorific` condition
            if (self::honorificConditions($rule) !== []) {
                $guarded[$index] = $rule;
            }
        }

        // Return the guarded rules keyed by config index
        return $guarded;
    }

    /**
     * Count the honorific-touching conditions by operator.
     *
     * Both directions matter: a rule that should fire and stops firing is as much a
     * regression as one that starts, so the positive `contains_any` conditions are
     * counted alongside the negative `not_contains_any` guards.
     *
     * @return array<string, int> Operator to the number of honorific-touching conditions
     */
    public static function honorificOperatorCensus(): array
    {
        // Tally one entry per honorific-touching condition
        $census = [];

        // Walk every rule, not only the guarded ones, so nothing is assumed
        foreach (self::schemaRules() as $rule) {
            foreach (self::honorificConditions($rule) as $condition) {
                // Read the operator, discarding a malformed row rather than casting it
                $operator = $condition['operator'] ?? null;

                if (! \is_string($operator)) {
                    continue;
                }

                // Record this operator against the running tally
                $census[$operator] = ($census[$operator] ?? 0) + 1;
            }
        }

        // Sort for a stable comparison in the test
        \ksort($census);

        // Return the operator census
        return $census;
    }

    /**
     * The configured `role_title` configuration handles.
     *
     * @return list<string> Stored configuration handles in config order
     */
    public static function configurationHandles(): array
    {
        // Read the ordered handle list from configuration and narrow it
        return self::stringList(config('role_title.configurations.handles'));
    }

    /**
     * The four honorific selections that can flip a guard, in fixture order.
     *
     * A `not_contains_any` guard passes only under `none`; the three others each
     * make it fail, which is what makes them the axis worth crossing.
     *
     * @return array<string, string|null> Case label to the honorific radios value
     */
    public static function honorificStates(): array
    {
        return [
            'none' => null,
            'emeritus' => 'emeritus',
            'emerita' => 'emerita',
            // The radios slot cannot hold both gendered forms. The former
            // both-checkboxes case collapses to a single honorific, and its schema
            // output is identical to `emeritus` because every guard tests presence,
            // not which form -- so the recorded `both` cases still reproduce.
            'both' => 'emeritus',
        ];
    }

    /**
     * A short, stable descriptor naming the rule a case exercises.
     *
     * The fixture is diffed by a human when it moves, so the label carries the
     * conditions that select the rule rather than only its index.
     *
     * @param  int  $index  Position of the rule in config order
     * @param  array<string, mixed>  $rule  The schema rule
     */
    public static function describeRule(int $index, array $rule): string
    {
        // Start the descriptor with the config-order index
        $parts = ['#'.$index];

        // Read this rule's conditions map, if it has one
        $conditions = $rule['conditions'] ?? null;

        // Append each scalar selector that distinguishes this rule
        if (\is_array($conditions)) {
            foreach (self::SCALAR_CONDITION_FIELDS as $field) {
                // Read the field's condition rows
                $rows = $conditions[$field] ?? null;

                // Skip fields this rule does not constrain
                if (! \is_array($rows)) {
                    continue;
                }

                // Read the first row, which carries the selector
                $condition = reset($rows);

                if (! \is_array($condition)) {
                    continue;
                }

                // Render the configured value compactly
                $value = $condition['value'] ?? null;
                $strings = self::stringList($value);
                $rendered = $strings === [] ? 'null' : \implode('|', $strings);

                // Record the field and its selector
                $parts[] = $field.'='.$rendered;
            }
        }

        // Join the descriptor parts into one label
        return \implode(' ', $parts);
    }

    /**
     * Build a normalized payload that satisfies a rule's non-honorific conditions.
     *
     * @param  array<string, mixed>  $rule  The schema rule to reach
     * @return array<string, mixed> Normalized `role_title` payload
     */
    private static function basePayloadForRule(array $rule): array
    {
        // Start every checkbox key false so only conditions turn one on
        $titleOptions = RoleTitleValue::emptyTitleOptionsPayload();

        // Start the scalar selectors unset
        $scalars = [
            'role' => null,
            'hierarchical_modifier' => null,
            'positional_qualifier' => null,
            'object' => null,
        ];

        // Read this rule's conditions map
        $conditions = $rule['conditions'] ?? null;

        // A rule without conditions is reached by the empty payload
        if (\is_array($conditions)) {
            foreach ($conditions as $field => $rows) {
                // Skip malformed condition lists rather than guessing at them
                if (! \is_string($field) || ! \is_array($rows)) {
                    continue;
                }

                foreach ($rows as $condition) {
                    // Skip malformed rows
                    if (! \is_array($condition)) {
                        continue;
                    }

                    // Read the operator, skipping rows that do not name one
                    $operator = $condition['operator'] ?? null;

                    if (! \is_string($operator)) {
                        continue;
                    }

                    // Narrow the configured value once for both branches
                    $values = self::stringList($condition['value'] ?? null);

                    // `title_options` is the only checkbox map, so it is set by key
                    if ($field === 'title_options') {
                        // A positive condition turns its keys on; a negative one leaves them off
                        if ($operator === 'contains_all') {
                            foreach ($values as $key) {
                                $titleOptions[$key] = true;
                            }
                        } elseif ($operator === 'contains_any' && $values !== []) {
                            $titleOptions[$values[0]] = true;
                        }

                        continue;
                    }

                    // Every other condition field is a scalar selector
                    if (\array_key_exists($field, $scalars)) {
                        $scalars[$field] = $values === [] ? null : $values[0];
                    }
                }
            }
        }

        // Assemble the payload in the shape the computer consumes
        return $scalars + ['title_options' => $titleOptions];
    }

    /**
     * Build the full matrix: every honorific-guarded rule, crossed with every
     * configuration and every honorific state.
     *
     * @return array<string, array<string, mixed>> Case label to computed schema output
     */
    public static function build(): array
    {
        // Accumulate one entry per case, keyed by a label naming what it exercises
        $matrix = [];

        // Walk the rules a honorific migration could move
        foreach (self::honorificGuardedRules() as $index => $rule) {
            // Derive the payload that reaches this rule
            $base = self::basePayloadForRule($rule);

            // Name the rule once for every case built from it
            $descriptor = self::describeRule($index, $rule);

            // Cross the rule with every configured configuration
            foreach (self::configurationHandles() as $configuration) {
                // Cross again with each honorific selection
                foreach (self::honorificStates() as $stateLabel => $honorific) {
                    // Copy the base payload and drive the honorific radios slot to this
                    // state. The base already carries any residual `title_options` guard
                    // for a mixed rule (e.g. `senior`); only the honorific axis moves here.
                    $payload = $base;
                    $payload['honorific'] = $honorific;
                    $payload['configuration'] = $configuration;

                    // Record the computed output under a label naming rule, configuration and state
                    $label = $descriptor.' :: '.$configuration.' :: '.$stateLabel;
                    $matrix[$label] = RoleTitleSchemaOutputComputer::computeFromNormalizedPayload($payload);
                }
            }
        }

        // Return the assembled matrix in deterministic config order
        return $matrix;
    }
}
