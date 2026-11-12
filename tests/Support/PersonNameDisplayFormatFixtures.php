<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests\Support;

use RuntimeException;
use UAMSWeb\Aether\People\Support\PersonNameDisplayFormats;

/**
 * Builds the display-format fixtures that hold `PersonNameDisplayFormats::buildOptions()`
 * and its JavaScript twin, `buildDisplayFormatOptions()`, to the same output (UAMS-Web/uams-statamic-archive#3053).
 *
 * The corpus is `tests/Fixtures/PersonNameDisplayFormats/corpus.json`. The fixture,
 * `display-format-options.json` beside it, is generated from the PHP by
 * `REGENERATE_COMMAND` and committed. A Pest test rebuilds it in memory and fails when the
 * committed file differs, and a Vitest spec runs the JavaScript over the committed cases,
 * so each CI job stays on its own toolchain (UAMS-Web/uams-statamic-archive#3043).
 *
 * **The nickname-off mapping.** The JavaScript takes `{ includeNickname: false }`, which
 * `buildOptions()` does not. The PHP sees a field with `include_nickname: false` through
 * `PersonName::applyIncludeNicknameConfig()`, which sets `no_nickname` and clears the
 * nickname pair before any option is built. `options()` applies that same step for a case
 * whose `include_nickname` is false, and the spec passes `includeNickname` from the same
 * flag, so both sides read one corpus entry.
 *
 * Depends only on Composer's autoloader, so the generator script can run it without
 * booting the application.
 */
final class PersonNameDisplayFormatFixtures
{
    /**
     * The command that rewrites the committed fixture from the PHP.
     */
    public const string REGENERATE_COMMAND = 'php scripts/generate-person-name-display-format-fixtures.php';

    /**
     * Absolute path of the input corpus.
     */
    public static function corpusPath(): string
    {
        return __DIR__.'/../Fixtures/PersonNameDisplayFormats/corpus.json';
    }

    /**
     * Absolute path of the committed fixture.
     */
    public static function fixturePath(): string
    {
        return __DIR__.'/../Fixtures/PersonNameDisplayFormats/display-format-options.json';
    }

    /**
     * The corpus cases.
     *
     * @return list<array{label: string, include_nickname: bool, value: array<string, mixed>}>
     *
     * @throws RuntimeException When the corpus is missing, unreadable, or malformed
     */
    public static function corpus(): array
    {
        $decoded = self::readJson(self::corpusPath());
        $rawCases = $decoded['cases'] ?? null;

        if (! \is_array($rawCases) || $rawCases === []) {
            throw new RuntimeException('The person-name corpus has no cases: '.self::corpusPath());
        }

        $cases = [];

        foreach ($rawCases as $index => $case) {
            if (
                ! \is_array($case)
                || ! \is_string($case['label'] ?? null)
                || ! \is_bool($case['include_nickname'] ?? null)
                || ! \is_array($case['value'] ?? null)
            ) {
                throw new RuntimeException(\sprintf('Corpus case %s needs a string label, a boolean include_nickname and an object value.', $index));
            }

            /** @var array<string, mixed> $value */
            $value = $case['value'];

            $cases[] = [
                'label' => $case['label'],
                'include_nickname' => $case['include_nickname'],
                'value' => $value,
            ];
        }

        return $cases;
    }

    /**
     * The options `buildOptions()` returns for one corpus value.
     *
     * @param  array<string, mixed>  $value  A raw person-name value
     * @return array<int, array{key: string, label: string, group: string}>
     */
    public static function options(array $value, bool $includeNickname): array
    {
        $normalized = PersonNameDisplayFormats::normalizeValue($value);

        // Mirrors PersonName::applyIncludeNicknameConfig() for a field that hides the nickname
        if (! $includeNickname) {
            $normalized['no_nickname'] = true;
            $normalized['nickname'] = ['text' => null, 'phonetic_text' => null];
        }

        return PersonNameDisplayFormats::buildOptions($normalized);
    }

    /**
     * The fixture document, built from the PHP in memory.
     *
     * @return array{_about: string, _regenerate: string, cases: list<array{label: string, include_nickname: bool, value: array<string, mixed>, options: array<int, array{key: string, label: string, group: string}>}>}
     */
    public static function build(): array
    {
        $cases = [];

        foreach (self::corpus() as $case) {
            $cases[] = [
                ...$case,
                'options' => self::options($case['value'], $case['include_nickname']),
            ];
        }

        return [
            '_about' => 'Generated from ' . PersonNameDisplayFormats::class . '::buildOptions() over corpus.json (UAMS-Web/uams-statamic-archive#3053). Do not edit by hand.',
            '_regenerate' => self::REGENERATE_COMMAND,
            'cases' => $cases,
        ];
    }

    /**
     * The fixture document as the bytes committed to disk.
     */
    public static function encode(): string
    {
        return \json_encode(
            self::build(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        )."\n";
    }

    /**
     * Read and decode a JSON object file.
     *
     * @return array<mixed>
     *
     * @throws RuntimeException When the file is missing, unreadable, or not a JSON object
     */
    private static function readJson(string $path): array
    {
        $contents = \is_file($path) ? \file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException('Cannot read '.$path);
        }

        $decoded = \json_decode($contents, true);

        if (! \is_array($decoded)) {
            throw new RuntimeException('Not a JSON object: '.$path);
        }

        return $decoded;
    }
}
