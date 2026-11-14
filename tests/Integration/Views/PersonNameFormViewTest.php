<?php

declare(strict_types=1);

use Statamic\Fields\Field;
use UAMSWeb\Aether\People\Fieldtypes\PersonName;

/**
 * The public-form views for `person_name`, which the add-on supplies in the
 * `statamic` view namespace after a host's overrides (UAMS-Web/uams-statamic#281).
 *
 * @command  vendor/bin/pest tests/Integration/Views/PersonNameFormViewTest.php
 */

/**
 * Render a `person_name` field the way Statamic's form tag does.
 *
 * @param  array<string, mixed>  $config  The field's blueprint config.
 */
function personNameFormViewRender(array $config = []): string
{
    $field = new Field('legal_name', ['type' => 'person_name', ...$config]);
    $fieldtype = mustBe(PersonName::class, $field->fieldtype(), 'fieldtype');
    /** @var view-string $view */
    $view = mustBeString($fieldtype->view(), 'view');

    return view($view, mustBeStringKeyed([
        ...$field->toArray(),
        'handle' => 'legal_name',
        'id' => 'contact_legal_name',
        'value' => null,
        ...$fieldtype->extraRenderableFieldData(),
    ], 'view data'))->render();
}

it('renders the person_name field with the add-on view', function (): void {
    $fieldtype = mustBe(PersonName::class, new Field('legal_name', ['type' => 'person_name'])->fieldtype(), 'fieldtype');

    expect($fieldtype->view())->toBe('statamic::forms.fields.person_name')
        ->and(realpath(view()->getFinder()->find('statamic::forms.fields.person_name')))
        ->toBe(dirname(__DIR__, 3).'/resources/field-views/forms/fields/person_name.antlers.html');
});

it('renders each name part through the pair partial', function (): void {
    $html = personNameFormViewRender();

    foreach (['first_name', 'middle_name', 'nickname', 'last_name'] as $part) {
        expect($html)->toContain("name=\"legal_name[{$part}][text]\"");
    }
});
