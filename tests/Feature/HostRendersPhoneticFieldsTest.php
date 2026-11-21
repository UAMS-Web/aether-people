<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests\Feature;

use Statamic\Facades\User;
use Statamic\Fields\Field;
use Tests\TestCase;
use UAMSWeb\Aether\People\Fieldtypes\PersonName;
use UAMSWeb\Aether\People\Fieldtypes\TextPhoneticPair;

/**
 * Host-bound check of the phonetic fieldtypes (UAMS-Web/aether-people#123): a real Statamic host
 * resolves `text_phonetic_pair` and `person_name` from this add-on, renders the public form field
 * from the add-on's own view, and serves the Control Panel build that registers the components.
 *
 * This class extends the HOST application's `Tests\TestCase`, so it runs only from the root of a
 * host (see `docs/DEVELOPMENT.md`), not in the standalone gate. A host that carries its own copy of
 * the field view or the Control Panel components (the site, until it removes them) decides by
 * registration order which copy wins, so the file assertions below hold on a host without copies.
 *
 * @command  php artisan test vendor/uams-web/aether-people/tests/Feature/HostRendersPhoneticFieldsTest.php
 */
final class HostRendersPhoneticFieldsTest extends TestCase
{
    /** The host's fieldtype registry returns this add-on's classes for both handles. */
    public function test_the_host_resolves_the_phonetic_fieldtypes_from_the_addon(): void
    {
        $this->assertInstanceOf(TextPhoneticPair::class, new Field('pronounced', ['type' => 'text_phonetic_pair'])->fieldtype());
        $this->assertInstanceOf(PersonName::class, new Field('name', ['type' => 'person_name'])->fieldtype());
    }

    /** The public form field view resolves to the add-on's file and renders both inputs. */
    public function test_the_host_renders_the_public_form_field_from_the_addon_view(): void
    {
        $field = new Field('pronounced', ['type' => 'text_phonetic_pair']);
        $view = $field->fieldtype()->view();

        $this->assertSame('statamic::forms.fields.text_phonetic_pair', $view);

        $path = realpath(view()->getFinder()->find($view));

        $this->assertIsString($path);
        $this->assertStringEndsWith(
            'resources/field-views/forms/fields/text_phonetic_pair.antlers.html',
            str_replace('\\', '/', $path),
        );

        $html = view($view, [
            ...$field->toArray(),
            'handle' => 'pronounced',
            'id' => 'contact_pronounced',
            'value' => null,
            'error' => null,
            ...$field->fieldtype()->extraRenderableFieldData(),
        ])->render();

        $this->assertStringContainsString('name="pronounced[text]"', $html);
        $this->assertStringContainsString('name="pronounced[phonetic_text]"', $html);
        $this->assertStringContainsString('lang="ipa"', $html);
    }

    /** The Control Panel page loads the add-on's published build, which registers the components. */
    public function test_the_control_panel_serves_the_build_that_registers_the_phonetic_components(): void
    {
        // An unsaved super user, so the test leaves nothing in the host's user store
        $user = User::make()->id('phonetic-test-user')->email('phonetic@example.test')->makeSuper();

        $response = $this->actingAs($user)->get('/cp/dashboard');

        $response->assertOk();

        preg_match('#/vendor/aether-people/build/assets/(cp-[\w-]+\.js)#', $response->getContent() ?: '', $match);

        $this->assertArrayHasKey(1, $match, 'The Control Panel page does not reference the add-on build.');

        $script = file_get_contents(public_path('vendor/aether-people/build/assets/'.$match[1]));

        $this->assertIsString($script);
        $this->assertStringContainsString('text_phonetic_pair-fieldtype', $script);
        $this->assertStringContainsString('text-phonetic-pair', $script);
    }
}
