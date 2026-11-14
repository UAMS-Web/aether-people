<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Fieldtypes;

use Illuminate\Contracts\Validation\ValidationRule;
use Statamic\Fields\Fieldtype;
use UAMSWeb\Aether\People\Rules\PersonNameDisplayFormatRule;
use UAMSWeb\Aether\People\Support\PersonNameComposite;

/**
 * The preferred display format of a person's name, kept in a field of its own
 * beside `person_name` (UAMS-Web/uams-statamic-archive#3186, decided on UAMS-Web/uams-statamic-archive#3130).
 *
 * The options are the formats the name allows once it is joined with the
 * nickname and pronunciation fields beside it, so they are built in the Control
 * Panel from the publish form's values and checked here against the same joined
 * name. The Workday sync never writes this field.
 *
 * @see UAMSWeb\Aether\People\Support\PersonNameComposite for how the name is joined
 */
class PersonNameDisplayFormat extends Fieldtype
{
    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string
     */
    protected $component = 'person_name_display_format';

    /**
     * Not offered in the blueprint editor: it only works beside a `person_name`.
     *
     * @var bool
     */
    // PCOV attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Flipped by hand, it fails "is offered in no blueprint, with its component and icon".
    // @pest-mutate-ignore: FalseToTrue
    protected $selectable = false;

    /**
     * Not offered in form blueprints.
     *
     * @var bool
     */
    // PCOV attributes no coverage to a property default, so Pest selects no test for its mutant.
    // Flipped by hand, it fails "is offered in no blueprint, with its component and icon".
    // @pest-mutate-ignore: FalseToTrue
    protected $selectableInForms = false;

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
     * Blueprint editor: whether a format must be chosen once one is available.
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
                'display' => __('Display format options'),
                'fields' => [
                    'require_when_available' => [
                        'type' => 'toggle',
                        'display' => __('Require a format once the name allows one'),
                        'instructions' => __(
                            'When enabled, a format must be chosen once every name part it depends on is filled.'
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
     * The chosen format must be one the joined name allows.
     *
     * @return array<int, ValidationRule>
     */
    #[\Override]
    public function rules(): array
    {
        $config = $this->field()?->config() ?? [];

        return [
            new PersonNameDisplayFormatRule(
                PersonNameComposite::PERSON_NAME,
                (bool) ($config['require_when_available'] ?? true),
                true
            ),
        ];
    }

    /**
     * Store a chosen key, or nothing.
     *
     * @param  mixed  $data  The submitted value
     */
    #[\Override]
    public function process(mixed $data): ?string
    {
        return \is_string($data) && \trim($data) !== '' ? $data : null;
    }
}
