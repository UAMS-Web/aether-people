import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

import { describe, expect, it } from 'vitest';

import { buildDisplayFormatOptions } from './personNameDisplayFormats.js';

/**
 * Parity with `UAMSWeb\Aether\People\Support\PersonNameDisplayFormats::buildOptions()` (UAMS-Web/uams-statamic-archive#3053).
 *
 * The fixture is generated from the PHP by
 * `php scripts/generate-person-name-display-format-fixtures.php`, and a Pest test
 * keeps it current. Each case's `include_nickname` is passed as `includeNickname`;
 * the PHP side applies the same flag the way `PersonName::applyIncludeNicknameConfig()`
 * does (`no_nickname` set, nickname cleared).
 *
 * The file is read at collection time, so a missing or malformed fixture fails the
 * run instead of skipping it.
 */
const fixturePath = fileURLToPath(
	new URL(
		'../../../../../tests/Fixtures/PersonNameDisplayFormats/display-format-options.json',
		import.meta.url
	)
);
const fixture = JSON.parse(readFileSync(fixturePath, 'utf8'));

describe('buildDisplayFormatOptions matches the PHP fixtures', () => {
	it('has cases to check', () => {
		expect(Array.isArray(fixture.cases)).toBe(true);
		expect(fixture.cases.length).toBeGreaterThan(0);
	});

	it.each(fixture.cases.map((testCase) => [testCase.label, testCase]))(
		'%s',
		(_label, testCase) => {
			expect(
				buildDisplayFormatOptions(testCase.value, {
					includeNickname: testCase.include_nickname
				})
			).toEqual(testCase.options);
		}
	);
});
