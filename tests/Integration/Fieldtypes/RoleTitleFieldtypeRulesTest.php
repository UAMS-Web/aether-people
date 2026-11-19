<?php

declare(strict_types=1);

use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;
use Statamic\Fields\Field;
use UAMSWeb\Aether\Enums\Isco08;
use UAMSWeb\Aether\People\Fieldtypes\RoleTitle;
use UAMSWeb\Aether\People\Rules\OnetSocOccupationCode;
use UAMSWeb\Aether\People\Rules\RoleTitlePerConfigurationOptionRule;
use UAMSWeb\Aether\People\Rules\RoleTitleScopeJoinerRule;
use UAMSWeb\Aether\People\Rules\RoleTitleScopeUnitRule;

covers(RoleTitle::class);

/**
 * The exact blueprint config section, nested validation rules and validation
 * attribute labels the `role_title` fieldtype returns, for the blueprint
 * configurations that change them: optional, required with both structures,
 * required through `validate` with one structure, and required with the default
 * structure only.
 *
 * @command  php artisan test --compact tests/Integration/Fieldtypes/RoleTitleFieldtypeRulesTest.php
 */

/**
 * A role_title fieldtype bound to a field with the given blueprint config.
 *
 * @param  array<string, mixed>  $config
 */
function roleTitleRulesFieldtype(array $config = []): RoleTitle
{
    $fieldtype = new RoleTitle;
    $fieldtype->setField(new Field('rt', ['type' => 'role_title'] + $config));

    return $fieldtype;
}

/**
 * A comparable form of one rule: a string as it is, an `in` rule as its string,
 * and any other rule object as its class and property values.
 */
function roleTitleRulesShape(mixed $rule): mixed
{
    if (\is_string($rule)) {
        return $rule;
    }

    if ($rule instanceof In) {
        return (string) $rule;
    }

    expect($rule)->toBeObject();

    /** @var object $rule */
    $shape = ['class' => $rule::class];

    foreach (new ReflectionObject($rule)->getProperties() as $property) {
        $shape[$property->getName()] = $property->getValue($rule);
    }

    return $shape;
}

/**
 * The fieldtype's nested rules, each rule in its comparable form.
 *
 * @param  array<string, mixed>  $config
 * @return array<string, list<mixed>>
 */
function roleTitleRulesMap(array $config): array
{
    $out = [];

    foreach (roleTitleRulesFieldtype($config)->extraRules() as $path => $rules) {
        $out[$path] = \array_map(roleTitleRulesShape(...), $rules);
    }

    return $out;
}

/**
 * The rule map for an optional field with the default config.
 *
 * @return array<string, list<mixed>>
 */
function roleTitleRulesOptionalMap(): array
{
    $none = '__role_title_none__';

    return [
        'rt.mode' => ['nullable', 'string', 'in:"structured","unstructured"'],
        'rt.configuration' => ['nullable', 'string', 'in:"faculty","academic_leadership","institutional_leadership","clinical_leadership"'],
        'rt.value' => ['nullable', 'string'],
        'rt.occupation' => ['nullable', 'array'],
        'rt.occupation.same_as' => ['nullable', 'array'],
        'rt.occupation.same_as.*' => ['nullable', 'string', 'url'],
        'rt.occupation.category_code' => ['nullable', 'array'],
        'rt.occupation.category_code.onet_soc' => ['nullable', 'array'],
        'rt.occupation.category_code.onet_soc.*' => ['nullable', 'string', ['class' => OnetSocOccupationCode::class]],
        'rt.occupation.category_code.isco_08' => ['nullable', 'array'],
        'rt.occupation.category_code.isco_08.*' => ['nullable', 'string', ['class' => Enum::class, 'type' => Isco08::class, 'validator' => null, 'only' => [], 'except' => []]],
        'rt.occupational_field' => ['nullable', 'array'],
        'rt.occupational_field.same_as' => ['nullable', 'array'],
        'rt.occupational_field.same_as.*' => ['nullable', 'string', 'url'],
        'rt.occupational_field.category_code' => ['nullable', 'array'],
        'rt.__cp_role_title_unstructured_isco_08' => ['nullable', 'array'],
        'rt.__cp_role_title_unstructured_onet_soc' => ['nullable', 'array'],
        'rt.__cp_role_title_unstructured_occ_same_as' => ['nullable', 'array'],
        'rt.__cp_role_title_unstructured_field_same_as' => ['nullable', 'array'],
        'rt.title_options.interim' => ['boolean'],
        'rt.title_options.co_prefix' => ['boolean'],
        'rt.title_options.regional' => ['boolean'],
        'rt.title_options.senior' => ['boolean'],
        'rt.title_options.executive' => ['boolean'],
        'rt.title_options.visiting' => ['boolean'],
        'rt.title_options.distinguished' => ['boolean'],
        'rt.title_options.adjunct' => ['boolean'],
        'rt.scope_qualifier' => ['nullable', 'string', 'in:"clinical","research","'.$none.'"'],
        'rt.hierarchical_modifier' => ['nullable', 'string', 'in:"associate","assistant","'.$none.'"'],
        'rt.object' => ['nullable', 'string', 'in:"department","division","fellowship_program","medical_student_clerkship","residency_program","section","service_line","training","'.$none.'"'],
        'rt.positional_qualifier' => ['nullable', 'string', 'in:"deputy","vice","'.$none.'"'],
        'rt.role' => ['nullable', 'string', ['class' => RoleTitlePerConfigurationOptionRule::class, 'data' => [], 'parentFieldPath' => 'rt', 'parentFieldMarkedRequired' => false, 'optionSubfieldKey' => 'role']],
        'rt.honorific' => ['nullable', 'string', 'in:"emeritus","emerita","'.$none.'"'],
        'rt.scope.scope_joiner' => ['nullable', 'string', 'required_with:{this}.rt.scope.scope_name', 'in:"for","of","in"', ['class' => RoleTitleScopeJoinerRule::class, 'data' => [], 'parentFieldPath' => 'rt']],
        'rt.scope.scope_article' => ['nullable', 'string', 'in:"the","'.$none.'"'],
        'rt.scope.scope_unit' => ['nullable', ['class' => RoleTitleScopeUnitRule::class, 'data' => [], 'parentFieldPath' => 'rt', 'rowLinkHandle' => null]],
        'rt.scope.scope_name' => ['nullable', 'string'],
    ];
}

/**
 * The per-configuration role rule, for a field required or not.
 *
 * @return list<mixed>
 */
function roleTitleRulesRoleRule(bool $required): array
{
    return ['nullable', 'string', ['class' => RoleTitlePerConfigurationOptionRule::class, 'data' => [], 'parentFieldPath' => 'rt', 'parentFieldMarkedRequired' => $required, 'optionSubfieldKey' => 'role']];
}

it('returns the optional rule map for the default config', function (array $config): void {
    expect(roleTitleRulesMap($config))->toBe(roleTitleRulesOptionalMap());
})->with([
    'default config' => [[]],
    'both structures, optional' => [['enabled_title_structures' => ['structured', 'unstructured']]],
    'a validate string, not a list' => [['validate' => 'required']],
    'a blank row link' => [['scope_unit_row_link' => '   ']],
    'a row link that is not a string' => [['scope_unit_row_link' => ['department']]],
]);

it('tightens every required segment when the field is required with both structures', function (): void {
    $requiredIfUnstructured = 'required_if:{this}.rt.mode,unstructured';

    $expected = [
        'rt.mode' => ['required', 'string', 'in:"structured","unstructured"'],
        'rt.configuration' => ['nullable', 'string', 'in:"faculty","academic_leadership","institutional_leadership","clinical_leadership"', 'required_if:{this}.rt.mode,structured'],
        'rt.value' => ['nullable', 'string', $requiredIfUnstructured],
        'rt.occupation.same_as' => ['nullable', 'array', $requiredIfUnstructured, 'min:1'],
        'rt.occupation.category_code.onet_soc' => ['nullable', 'array', $requiredIfUnstructured, 'min:1'],
        'rt.occupation.category_code.isco_08' => ['nullable', 'array', $requiredIfUnstructured, 'min:1'],
        'rt.occupational_field.same_as' => ['nullable', 'array', $requiredIfUnstructured, 'min:1'],
        'rt.role' => roleTitleRulesRoleRule(true),
        'rt.scope.scope_unit' => ['nullable', ['class' => RoleTitleScopeUnitRule::class, 'data' => [], 'parentFieldPath' => 'rt', 'rowLinkHandle' => 'row_unit']],
    ] + roleTitleRulesOptionalMap();

    expect(roleTitleRulesMap([
        'required' => true,
        'enabled_title_structures' => ['structured', 'unstructured'],
        'required_unstructured_subfields' => ['value', 'occupation_same_as', 'occupation_onet_soc', 'occupation_isco_08', 'occupational_field_same_as'],
        'scope_unit_row_link' => '  row_unit  ',
    ]))->toEqual($expected);
});

it('leaves the mode and configuration optional for a required field with only the unstructured structure', function (): void {
    $expected = [
        'rt.value' => ['nullable', 'string', 'required_if:{this}.rt.mode,unstructured'],
        'rt.role' => roleTitleRulesRoleRule(true),
    ] + roleTitleRulesOptionalMap();

    expect(roleTitleRulesMap([
        'validate' => ['required'],
        'enabled_title_structures' => ['unstructured'],
    ]))->toEqual($expected);
});

it('requires the configuration but not the hidden mode picker for a required structured-only field', function (): void {
    $expected = [
        'rt.configuration' => ['nullable', 'string', 'in:"faculty","academic_leadership","institutional_leadership","clinical_leadership"', 'required_if:{this}.rt.mode,structured'],
        'rt.value' => ['nullable', 'string', 'required_if:{this}.rt.mode,unstructured'],
        'rt.role' => roleTitleRulesRoleRule(true),
    ] + roleTitleRulesOptionalMap();

    expect(roleTitleRulesMap(['required' => true]))->toEqual($expected);
});

it('returns the rule map in a stable key order', function (): void {
    expect(\array_keys(roleTitleRulesMap([])))->toBe(\array_keys(roleTitleRulesOptionalMap()));
});

it('returns no nested rules or attribute labels without a field', function (): void {
    $fieldtype = new RoleTitle;

    expect($fieldtype->extraRules())->toBe([])
        ->and($fieldtype->extraValidationAttributes())->toBe([]);
});

it('labels each nested validation attribute, using option labels for title options', function (): void {
    expect(roleTitleRulesFieldtype()->extraValidationAttributes())->toBe([
        'rt.mode' => 'Title Structure',
        'rt.configuration' => 'Title Configuration',
        'rt.value' => 'Title Text',
        'rt.scope_qualifier' => 'Scope Qualifier',
        'rt.hierarchical_modifier' => 'Hierarchical Modifier',
        'rt.role' => 'Role',
        'rt.object' => 'Object',
        'rt.positional_qualifier' => 'Positional Qualifier',
        'rt.scope.scope_joiner' => 'Scope Joiner',
        'rt.scope.scope_article' => 'Scope Article',
        'rt.scope.scope_unit' => 'Scope Unit',
        'rt.scope.scope_name' => 'Scope Name',
        'rt.title_options.visiting' => 'visiting',
        'rt.title_options.distinguished' => 'distinguished',
        'rt.title_options.adjunct' => 'adjunct',
        'rt.title_options.interim' => 'interim',
        'rt.title_options.co_prefix' => 'co-',
        'rt.title_options.regional' => 'regional',
        'rt.title_options.executive' => 'executive',
        'rt.title_options.senior' => 'senior',
        'rt.occupation.same_as' => 'Occupation: Reference Webpages',
        'rt.occupation.category_code.isco_08' => 'Occupation: ISCO-08 Codes',
        'rt.occupation.category_code.onet_soc' => 'Occupation: O*NET-SOC Codes',
        'rt.occupational_field.same_as' => 'Occupational Field: Reference Webpages',
    ]);
});

it('returns the blueprint config section with every option, default and condition', function (): void {
    $fieldtype = roleTitleRulesFieldtype();

    /** @var array<int, array<string, mixed>> $items */
    $items = (fn (): array => $this->configFieldItems())->call($fieldtype);

    $structures = [
        'structured' => 'Structured',
        'unstructured' => 'Unstructured',
    ];
    $configurations = [
        'faculty' => 'Faculty Role Title',
        'academic_leadership' => 'Academic Leadership Role Title',
        'institutional_leadership' => 'Institutional Leadership Role Title',
        'clinical_leadership' => 'Clinical Leadership Role Title',
    ];

    expect($items)->toBe([
        [
            'display' => 'Role Title Options',
            'fields' => [
                'enabled_title_structures' => [
                    'type' => 'checkboxes',
                    'display' => 'Enabled Title Structures',
                    'instructions' => __('aether-people::fieldtypes.role_title.config.enabled_title_structures_instructions'),
                    'options' => $structures,
                    'default' => ['structured', 'unstructured'],
                    'validate' => ['required', 'array', 'min:1'],
                ],
                'default_title_structure' => [
                    'type' => 'select',
                    'display' => 'Default Title Structure',
                    'instructions' => __('aether-people::fieldtypes.role_title.config.default_title_structure_instructions'),
                    'options' => $structures,
                    'default' => 'structured',
                    'if' => ['enabled_title_structures' => 'custom selectionCount:gt,1'],
                ],
                'enabled_configurations' => [
                    'type' => 'checkboxes',
                    'display' => 'Enabled Title Configurations',
                    'instructions' => __('aether-people::fieldtypes.role_title.config.enabled_configurations_instructions'),
                    'options' => $configurations,
                    'default' => \array_keys($configurations),
                    'validate' => ['required', 'array', 'min:1'],
                    'if' => ['enabled_title_structures' => 'custom selectionContains:structured'],
                ],
                'default_configuration' => [
                    'type' => 'select',
                    'display' => 'Default Configuration',
                    'instructions' => __('aether-people::fieldtypes.role_title.config.default_configuration_instructions'),
                    'options' => $configurations,
                    'default' => 'faculty',
                    'if' => ['enabled_title_structures' => 'custom roleTitleDefaultConfigurationVisible'],
                ],
                'required_unstructured_subfields' => [
                    'type' => 'checkboxes',
                    'display' => 'Required Unstructured Subfields',
                    'instructions' => __('aether-people::fieldtypes.role_title.config.required_unstructured_subfields_instructions'),
                    'options' => [
                        'value' => 'Title Text',
                        'occupation_same_as' => 'Occupation: Reference Webpages',
                        'occupation_onet_soc' => 'Occupation: O*NET-SOC Codes',
                        'occupation_isco_08' => 'Occupation: ISCO-08 Codes',
                        'occupational_field_same_as' => 'Occupational Field: Reference Webpages',
                    ],
                    'default' => ['value'],
                    'validate' => ['nullable', 'array'],
                    'if' => ['enabled_title_structures' => 'custom selectionContains:unstructured'],
                ],
                'scope_unit_row_link' => [
                    'type' => 'text',
                    'display' => 'Scope Unit Must Match Row Field',
                    'instructions' => __('aether-people::fieldtypes.role_title.config.scope_unit_row_link_instructions'),
                    'if' => ['enabled_title_structures' => 'custom selectionContains:structured'],
                ],
                'border' => [
                    'type' => 'toggle',
                    'display' => 'Bordered Container',
                    'instructions' => 'Wrap the field in the same bordered card shell as a Group field.',
                    'default' => true,
                ],
            ],
        ],
    ]);
});

it('declares its picker metadata', function (): void {
    $fieldtype = new RoleTitle;

    expect($fieldtype->icon())->toBe('text-formatting')
        ->and($fieldtype->component())->toBe('role_title')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeFalse()
        ->and($fieldtype->keywords())->toBe(['role', 'title', 'position'])
        ->and($fieldtype->categories())->toBe(['text'])
        ->and($fieldtype->rules())->toBe(['array']);
});
