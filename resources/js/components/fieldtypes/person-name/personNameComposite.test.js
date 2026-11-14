import { describe, expect, it } from 'vitest';

import { buildDisplayFormatOptions } from './personNameDisplayFormats.js';
import { composePersonName } from './personNameComposite.js';

/**
 * The Control Panel's join of `person_name` with the refinement fields kept
 * beside it (UAMS-Web/uams-statamic-archive#3186). Mirrors `tests/Unit/Support/PersonNameCompositeTest.php`,
 * so the display-format options offered match the ones the server accepts.
 */
const legalName = () => ({
	first_name: { text: 'Leonard', phonetic_text: null },
	no_middle_name: false,
	middle_name: { text: 'Horatio', phonetic_text: null },
	no_nickname: true,
	nickname: { text: null, phonetic_text: null },
	last_name: { text: 'McCoy', phonetic_text: null },
	no_generational_suffix: true,
	generational_suffix: null,
	display_format: null
});

describe('composePersonName', () => {
	it('returns null without a person name', () => {
		expect(composePersonName(undefined)).toBeNull();
		expect(composePersonName({ person_name: 'Leonard McCoy' })).toBeNull();
	});

	it('returns the stored name unchanged when no refinement field is present', () => {
		expect(composePersonName({ person_name: legalName() })).toEqual(legalName());
	});

	it('takes the nickname, the pronunciation and the format from their own fields', () => {
		const name = composePersonName({
			person_name: legalName(),
			nickname: { text: 'Bones', phonetic_text: 'kʌks' },
			name_pronunciation: { first_name: 'fu', last_name: null },
			name_display_format: 'nickname_only@ns'
		});

		expect(name.no_nickname).toBe(false);
		expect(name.nickname).toEqual({ text: 'Bones', phonetic_text: 'kʌks' });
		expect(name.first_name).toEqual({ text: 'Leonard', phonetic_text: 'fu' });
		expect(name.middle_name).toEqual({ text: 'Horatio', phonetic_text: null });
		expect(name.last_name).toEqual({ text: 'McCoy', phonetic_text: null });
		expect(name.display_format).toBe('nickname_only@ns');
	});

	it.each([
		['empty text', { text: '', phonetic_text: 'kʌks' }],
		['blank text', { text: '   ', phonetic_text: null }],
		['null', null]
	])('reads a nickname with %s as no nickname', (_label, nickname) => {
		const name = composePersonName({ person_name: { ...legalName(), no_nickname: false }, nickname });

		expect(name.no_nickname).toBe(true);
		expect(name.nickname).toEqual({ text: null, phonetic_text: null });
	});

	it('reads an empty display format as none', () => {
		expect(composePersonName({ person_name: legalName(), name_display_format: '' }).display_format).toBeNull();
	});

	it('offers nickname formats only once the nickname field holds one', () => {
		const without = buildDisplayFormatOptions(composePersonName({ person_name: legalName(), nickname: null }));
		const withNickname = buildDisplayFormatOptions(
			composePersonName({ person_name: legalName(), nickname: { text: 'Bones', phonetic_text: null } })
		);

		expect(without.some((option) => option.key.startsWith('nickname_only'))).toBe(false);
		expect(withNickname.some((option) => option.key.startsWith('nickname_only'))).toBe(true);
	});
});
