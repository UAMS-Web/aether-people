<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Fieldtypes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use UAMSWeb\Aether\People\Rules\PersonNameDisplayFormatRule;
use UAMSWeb\Aether\People\Support\PersonNameDisplayFormats;

/**
 * Structured person name with text + phonetic text pairs, toggles, generational
 * suffix, and an optional display-format key (blueprint can hide that subfield).
 * The CP phonetic (IPA) instruction alert can also be toggled off per blueprint.
 * Nickname subfields can be hidden per blueprint (`include_nickname`), and so
 * can each part's phonetic text (`include_phonetic`), for a blueprint that keeps
 * the pronunciation in a field of its own (UAMS-Web/uams-statamic-archive#3186).
 *
 * @see UAMSWeb\Aether\People\Support\PersonNameDisplayFormats for the display-format options
 */
class PersonName extends Fieldtype
{
    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string
     */
    protected $component = 'person_name';

    /**
     * Indicates whether the fieldtype is selectable in collection blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in collection blueprints
     */
    // PCOV attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Flipped by hand, it fails "is offered in collection and form blueprints, with its component and icon".
    // @pest-mutate-ignore: TrueToFalse
    protected $selectable = true;

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    // PCOV attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Flipped by hand, it fails "is offered in collection and form blueprints, with its component and icon".
    // @pest-mutate-ignore: TrueToFalse
    protected $selectableInForms = true;

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'fieldtype-users';
    }

    /**
     * Blueprint editor: toggles for text and phonetic requirements.
     *
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        /** @var array<int, array<string, mixed>> $parent */
        $parent = parent::configFieldItems();

        $custom = [
            [
                'display' => __('Person name options'),
                'fields' => [
                    'require_text' => [
                        'type' => 'toggle',
                        'display' => __('Require text for each visible name part'),
                        'instructions' => __(
                            'When enabled, each visible text field must be filled.'
                        ),
                        'default' => true,
                    ],
                    'require_phonetic' => [
                        'type' => 'toggle',
                        'display' => __('Require phonetic text for each visible name part'),
                        'instructions' => __(
                            'When enabled, each visible phonetic text field must be filled.'
                        ),
                        'default' => false,
                    ],
                    'include_phonetic_instructions' => [
                        'type' => 'toggle',
                        'display' => __('Include phonetic text instructions'),
                        'instructions' => __(
                            'When enabled, the IPA help alert is shown at the top of the field. When disabled, that alert is hidden.'
                        ),
                        'default' => true,
                    ],
                    'include_display_format' => [
                        'type' => 'toggle',
                        'display' => __('Include preferred display format'),
                        'instructions' => __(
                            'When enabled, editors choose how the name is shown when all parts are available. When disabled, that control is hidden and stored display format is cleared on save.'
                        ),
                        'default' => true,
                    ],
                    'include_nickname' => [
                        'type' => 'toggle',
                        'display' => __('Include nickname'),
                        'instructions' => __(
                            'When enabled, editors can enter a nickname and related phonetic text. When disabled, those controls are hidden and nickname data is cleared on save.'
                        ),
                        'default' => true,
                    ],
                    'include_phonetic' => [
                        'type' => 'toggle',
                        'display' => __('Include phonetic text'),
                        'instructions' => __(
                            'When enabled, each name part has a phonetic text input beside it. When disabled, those inputs are hidden and phonetic text is cleared on save.'
                        ),
                        'default' => true,
                    ],
                ],
            ],
        ];

        return \array_merge(
            $parent,
            $custom
        );
    }

    /**
     * Preload config for the publish form (suffix options, flags).
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function preload(): array
    {
        $config = $this->config();

        /** @var array<string, mixed> $configArray */
        $configArray = \is_array($config) ? $config : [];

        return \array_merge(
            $configArray,
            [
                'generational_suffix_options' => PersonNameDisplayFormats::generationalSuffixSelectOptions(),
            ]
        );
    }

    /**
     * Extra data merged into the form-field template scope when this fieldtype
     * is rendered via `{{ form:create }}`. Surfaces the suffix options and
     * (best-effort) display-format options so the public template can loop
     * over them without duplicating PHP-side helpers.
     *
     * Statamic calls this from `RendersForms::getRenderableField` (vendor
     * `vendor/statamic/cms/src/Tags/Concerns/RendersForms.php:158`); the
     * returned keys become Antlers variables in the rendered template.
     *
     * `display_format_options` is best-effort: the CP recomputes the option
     * list reactively from the current name parts via
     * `PersonNameDisplayFormats::buildOptions`. On the public side we compute
     * it once at server-render time from whatever value the form starts with,
     * which means the list is empty on new submissions until a JS port of
     * `buildOptions` lands. The template surfaces this limitation in a small
     * help note under the select.
     *
     * @return array<string, mixed> Extra data keyed for template consumption.
     */
    #[\Override]
    public function extraRenderableFieldData(): array
    {
        $fieldValue = $this->field()?->value();
        /** @var array<string, mixed> $startingValue */
        $startingValue = \is_array($fieldValue) ? $fieldValue : [];

        return [
            'generational_suffix_options' => PersonNameDisplayFormats::generationalSuffixSelectOptions(),
            'display_format_options' => PersonNameDisplayFormats::buildOptions(
                PersonNameDisplayFormats::normalizeValue(
                    $startingValue
                )
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    public function rules(): array
    {
        return ['array'];
    }

    /**
     * Human-readable names for nested validation keys so Laravel `required_if`
     * and similar messages use readable :attribute and :other text instead of
     * raw dotted field paths.
     *
     * Labels are defined once in the add-on's `lang/en/fieldtypes.php`
     * (`aether-people::fieldtypes.person_name.field.*`) and reused in the CP Vue
     * fieldtype.
     *
     * @return array<string, string>
     */
    #[\Override]
    public function extraValidationAttributes(): array
    {
        $field = $this->field();

        if (! $field instanceof Field) {
            return [];
        }

        $handle = $field->handle();
        // Equivalent: a scalar handle concatenates to the same string with or without the cast.
        // @pest-mutate-ignore: RemoveStringCast
        $prefix = \is_scalar($handle) ? (string) $handle : '';
        $p = static fn (string $path): string => $prefix.'.'.$path;

        /** @var array<string, string> $relativePathToLangKey maps nested path => lang file key suffix */
        $relativePathToLangKey = [
            'no_middle_name' => 'no_middle_name',
            'no_nickname' => 'no_nickname',
            'no_generational_suffix' => 'no_generational_suffix',
            'first_name.text' => 'first_name_text',
            'first_name.phonetic_text' => 'first_name_phonetic_text',
            'middle_name.text' => 'middle_name_text',
            'middle_name.phonetic_text' => 'middle_name_phonetic_text',
            'nickname.text' => 'nickname_text',
            'nickname.phonetic_text' => 'nickname_phonetic_text',
            'last_name.text' => 'last_name_text',
            'last_name.phonetic_text' => 'last_name_phonetic_text',
            'generational_suffix' => 'generational_suffix',
            'display_format' => 'preferred_display_format',
        ];

        $out = [];

        $config = $field->config();
        // Each flag is read only as a condition, where a truthy value acts as true with or without the cast.
        // @pest-mutate-ignore: RemoveBooleanCast
        $includeDisplayFormat = (bool) ($config['include_display_format'] ?? true);
        // Each flag is read only as a condition, where a truthy value acts as true with or without the cast.
        // @pest-mutate-ignore: RemoveBooleanCast
        $includeNickname = (bool) ($config['include_nickname'] ?? true);

        // Drop the keys of the parts the blueprint hides
        if (! $includeDisplayFormat) {
            unset($relativePathToLangKey['display_format']);
        }

        if (! $includeNickname) {
            unset(
                $relativePathToLangKey['no_nickname'],
                $relativePathToLangKey['nickname.text'],
                $relativePathToLangKey['nickname.phonetic_text']
            );
        }

        foreach ($relativePathToLangKey as $relativePath => $langKey) {
            $out[$p($relativePath)] = $this->personNameFieldtypeFieldLabel($langKey);
        }

        unset(
            $field,
            $prefix,
            $p,
            $relativePathToLangKey,
            $config,
            $includeDisplayFormat,
            $includeNickname
        );

        return $out;
    }

    /**
     * Translated CP / validation label for a `person_name.field.{key}` entry.
     *
     * @param  string  $langKey  Key under `person_name.field` in fieldtypes.php
     */
    private function personNameFieldtypeFieldLabel(string $langKey): string
    {
        return __('aether-people::fieldtypes.person_name.field.'.$langKey);
    }

    /**
     * Nested validation rules for sub-keys.
     *
     * @return array<string, array<int, string|\Stringable|ValidationRule>>
     */
    #[\Override]
    public function extraRules(): array
    {
        $field = $this->field();

        if (! $field instanceof Field) {
            return [];
        }

        $config = $field->config();
        $handle = $field->handle();
        $prefix = \is_scalar($handle) ? (string) $handle : '';
        $validate = $config['validate'] ?? [];
        $validateHasRequired = \is_array($validate) && \in_array('required', $validate, true);
        $required = ! empty($config['required']) || $validateHasRequired;
        $requireText = ! empty($config['require_text']);
        // Each flag is read only as a condition, where a truthy value acts as true with or without the cast.
        // @pest-mutate-ignore: RemoveBooleanCast
        $includePhonetic = (bool) ($config['include_phonetic'] ?? true);
        $requirePhonetic = ! empty($config['require_phonetic']) && $includePhonetic;
        // Each flag is read only as a condition, where a truthy value acts as true with or without the cast.
        // @pest-mutate-ignore: RemoveBooleanCast
        $includeDisplayFormat = (bool) ($config['include_display_format'] ?? true);
        // Each flag is read only as a condition, where a truthy value acts as true with or without the cast.
        // @pest-mutate-ignore: RemoveBooleanCast
        $includeNickname = (bool) ($config['include_nickname'] ?? true);
        $mustText = $required || $requireText;

        $dot = static fn (string $path): string => $prefix.'.'.$path;

        $suffixValues = \array_column(
            PersonNameDisplayFormats::generationalSuffixSelectOptions(),
            'value'
        );

        $rules = [];

        $rules[$dot('no_middle_name')] = ['boolean'];
        $rules[$dot('no_generational_suffix')] = ['boolean'];

        $rules[$dot('first_name')] = ['array'];
        $rules[$dot('first_name.text')] = $this->mergeTextRules(
            $mustText,
            true,
            null
        );
        $rules[$dot('first_name.phonetic_text')] = $this->mergePhoneticRules(
            $requirePhonetic,
            true,
            null
        );

        $rules[$dot('middle_name')] = ['array'];
        $rules[$dot('middle_name.text')] = $this->mergeTextRules(
            $mustText,
            false,
            'required_if:'.$dot('no_middle_name').',false'
        );
        $rules[$dot('middle_name.phonetic_text')] = $this->mergePhoneticRules(
            $requirePhonetic,
            false,
            'required_if:'.$dot('no_middle_name').',false'
        );

        if ($includeNickname) {
            $rules[$dot('no_nickname')] = ['boolean'];
            $rules[$dot('nickname')] = ['array'];
            $rules[$dot('nickname.text')] = $this->mergeTextRules(
                $mustText,
                false,
                'required_if:'.$dot('no_nickname').',false'
            );
            $rules[$dot('nickname.phonetic_text')] = $this->mergePhoneticRules(
                $requirePhonetic,
                false,
                'required_if:'.$dot('no_nickname').',false'
            );
        }

        $rules[$dot('last_name')] = ['array'];
        $rules[$dot('last_name.text')] = $this->mergeTextRules(
            $mustText,
            true,
            null
        );
        $rules[$dot('last_name.phonetic_text')] = $this->mergePhoneticRules(
            $requirePhonetic,
            true,
            null
        );

        $rules[$dot('generational_suffix')] = $this->suffixRules(
            $dot,
            $suffixValues,
            $mustText
        );

        if ($includeDisplayFormat) {
            $rules[$dot('display_format')] = [
                new PersonNameDisplayFormatRule(
                    $prefix,
                    $required
                ),
            ];
        }

        unset(
            $field,
            $config,
            $validate,
            $validateHasRequired,
            $required,
            $requireText,
            $includePhonetic,
            $requirePhonetic,
            $includeDisplayFormat,
            $includeNickname,
            $prefix,
            $dot,
            $suffixValues,
            $mustText
        );

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    private function mergeTextRules(
        bool $mustText,
        bool $alwaysVisible,
        ?string $requiredIfWhenVisible
    ): array {
        $out = ['nullable', 'string', 'name'];

        if ($mustText) {
            if ($alwaysVisible) {
                \array_unshift($out, 'required');
            } elseif ($requiredIfWhenVisible !== null) {
                \array_unshift($out, $requiredIfWhenVisible);
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function mergePhoneticRules(
        bool $requirePhonetic,
        bool $alwaysVisible,
        ?string $requiredIfWhenVisible
    ): array {
        $out = ['nullable', 'string', 'international_phonetic_alphabet'];

        if ($requirePhonetic) {
            if ($alwaysVisible) {
                \array_unshift($out, 'required');
            } elseif ($requiredIfWhenVisible !== null) {
                \array_unshift($out, $requiredIfWhenVisible);
            }
        }

        return $out;
    }

    /**
     * @param  callable(string): string  $dot
     * @param  array<int, string>  $suffixValues
     * @return array<int, string|\Stringable>
     */
    private function suffixRules(
        callable $dot,
        array $suffixValues,
        bool $mustText
    ): array {
        $inRule = Rule::in($suffixValues);

        if ($mustText) {
            return [
                'nullable',
                'string',
                'required_if:'.$dot('no_generational_suffix').',false',
                $inRule,
            ];
        }

        return [
            'nullable',
            'string',
            $inRule,
        ];
    }

    /**
     * Default value for new entries.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function defaultValue(): array
    {
        return $this->emptyValue();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyValue(): array
    {
        return [
            'first_name' => [
                'text' => '',
                'phonetic_text' => '',
            ],
            'no_middle_name' => false,
            'middle_name' => [
                'text' => '',
                'phonetic_text' => '',
            ],
            'no_nickname' => false,
            'nickname' => [
                'text' => '',
                'phonetic_text' => '',
            ],
            'last_name' => [
                'text' => '',
                'phonetic_text' => '',
            ],
            'no_generational_suffix' => false,
            'generational_suffix' => null,
            'display_format' => null,
        ];
    }

    /**
     * Normalize for the publish form.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function preProcess(mixed $data): array
    {
        $normalized = $this->normalizeStored($data);

        return $this->applyIncludeNicknameConfig($normalized);
    }

    /**
     * Persist normalized array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function process(mixed $data): array
    {
        $normalized = $this->normalizeStored($data);
        $out = $this->stripHiddenBranches($normalized);

        unset($normalized);

        $out = $this->applyIncludeNicknameConfig($out);

        $config = $this->field()?->config() ?? [];
        // Each flag is read only as a condition, where a truthy value acts as true with or without the cast.
        // @pest-mutate-ignore: RemoveBooleanCast
        $includeDisplayFormat = (bool) ($config['include_display_format'] ?? true);

        if (! $includeDisplayFormat) {
            $out['display_format'] = null;
        }

        // Clear each part's phonetic text when the blueprint keeps it elsewhere
        // Each flag is read only as a condition, where a truthy value acts as true with or without the cast.
        // @pest-mutate-ignore: RemoveBooleanCast
        if (! (bool) ($config['include_phonetic'] ?? true)) {
            foreach (['first_name', 'middle_name', 'nickname', 'last_name'] as $part) {
                /** @var array<string, mixed> $pair */
                $pair = \is_array($out[$part] ?? null) ? $out[$part] : [];
                $pair['phonetic_text'] = null;
                $out[$part] = $pair;
            }
        }

        unset(
            $config,
            $includeDisplayFormat
        );

        return $out;
    }

    /**
     * Ensure validation receives a complete array shape.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function preProcessValidatable(mixed $value): array
    {
        $base = $this->emptyValue();

        if (! \is_array($value)) {
            return $base;
        }

        /** @var array<string, mixed> $replaced */
        $replaced = \array_replace_recursive(
            $base,
            $value
        );

        return $replaced;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeStored(mixed $data): array
    {
        if (! \is_array($data)) {
            return $this->emptyValue();
        }

        $base = $this->emptyValue();
        /** @var array<string, mixed> $merged */
        $merged = \array_replace_recursive(
            $base,
            $data
        );

        /** @var array<string, mixed> $firstName */
        $firstName = \is_array($merged['first_name'] ?? null) ? $merged['first_name'] : [];
        /** @var array<string, mixed> $middleName */
        $middleName = \is_array($merged['middle_name'] ?? null) ? $merged['middle_name'] : [];
        /** @var array<string, mixed> $nickname */
        $nickname = \is_array($merged['nickname'] ?? null) ? $merged['nickname'] : [];
        /** @var array<string, mixed> $lastName */
        $lastName = \is_array($merged['last_name'] ?? null) ? $merged['last_name'] : [];

        $merged['first_name'] = $this->normalizePair($firstName);
        $merged['middle_name'] = $this->normalizePair($middleName);
        $merged['nickname'] = $this->normalizePair($nickname);
        $merged['last_name'] = $this->normalizePair($lastName);

        $merged['no_middle_name'] = (bool) ($merged['no_middle_name'] ?? false);
        $merged['no_nickname'] = (bool) ($merged['no_nickname'] ?? false);
        $merged['no_generational_suffix'] = (bool) ($merged['no_generational_suffix'] ?? false);

        $suf = $merged['generational_suffix'] ?? null;
        $merged['generational_suffix'] = \is_string($suf) && \trim($suf) !== ''
            ? \trim($suf)
            : null;

        $df = $merged['display_format'] ?? null;
        $merged['display_format'] = \is_string($df) ? $df : null;

        unset($base, $data, $suf, $df);

        return $this->stripHiddenBranches($merged);
    }

    /**
     * When nickname is disabled in the blueprint, force “no nickname” and clear stored
     * nickname pairs (same shape as `stripHiddenBranches` for `no_nickname`).
     *
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function applyIncludeNicknameConfig(array $normalized): array
    {
        $config = $this->field()?->config() ?? [];
        // Each flag is read only as a condition, where a truthy value acts as true with or without the cast.
        // @pest-mutate-ignore: RemoveBooleanCast
        $includeNickname = (bool) ($config['include_nickname'] ?? true);

        unset($config);

        if ($includeNickname) {
            unset($includeNickname);

            return $normalized;
        }

        $normalized['no_nickname'] = true;
        $normalized['nickname'] = [
            'text' => null,
            'phonetic_text' => null,
        ];

        unset($includeNickname);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function stripHiddenBranches(array $normalized): array
    {
        if (! empty($normalized['no_middle_name'])) {
            $normalized['middle_name'] = [
                'text' => null,
                'phonetic_text' => null,
            ];
        }

        if (! empty($normalized['no_nickname'])) {
            $normalized['nickname'] = [
                'text' => null,
                'phonetic_text' => null,
            ];
        }

        if (! empty($normalized['no_generational_suffix'])) {
            $normalized['generational_suffix'] = null;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $pair
     * @return array{text: string, phonetic_text: string}
     */
    private function normalizePair(array $pair): array
    {
        $text = isset($pair['text']) && \is_string($pair['text']) ? $pair['text'] : '';
        $pho = isset($pair['phonetic_text']) && \is_string($pair['phonetic_text'])
            ? $pair['phonetic_text']
            : '';

        unset($pair);

        return [
            'text' => $text,
            'phonetic_text' => $pho,
        ];
    }
}
