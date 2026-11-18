<?php

declare(strict_types=1);

use Statamic\Fields\Field;
use UAMSWeb\Aether\People\Fieldtypes\TextPhoneticPair;

/**
 * The public-form view for `text_phonetic_pair`, which the add-on supplies in the
 * `statamic` view namespace after a host's overrides (UAMS-Web/aether-people#123).
 *
 * @command  vendor/bin/pest tests/Integration/Views/TextPhoneticPairFormViewTest.php
 */

/**
 * Render a `text_phonetic_pair` field the way Statamic's form tag does.
 *
 * @param  array<string, mixed>  $config  The field's blueprint config.
 * @param  mixed  $value  The submitted or old value.
 */
function textPhoneticPairFormViewRender(array $config = [], mixed $value = null): string
{
    $field = new Field('pronounced', ['type' => 'text_phonetic_pair', ...$config]);
    $fieldtype = mustBe(TextPhoneticPair::class, $field->fieldtype(), 'fieldtype');
    /** @var view-string $view */
    $view = mustBeString($fieldtype->view(), 'view');

    return view($view, mustBeStringKeyed([
        ...$field->toArray(),
        'handle' => 'pronounced',
        'id' => 'contact_pronounced',
        'value' => $value,
        'error' => null,
        ...$fieldtype->extraRenderableFieldData(),
    ], 'view data'))->render();
}

it('renders the text_phonetic_pair field with the add-on view', function (): void {
    $fieldtype = mustBe(TextPhoneticPair::class, new Field('pronounced', ['type' => 'text_phonetic_pair'])->fieldtype(), 'fieldtype');

    expect($fieldtype->view())->toBe('statamic::forms.fields.text_phonetic_pair')
        ->and(realpath(view()->getFinder()->find('statamic::forms.fields.text_phonetic_pair')))
        ->toBe(dirname(__DIR__, 3).'/resources/field-views/forms/fields/text_phonetic_pair.antlers.html');
});

it('renders a text and a phonetic input under the field handle', function (): void {
    $html = textPhoneticPairFormViewRender(['text_label' => 'Name', 'phonetic_label' => 'Name (IPA)']);

    expect($html)->toContain('name="pronounced[text]"')
        ->toContain('name="pronounced[phonetic_text]"')
        ->toContain('lang="ipa"')
        ->toContain('>Name</label>')
        ->toContain('>Name (IPA)</label>')
        ->toContain('The phonetic text uses the International Phonetic Alphabet (IPA).');
});

it('hides the IPA note when the blueprint turns it off', function (): void {
    expect(textPhoneticPairFormViewRender(['include_phonetic_instructions' => false]))
        ->not->toContain('International Phonetic Alphabet');
});

it('escapes a submitted value in both inputs', function (): void {
    $payload = '"><img src=x onerror=alert(1)>';
    $html = textPhoneticPairFormViewRender([], ['text' => $payload, 'phonetic_text' => $payload]);

    expect($html)->not->toContain('<img src=x')
        ->toContain('value="&quot;&gt;&lt;img src=x onerror=alert(1)&gt;"');
});
