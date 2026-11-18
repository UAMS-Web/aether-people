<?php

declare(strict_types=1);

/**
 * The Control Panel side of the phonetic fieldtypes moved from the host site
 * (UAMS-Web/aether-people#123): the add-on registers `text_phonetic_pair`, the
 * `person_name` editor uses the add-on's own phonetic partials rather than the
 * host's globals, and every lang key the Vue components read is one the add-on's
 * lang file defines.
 *
 * @command  vendor/bin/pest tests/Unit/PhoneticPartialsTest.php
 */

/**
 * The contents of a file under the repository root.
 *
 * @param  string  $path  The path, relative to the repository root.
 */
function phoneticPartialsSource(string $path): string
{
    $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

    return mustBeString($contents, $path);
}

/**
 * Every `aether-people::fieldtypes.*` key a Control Panel source file reads, without the prefix.
 *
 * @return list<string>
 */
function phoneticPartialsVueKeys(): array
{
    $keys = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/js', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || ! in_array($file->getExtension(), ['vue', 'js'], true)) {
            continue;
        }

        preg_match_all("/__\\('aether-people::fieldtypes\\.([A-Za-z0-9_.]+)'\\)/", mustBeString(file_get_contents($file->getPathname()), 'source'), $matches);
        array_push($keys, ...$matches[1]);
    }

    $keys = array_values(array_unique($keys));
    sort($keys);

    return $keys;
}

/**
 * The keys of the add-on's `fieldtypes` lang file.
 *
 * @return list<string>
 */
function phoneticPartialsLangKeys(): array
{
    /** @var array<string, string> $lines */
    $lines = require dirname(__DIR__, 2).'/lang/en/fieldtypes.php';

    return array_keys($lines);
}

it('defines every add-on lang key the Control Panel components read', function (): void {
    $vueKeys = phoneticPartialsVueKeys();

    // The scan finds keys from both phonetic surfaces, so an empty result cannot pass
    expect($vueKeys)->toContain('phonetic_instructions.alert_heading')
        ->toContain('text_phonetic_pair.field.default_phonetic_label')
        ->toContain('person_name.field.first_name_text')
        ->and(array_values(array_diff($vueKeys, phoneticPartialsLangKeys())))->toBe([]);
});

it('carries the phonetic instructions and every text_phonetic_pair key in the add-on namespace', function (): void {
    $langKeys = phoneticPartialsLangKeys();

    expect(array_values(array_filter($langKeys, fn (string $key): bool => str_starts_with($key, 'phonetic_instructions.'))))
        ->toBe([
            'phonetic_instructions.alert_heading',
            'phonetic_instructions.p1',
            'phonetic_instructions.p2_html',
            'phonetic_instructions.p3_html',
            'phonetic_instructions.p4_html',
            'phonetic_instructions.p5_html',
        ])
        ->and(array_filter($langKeys, fn (string $key): bool => str_starts_with($key, 'text_phonetic_pair.config.')))->toHaveCount(19)
        ->and(array_filter($langKeys, fn (string $key): bool => str_starts_with($key, 'text_phonetic_pair.field.')))->toHaveCount(4);
});

it('reads no phonetic key from the host statamic namespace', function (string $path): void {
    expect(phoneticPartialsSource($path))->not->toContain('statamic::fieldtypes.');
})->with([
    'resources/js/components/fieldtypes/TextPhoneticPairFieldtype.vue',
    'resources/js/components/fieldtypes/partials/PhoneticTextInstructions.vue',
    'resources/js/components/fieldtypes/partials/PhoneticTextInput.vue',
    'resources/js/components/fieldtypes/partials/TextPhoneticPair.vue',
    'resources/js/components/fieldtypes/PersonNameFieldtype.vue',
    'src/Fieldtypes/TextPhoneticPair.php',
]);

it('registers the text_phonetic_pair fieldtype component', function (): void {
    expect(phoneticPartialsSource('resources/js/cp.js'))
        ->toContain("import TextPhoneticPairFieldtype from './components/fieldtypes/TextPhoneticPairFieldtype.vue'")
        ->toContain("Statamic.\$components.register('text_phonetic_pair-fieldtype', TextPhoneticPairFieldtype)");
});

it('renders person_name with the add-on partials, registered under the names its template uses', function (): void {
    $source = phoneticPartialsSource('resources/js/components/fieldtypes/PersonNameFieldtype.vue');

    expect($source)->toContain("import PhoneticTextInstructions from './partials/PhoneticTextInstructions.vue';")
        ->toContain("import TextPhoneticPair from './partials/TextPhoneticPair.vue';")
        ->toContain("'phonetic-text-instructions-alert': PhoneticTextInstructions")
        ->toContain("'text-phonetic-pair': TextPhoneticPair")
        ->toContain('<phonetic-text-instructions-alert />')
        ->toContain('<text-phonetic-pair');
});

it('renders text_phonetic_pair with the add-on partials', function (): void {
    expect(phoneticPartialsSource('resources/js/components/fieldtypes/TextPhoneticPairFieldtype.vue'))
        ->toContain("import PhoneticTextInstructions from './partials/PhoneticTextInstructions.vue';")
        ->toContain("import TextPhoneticPairInput from './partials/TextPhoneticPair.vue';")
        ->and(phoneticPartialsSource('resources/js/components/fieldtypes/partials/TextPhoneticPair.vue'))
        ->toContain("import PhoneticTextInput from './PhoneticTextInput.vue';");
});
