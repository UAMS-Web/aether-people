<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Duplicates;

/**
 * Minimal disjoint-set (union-find) over entry ids, used by {@see PeopleDuplicateDetector}
 * to merge entries linked by any shared signal into connected clusters.
 *
 * Path compression keeps `find` near-constant; the structure is intentionally tiny
 * and internal to duplicate detection.
 */
final class PeopleDuplicateUnionFind
{
    /**
     * Parent pointer per element; a root points at itself.
     *
     * @var array<string, string>
     */
    private array $parent = [];

    /**
     * @param  list<string>  $elements  Initial elements, each its own singleton set.
     */
    public function __construct(array $elements)
    {
        foreach ($elements as $element) {
            $this->parent[$element] = $element;
        }
    }

    /**
     * Find the representative root of an element's set (with path compression).
     */
    public function find(string $element): string
    {
        // Unknown elements are their own root
        if (! isset($this->parent[$element])) {
            return $element;
        }

        // Walk to the root, then compress the path so future lookups are flat
        $root = $element;
        // Reading the comparison as identity instead of non-identity makes this loop endless, so a mutation run
        // can only time out on it.
        // @pest-mutate-ignore: NotIdenticalToIdentical
        while ($this->parent[$root] !== $root) {
            $root = $this->parent[$root];
        }

        // Reading the comparison as identity instead of non-identity makes this loop endless, so a mutation run
        // can only time out on it.
        // @pest-mutate-ignore: NotIdenticalToIdentical
        while ($this->parent[$element] !== $root) {
            $next = $this->parent[$element];
            $this->parent[$element] = $root;
            $element = $next;
        }

        return $root;
    }

    /**
     * Merge the sets containing the two elements.
     *
     * An element not yet in the structure joins it first as its own singleton, so
     * every parent pointer this writes targets a key and `groups()` reports both
     * elements (#682). `find()` alone still adds nothing.
     */
    public function union(string $a, string $b): void
    {
        // Register unknown elements so the root written below is always a key
        $this->parent[$a] ??= $a;
        $this->parent[$b] ??= $b;

        $rootA = $this->find($a);
        $rootB = $this->find($b);

        // Already in the same set
        if ($rootA === $rootB) {
            return;
        }

        // Point one root at the other
        $this->parent[$rootA] = $rootB;
    }

    /**
     * Group every element by its set root.
     *
     * @return array<string, list<string>> Root id => member ids.
     */
    public function groups(): array
    {
        $groups = [];

        foreach (array_keys($this->parent) as $element) {
            $groups[$this->find($element)][] = $element;
        }

        return $groups;
    }
}
