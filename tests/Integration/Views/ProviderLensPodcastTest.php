<?php

declare(strict_types=1);

use Illuminate\View\FileViewFinder;
use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use UAMSWeb\Aether\People\Tests\Fixtures\HostStandIns\CspNonceStandIn;
use UAMSWeb\Aether\People\Tests\Fixtures\HostStandIns\SanitizeUrlStandIn;

/**
 * Render coverage for the UAMS Health Talk podcast section of the clinical provider
 * lens (UAMS-Web/uams-statamic-archive#1683) and its Find-a-Doc global configuration (UAMS-Web/uams-statamic-archive#1688).
 *
 * Rendered as a partial with a synthetic scope so nothing reaches the network: the
 * RadioMD script is never fetched, only its `<script src>` asserted.
 *
 * @command  vendor/bin/pest tests/Integration/Views/ProviderLensPodcastTest.php
 */
beforeEach(function (): void {
    // Render the partials against the host partials they include, stood in by the fixtures: the
    // rendering ones ahead of the marker-only ones, so a section's content reaches the output
    app('view')->addLocation(dirname(__DIR__, 2).'/Fixtures/HostPartials');
    mustBe(FileViewFinder::class, app('view')->getFinder(), 'the view finder')->prependLocation(dirname(__DIR__, 2).'/Fixtures/HostPartialsRendered');

    // The host's `sanitize_url` modifier and `csp_nonce` tag
    app('statamic.modifiers')->put('sanitize_url', SanitizeUrlStandIn::class);
    app('statamic.tags')->put('csp_nonce', CspNonceStandIn::class);
});

/**
 * The `uams_fad_settings` global as the cascade exposes it, under its own handle.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function healthTalkSettings(array $overrides = []): array
{
    return [
        'uams_fad_settings' => $overrides + [
            'health_talk_query' => new LabeledValue(true, 'Yes'),
            'health_talk_script_url' => 'https://radiomd.com/widget/easyXDM.js',
            'health_talk_client' => 'uams',
            'health_talk_station_id' => 303,
            'health_talk_program_id' => 1837,
        ],
    ];
}

/**
 * Render the lens body with the given person scope and global settings.
 *
 * @param  array<string, mixed>  $person
 * @param  array<string, mixed>  $settings
 */
function renderProviderLensPodcast(array $person, array $settings = []): string
{
    $scope = [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
    ] + $person + ($settings === [] ? healthTalkSettings() : $settings);

    return (string) Antlers::parse('{{ partial:people/provider/profile }}', $scope, true);
}

it('renders the podcast section for a provider with a guest name', function (): void {
    $html = renderProviderLensPodcast(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']);

    expect($html)->toContain('provider-podcast')
        ->and($html)->toContain('id="radiomd-embedded-filtered-doctor"')
        // The provider's name, not just the copy around it - asserting only
        // "Listen to the episodes featuring" stays green with the interpolation
        // deleted, shipping "Listen to the episodes featuring ."
        ->and($html)->toContain('Listen to the episodes featuring Dr. McCoy.')
        ->and($html)->toContain('src="https://radiomd.com/widget/easyXDM.js"');
});

it('renders no podcast section for a whitespace-only guest name', function (): void {
    // The gate trims (UAMS-Web/uams-statamic-archive#1695). Without it a name of spaces is a truthy string, so the
    // section rendered a heading over "Listen to the episodes featuring ." and a
    // RadioMD mount keyed on nothing - and `ProviderProfileBodyResolver`, which does
    // trim, then reported the body empty and dropped the foot-of-page CTA from a
    // profile that visibly had a section on it.
    $html = renderProviderLensPodcast(['podcast_guest_name_uams_health_talk' => '   ']);

    expect($html)->not->toContain('provider-podcast')
        ->and($html)->not->toContain('Listen to the episodes featuring');
});

it("gives the podcast lede the design system's lead class", function (): void {
    // WordPress wraps this sentence in its `lead` + centered wrapper classes. `lead`
    // is carried over - it is the design system's own lede class, the one
    // `block_more` and the 404 page use - and the centering deliberately is not:
    // every other block on this profile is left-aligned (UAMS-Web/uams-statamic-archive#1695).
    $html = renderProviderLensPodcast(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']);

    expect($html)->toContain('class="lead provider-podcast-lede"');
});

// The one thing that makes the section degrade to empty rather than broken when the
// third-party script is blocked or fails to load.
it('guards the widget call on the vendor function having loaded', function (): void {
    $html = renderProviderLensPodcast(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']);

    expect($html)->toContain("typeof radiomd_embedded_filtered_doctor !== 'function'")
        ->and($html)->toContain('return;');
});

it('renders no podcast section for a provider without a guest name', function (): void {
    $html = renderProviderLensPodcast([]);

    // No empty heading, and - the part a `{{ if }}` around only the copy would miss -
    // no orphaned third-party script tag or mount point either.
    expect($html)->not->toContain('provider-podcast')
        ->and($html)->not->toContain('radiomd-embedded-filtered-doctor')
        ->and($html)->not->toContain('radiomd.com');
});

// --- The configured values (UAMS-Web/uams-statamic-archive#1688) ------------------------------------------

it('carries the configured vendor arguments, not RadioMD literals', function (): void {
    $html = renderProviderLensPodcast(
        ['podcast_guest_name_uams_health_talk' => 'Leonard McCoy'],
        healthTalkSettings([
            'health_talk_client' => 'starfleet',
            'health_talk_station_id' => 909,
            'health_talk_program_id' => 4747,
            'health_talk_script_url' => 'https://radiomd.example/widget/other.js',
        ])
    );

    // A config change is provably reflected: none of the WordPress literals survive.
    expect($html)->toContain('data-radiomd-client="starfleet"')
        ->and($html)->toContain('data-radiomd-station="909"')
        ->and($html)->toContain('data-radiomd-program="4747"')
        ->and($html)->toContain('src="https://radiomd.example/widget/other.js"')
        ->and($html)->not->toContain('"uams"')
        ->and($html)->not->toContain('303')
        ->and($html)->not->toContain('1837');
});

it('renders nothing at all when the toggle is off', function (): void {
    $html = renderProviderLensPodcast(
        ['podcast_guest_name_uams_health_talk' => 'Leonard McCoy'],
        healthTalkSettings(['health_talk_query' => new LabeledValue(false, 'No')])
    );

    expect($html)->not->toContain('provider-podcast')
        ->and($html)->not->toContain('radiomd');
});

it('still renders on an unsaved global, matching the field default and WordPress', function (): void {
    // `health_talk_query` augments to a LabeledValue, so a bare truthiness test would
    // pass for an explicit "No" too - this pins the `!== false` form, which has to
    // treat a NULL (never-saved) global as "on" while still honoring an explicit "No".
    $html = renderProviderLensPodcast(
        ['podcast_guest_name_uams_health_talk' => 'Leonard McCoy'],
        healthTalkSettings(['health_talk_query' => null])
    );

    expect($html)->toContain('provider-podcast');
});

it('omits the more-episodes link until a destination is configured', function (): void {
    $withoutDestination = renderProviderLensPodcast(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']);

    $withDestination = renderProviderLensPodcast(
        ['podcast_guest_name_uams_health_talk' => 'Leonard McCoy'],
        healthTalkSettings(['health_talk_more_url' => '/health/podcast'])
    );

    expect($withoutDestination)->not->toContain('Listen to More Episodes')
        ->and($withDestination)->toContain('Listen to More Episodes')
        ->and($withDestination)->toContain('href="/health/podcast"');
});

// --- Escaping (UAMS-Web/uams-statamic-archive#1683) --------------------------------------------------------

it('gives a hostile guest name no path into the widget call', function (): void {
    // Antlers is raw by default and a script body is not an HTML context, so the
    // guest name never reaches JavaScript source: it crosses into the script through
    // a `data-` attribute, where `sanitize` genuinely neutralizes the quote that
    // would otherwise break out of the attribute.
    $html = renderProviderLensPodcast([
        'podcast_guest_name_uams_health_talk' => 'McCoy", 0, 0, "");alert(1);//<script>\\',
    ]);

    // The payload's inert text may well appear - it is data in an HTML attribute, and
    // harmless there. What must NOT appear is a form that ESCAPES that attribute: a
    // literal quote closing it early, or a live tag.
    expect($html)->toContain('provider-podcast')
        ->and($html)->toContain('&quot;')
        ->and($html)->not->toContain('data-radiomd-guest="McCoy",')
        ->and($html)->not->toContain('<script>\\')
        ->and($html)->not->toContain('");alert(1)')
        // The call itself reads from `dataset`, so no editor text is ever interpolated
        // into JavaScript source in the first place.
        ->and($html)->toContain('mount.dataset.radiomdGuest')
        ->and($html)->not->toContain('radiomd_embedded_filtered_doctor("');
});

it('escapes the provider name in the podcast lead copy', function (): void {
    $html = renderProviderLensPodcast([
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy<svg/onload=alert(1)></svg>'],
        'podcast_guest_name_uams_health_talk' => 'Leonard McCoy',
    ]);

    expect($html)->toContain('provider-podcast')
        ->and($html)->not->toContain('<svg/onload')
        ->and($html)->not->toContain('</svg>');
});

// --- CSP (UAMS-Web/uams-statamic-archive#1683) ---------------------------------------------------------------

// The `security-headers` directives that allow the RadioMD hosts are the host's config and stay in the site's test.
it('nonce-gates the inline style the widget needs', function (): void {
    // `style-src` is listed under `nonce_directives` and drops 'unsafe-inline', so an
    // un-nonced inline <style> is blocked the moment CSP is enforced.
    $html = renderProviderLensPodcast(['podcast_guest_name_uams_health_talk' => 'Leonard McCoy']);

    expect($html)->toContain('<style nonce=');
});
