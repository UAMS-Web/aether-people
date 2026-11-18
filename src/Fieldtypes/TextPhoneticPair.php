<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Fieldtypes;

use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;

/**
 * Blueprint-driven text plus IPA phonetic pair, using the same Control Panel
 * control as the {@see PersonName} fieldtype's subfields.
 *
 * Moved from the host site (UAMS-Web/aether-people#123). Its labels are read from
 * the add-on's `aether-people::fieldtypes.text_phonetic_pair.*` keys, which a host
 * can still override under `lang/vendor/aether-people/`. The rules `name` and
 * `international_phonetic_alphabet` that {@see self::extraRules()} names belong to
 * the host site, as they do for {@see PersonName}.
 */
class TextPhoneticPair extends Fieldtype
{
    /**
     * The prefix of every lang key this fieldtype reads.
     */
    private const string LANG_PREFIX = 'aether-people::fieldtypes.text_phonetic_pair.';

    /**
     * Display title in the fieldtype picker.
     *
     * @var string
     */
    protected static $title = 'Text & Phonetic Pair';

    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string
     */
    protected $component = 'text_phonetic_pair';

    /**
     * Indicates whether the fieldtype is selectable in collection blueprints.
     *
     * @var bool
     */
    // PCOV attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Flipped by hand, it fails "identifies itself in the Control Panel".
    // @pest-mutate-ignore: TrueToFalse
    protected $selectable = true;

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool
     */
    // PCOV attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Flipped by hand, it fails "identifies itself in the Control Panel".
    // @pest-mutate-ignore: TrueToFalse
    protected $selectableInForms = true;

    /**
     * Search keywords in the fieldtype picker.
     *
     * @var array<int, string>
     */
    // PCOV attributes no coverage to a property default, so Pest selects no test for its mutants.
    // Removing any entry by hand fails "identifies itself in the Control Panel".
    // @pest-mutate-ignore: RemoveArrayItem
    protected $keywords = [
        'text',
        'phonetic',
        'ipa',
        'pair',
    ];

    /**
     * The fieldtype picker categories.
     *
     * @var array<int, string>
     */
    // PCOV attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Removing the entry by hand fails "identifies itself in the Control Panel".
    // @pest-mutate-ignore: RemoveArrayItem
    protected $categories = [
        'text',
    ];

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'text-formatting';
    }

    /**
     * Blueprint editor: labels, layout, and validation toggles (mirrors the
     * partial component props and {@see PersonName} patterns).
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
                'display' => $this->label('config.section_heading'),
                'fields' => [
                    'text_label' => $this->configField('text_label', 'text', ''),
                    'phonetic_label' => $this->configField('phonetic_label', 'text', ''),
                    'quote_straight_on_text' => $this->configField('quote_straight_on_text', 'toggle', false),
                    'split_inputs' => $this->configField('split_inputs', 'toggle', true),
                    'border' => $this->configField('border', 'toggle', true),
                    'body_class' => $this->configField('body_class', 'text', 'space-y-6'),
                    'require_text' => $this->configField('require_text', 'toggle', true),
                    'require_phonetic' => $this->configField('require_phonetic', 'toggle', false),
                    'include_phonetic_instructions' => $this->configField('include_phonetic_instructions', 'toggle', true),
                ],
            ],
        ];

        return \array_merge(
            $parent,
            $custom
        );
    }

    /**
     * One blueprint setting, labeled from its `config.{handle}_display` and
     * `config.{handle}_instructions` keys.
     *
     * @param  string  $handle  The setting's handle
     * @param  string  $type  The setting's own fieldtype
     * @param  bool|string  $default  The setting's default value
     * @return array{type: string, display: string, instructions: string, default: bool|string}
     */
    private function configField(string $handle, string $type, bool|string $default): array
    {
        return [
            'type' => $type,
            'display' => $this->label('config.'.$handle.'_display'),
            'instructions' => $this->label('config.'.$handle.'_instructions'),
            'default' => $default,
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
     * Human-readable names for nested validation keys.
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

        /** @var string $prefix */
        $prefix = $field->handle();

        return [
            $prefix.'.text' => $this->label('field.text'),
            $prefix.'.phonetic_text' => $this->label('field.phonetic_text'),
        ];
    }

    /**
     * The translated text of one of this fieldtype's lang keys.
     *
     * @param  string  $key  The key under `aether-people::fieldtypes.text_phonetic_pair`
     */
    private function label(string $key): string
    {
        return __(self::LANG_PREFIX.$key);
    }

    /**
     * Nested validation rules for `text` and `phonetic_text`.
     *
     * @return array<string, array<int, string>>
     */
    #[\Override]
    public function extraRules(): array
    {
        $field = $this->field();

        if (! $field instanceof Field) {
            return [];
        }

        $config = $field->config();
        /** @var string $prefix */
        $prefix = $field->handle();
        $validate = $config['validate'] ?? [];
        $validateHasRequired = \is_array($validate) && \in_array('required', $validate, true);
        $required = ! empty($config['required']) || $validateHasRequired;
        $mustText = $required || ! empty($config['require_text']);
        $requirePhonetic = ! empty($config['require_phonetic']);

        return [
            $prefix.'.text' => $mustText
                ? ['required', 'string', 'name']
                : ['nullable', 'string', 'name'],
            $prefix.'.phonetic_text' => $requirePhonetic
                ? ['required', 'string', 'international_phonetic_alphabet']
                : ['nullable', 'string', 'international_phonetic_alphabet'],
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
        return $this->emptyPair();
    }

    /**
     * @return array{text: string, phonetic_text: string}
     */
    private function emptyPair(): array
    {
        return [
            'text' => '',
            'phonetic_text' => '',
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
        return $this->normalizeStored($data);
    }

    /**
     * Persist normalized pair.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function process(mixed $data): array
    {
        return $this->normalizeStored($data);
    }

    /**
     * Ensure validation receives a complete pair shape.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function preProcessValidatable(mixed $value): array
    {
        $base = $this->emptyPair();

        if (! \is_array($value)) {
            return $base;
        }

        /** @var array<string, mixed> $merged */
        $merged = \array_replace_recursive(
            $base,
            $value
        );

        return $merged;
    }

    /**
     * @return array{text: string, phonetic_text: string}
     */
    private function normalizeStored(mixed $data): array
    {
        if (! \is_array($data)) {
            return $this->emptyPair();
        }

        return [
            'text' => isset($data['text']) && \is_string($data['text']) ? $data['text'] : '',
            'phonetic_text' => isset($data['phonetic_text']) && \is_string($data['phonetic_text'])
                ? $data['phonetic_text']
                : '',
        ];
    }
}
