<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use UAMSWeb\Aether\People\Support\RoleTitleDefinition;
use UAMSWeb\Aether\People\Support\RoleTitleScopeUnit;

/**
 * Validates a `role_title` scope's joiner against the article it composes with (UAMS-Web/uams-statamic-archive#2546).
 *
 * A joiner that declares `requires_article` (`in`) introduces only units, so a
 * TYPED scope using it must choose `the`: the corpus has no article-less `in`
 * to compose. A linked scope always takes the article and passes, and so does a
 * scope with no typed name, since it composes no phrase at all.
 */
final class RoleTitleScopeJoinerRule implements DataAwareRule, ValidationRule
{
    /**
     * Full request data bag, for the sibling article, name and link.
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Create a rule bound to the parent `role_title` path.
     *
     * @param  string  $parentFieldPath  Dotted path to the role_title value (e.g.
     *                                   `title` or `job_title.0.title`)
     */
    public function __construct(
        private readonly string $parentFieldPath
    ) {}

    /**
     * Receive the full validation data bag for `DataAwareRule`.
     *
     * @param  array<string, mixed>  $data  Request data including the parent field
     */
    public function setData(
        array $data
    ): static {
        // Store the bag so `validate()` can read the scope's siblings
        $this->data = $data;

        return $this;
    }

    /**
     * Refuse a typed scope that uses an article-requiring joiner without `the`.
     *
     * @param  string  $attribute  Dotted validation attribute path
     * @param  mixed  $value  Posted joiner storage key
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Only a joiner that declares it requires the article is checked
        if (! \is_string($value) || ! \in_array(
            \trim($value),
            RoleTitleDefinition::instance()->articleRequiringOptionKeysForNestedField(
                RoleTitleScopeUnit::GROUP_KEY,
                'scope_joiner'
            ),
            // Equivalent: joiner keys are words such as `in`, never numeric strings,
            // so loose comparison matches exactly as strict comparison does.
            // @pest-mutate-ignore: TrueToFalse
            true
        )) {
            return;
        }

        $scopePath = $this->parentFieldPath.'.'.RoleTitleScopeUnit::GROUP_KEY;

        // A linked unit always takes the article
        if (RoleTitleScopeUnit::normalizeId(Arr::get($this->data, $scopePath.'.'.RoleTitleScopeUnit::NESTED_KEY)) !== null) {
            return;
        }

        // A scope with no typed name composes no phrase to check
        $name = Arr::get($this->data, $scopePath.'.scope_name');

        if (! \is_string($name) || \trim($name) === '') {
            return;
        }

        // A typed scope passes only when it chose the article
        $article = Arr::get($this->data, $scopePath.'.scope_article');

        if (\is_string($article) && \trim($article) === 'the') {
            return;
        }

        $fail('aether-people::fieldtypes.role_title.validation.scope_joiner_requires_article')->translate();
    }
}
