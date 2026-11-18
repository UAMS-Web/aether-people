<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\Assert;
use Statamic\Fields\Field;
use UAMSWeb\Aether\Enums\Isco08;
use UAMSWeb\Aether\People\Fieldtypes\Isco_08;
use UAMSWeb\Aether\People\Rules\Isco08FieldtypeStoredRule;
use UAMSWeb\Aether\People\Support\Isco08FieldtypeStorage;

/**
 * Feature tests for the chained ISCO-08 occupation fieldtype: process/preProcess
 * value recomputation, CP sentinel handling, extra validation rules, and the
 * enum-backed hierarchy option rows.
 *
 * Mutation: 100% on UAMSWeb\Aether\People\Fieldtypes\Isco_08 (77 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Integration/Fieldtypes/Isco08FieldtypeTest.php
 */
function isco08MakeFieldtype(): Isco_08
{
    $fieldtype = new Isco_08;
    $field = new Field('isco', [
        'type' => 'isco_08',
    ]);
    $fieldtype->setField($field);

    return $fieldtype;
}

function isco08TitleForCode(string $code): string
{
    $case = Isco08::tryFrom($code);
    Assert::assertNotNull($case);

    return $case->title();
}

it('persists the full chain and recomputes value on process', function (): void {
    $fieldtype = isco08MakeFieldtype();

    $out = $fieldtype->process([
        'major_group' => '3',
        'sub_major_group' => '32',
        'minor_group' => '321',
        'unit_group' => '3214',
        'value' => 'tampered',
    ]);

    expect($out)->toBe([
        'major_group' => '3',
        'sub_major_group' => '32',
        'minor_group' => '321',
        'unit_group' => '3214',
        'value' => '3214',
    ]);
});

it('yields nulls below and value when process sentinel is at sub-major', function (): void {
    $fieldtype = isco08MakeFieldtype();

    $out = $fieldtype->process([
        'major_group' => '3',
        'sub_major_group' => false,
    ]);

    expect($out)->toBe([
        'major_group' => '3',
        'sub_major_group' => null,
        'minor_group' => null,
        'unit_group' => null,
        'value' => '3',
    ]);
});

it('yields null unit and value when process sentinel is at minor', function (): void {
    $fieldtype = isco08MakeFieldtype();

    $out = $fieldtype->process([
        'major_group' => '3',
        'sub_major_group' => '32',
        'minor_group' => false,
    ]);

    expect($out)->toBe([
        'major_group' => '3',
        'sub_major_group' => '32',
        'minor_group' => null,
        'unit_group' => null,
        'value' => '32',
    ]);
});

it('sets value when process sentinel is at unit', function (): void {
    $fieldtype = isco08MakeFieldtype();

    $out = $fieldtype->process([
        'major_group' => '3',
        'sub_major_group' => '32',
        'minor_group' => '321',
        'unit_group' => false,
    ]);

    expect($out)->toBe([
        'major_group' => '3',
        'sub_major_group' => '32',
        'minor_group' => '321',
        'unit_group' => null,
        'value' => '321',
    ]);
});

it('sets the stored value key on pre-process', function (): void {
    $fieldtype = isco08MakeFieldtype();

    $out = $fieldtype->preProcess([
        'major_group' => '3',
        'sub_major_group' => '32',
        'minor_group' => '321',
        'unit_group' => '3214',
    ]);

    expect(data_get($out, 'value'))->toBe('3214');
});

it('sets the pre-process value when the sentinel is at sub', function (): void {
    $fieldtype = isco08MakeFieldtype();

    $out = $fieldtype->preProcess([
        'major_group' => '3',
        'sub_major_group' => null,
        'minor_group' => null,
        'unit_group' => null,
    ]);

    expect(data_get($out, 'value'))->toBe('3');
});

it('accepts the cp sentinel false on sub-major in extra rules', function (): void {
    $fieldtype = new Isco_08;
    $field = new Field('isco_08_classification_field', [
        'type' => 'isco_08',
    ]);
    $fieldtype->setField($field);

    $nested = [
        'major_group' => '1',
        'sub_major_group' => false,
    ];
    $preprocessed = $fieldtype->preProcessValidatable($nested);

    $validator = Validator::make(
        ['isco_08_classification_field' => $preprocessed],
        $fieldtype->extraRules(),
    );

    expect($validator->passes())->toBeTrue(
        (string) json_encode($validator->errors()->all()),
    );
});

it('labels hierarchy option rows with code space title', function (): void {
    $meta = Isco08::hierarchyOptionRows();
    $row = collect($meta['majors'])->firstWhere('key', '3');

    Assert::assertNotNull($row);
    expect(data_get($row, 'value'))->toBe('3 '.isco08TitleForCode('3'));

    $case = Isco08::from('3');
    expect(data_get($row, 'description'))->toBe($case->definition());
});

/**
 * Every tier shape the processing methods see, keyed by a short label.
 *
 * @return array<string, array{0: mixed, 1: array<string, mixed>, 2: array<string, mixed>}>
 */
function isco08TierCases(): array
{
    $empty = ['major_group' => null, 'sub_major_group' => null, 'minor_group' => null, 'unit_group' => null];

    // Each row: input, the stored (process) shape, and the CP (sentinel) shape
    return [
        'a major only' => [['major_group' => '3'], ['major_group' => '3', 'sub_major_group' => null, 'minor_group' => null, 'unit_group' => null, 'value' => '3'], ['major_group' => '3', 'sub_major_group' => false, 'minor_group' => null, 'unit_group' => null, 'value' => '3']],
        'down to a sub-major' => [['major_group' => '3', 'sub_major_group' => '32'], ['major_group' => '3', 'sub_major_group' => '32', 'minor_group' => null, 'unit_group' => null, 'value' => '32'], ['major_group' => '3', 'sub_major_group' => '32', 'minor_group' => false, 'unit_group' => null, 'value' => '32']],
        'down to a minor' => [['major_group' => '3', 'sub_major_group' => '32', 'minor_group' => '321'], ['major_group' => '3', 'sub_major_group' => '32', 'minor_group' => '321', 'unit_group' => null, 'value' => '321'], ['major_group' => '3', 'sub_major_group' => '32', 'minor_group' => '321', 'unit_group' => false, 'value' => '321']],
        'the full chain' => [['major_group' => '3', 'sub_major_group' => '32', 'minor_group' => '321', 'unit_group' => '3214'], ['major_group' => '3', 'sub_major_group' => '32', 'minor_group' => '321', 'unit_group' => '3214', 'value' => '3214'], ['major_group' => '3', 'sub_major_group' => '32', 'minor_group' => '321', 'unit_group' => '3214', 'value' => '3214']],
        'a sub-major from another major' => [['major_group' => '3', 'sub_major_group' => '21'], ['major_group' => '3', 'sub_major_group' => null, 'minor_group' => null, 'unit_group' => null, 'value' => '3'], ['major_group' => '3', 'sub_major_group' => false, 'minor_group' => null, 'unit_group' => null, 'value' => '3']],
        'an unknown major' => [['major_group' => '99'], [], $empty],
        'an empty major' => [['major_group' => ''], [], $empty],
    ];
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = new Isco_08;

    expect(Isco_08::title())->toBe('ISCO-08 Occupation Classification Code')
        ->and($fieldtype->component())->toBe('isco_08')
        ->and($fieldtype->icon())->toBe('array')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->localizable())->toBeFalse()
        ->and($fieldtype->rules())->toBe(['array'])
        ->and($fieldtype->categories())->toBe(['special', 'structured'])
        ->and($fieldtype->keywords())->toBe([
            'ILO',
            'ISCO 08',
            'ISCO 2008',
            'ISCO',
            'ISCO-08',
            'ISCO-2008',
            'ISCO08',
            'ISCO2008',
            'International Labor Organization',
            'International Labour Organization',
            'International Standard Classification of Occupations 2008',
            'International Standard Classification of Occupations',
            'classification',
            'classifications',
            'classifier',
            'classifiers',
            'classify',
            'classifying',
            'code',
            'codes',
            'identification',
            'identifications',
            'identifier',
            'identifiers',
            'identify',
            'occupation',
        ]);
});

it('preloads the hierarchy rows and defaults to the empty tier shape', function (): void {
    $fieldtype = new Isco_08;

    expect($fieldtype->preload())->toBe(Isco08::hierarchyOptionRows())
        ->and($fieldtype->defaultValue())->toBe(Isco08FieldtypeStorage::emptyShape());
});

it('stores the normalized chain with its resolved value', function (array $input, array $stored): void {
    expect(isco08MakeFieldtype()->process($input))->toBe($stored);
})->with(fn (): array => array_map(fn (array $row): array => [$row[0], $row[1]], isco08TierCases()));

it('stores nothing for a non-array value', function (mixed $input): void {
    expect(isco08MakeFieldtype()->process($input))->toBe([]);
})->with(['null' => [null], 'a code string' => ['3']]);

it('marks the first unset tier below a selection as false for the Control Panel', function (array $input, array $stored, array $cp): void {
    $fieldtype = isco08MakeFieldtype();

    expect($fieldtype->preProcess($input))->toBe($cp)
        ->and($fieldtype->preProcessValidatable($input))->toBe($cp)
        ->and($fieldtype->augment($input))->toBe($cp);
})->with(isco08TierCases(...));

it('falls back to the empty tier shape for a non-array value before editing or validation', function (mixed $input): void {
    $fieldtype = isco08MakeFieldtype();
    $empty = ['major_group' => null, 'sub_major_group' => null, 'minor_group' => null, 'unit_group' => null];

    expect($fieldtype->preProcess($input))->toBe($empty)
        ->and($fieldtype->preProcessValidatable($input))->toBe($empty);
})->with(['null' => [null], 'a code string' => ['3']]);

it('augments a non-array stored value unchanged', function (mixed $input): void {
    expect(isco08MakeFieldtype()->augment($input))->toBe($input);
})->with(['null' => [null], 'a code string' => ['3']]);

it('labels each nested tier attribute under the field handle', function (): void {
    expect(isco08MakeFieldtype()->extraValidationAttributes())->toBe([
        'isco.major_group' => 'Major Group',
        'isco.sub_major_group' => 'Sub-Major Group',
        'isco.minor_group' => 'Minor Group',
        'isco.unit_group' => 'Unit Group',
        'isco.value' => 'Resolved ISCO-08 code (stored, not shown in the field UI)',
    ]);
});

it('adds no nested attributes or rules without a bound field', function (): void {
    $fieldtype = new Isco_08;

    expect($fieldtype->extraValidationAttributes())->toBe([])
        ->and($fieldtype->extraRules())->toBe([]);
});

it('builds the tier rules, requiring a major only when the field is required', function (array $config, bool $required): void {
    $fieldtype = new Isco_08;
    $fieldtype->setField(new Field('isco', array_merge(['type' => 'isco_08'], $config)));

    $majorRules = ['nullable', 'string', Rule::in(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'])];

    expect($fieldtype->extraRules())->toEqual([
        'isco.major_group' => $required ? ['required', ...$majorRules] : $majorRules,
        'isco.sub_major_group' => ['nullable'],
        'isco.minor_group' => ['nullable'],
        'isco.unit_group' => ['nullable'],
        'isco.value' => ['nullable', 'string'],
        'isco' => [new Isco08FieldtypeStoredRule($required)],
    ]);
})->with([
    'not required' => [[], false],
    'the field required' => [['required' => true], true],
    'required in the validate list' => [['validate' => ['array', 'required']], true],
    'a validate string, not a list' => [['validate' => 'required'], false],
    'a loose required match in the validate list' => [['validate' => [true]], false],
]);
