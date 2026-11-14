import { afterEach, describe, expect, it, vi } from 'vitest';

import { buildDisplayFormatOptions } from '../components/fieldtypes/person-name/personNameDisplayFormats.js';
import {
	PERSON_NAME_BOUND_ATTR,
	PERSON_NAME_SELECTOR,
	initPersonNameDisplayFormat
} from './personNameDisplayFormatField.js';

/**
 * The public-form display-format <select> wiring (UAMS-Web/uams-statamic#281).
 *
 * The host's front-end bundle registers this with its own Alpine, so these drive
 * it with a stand-in Alpine whose effect runs immediately and can be re-run, and
 * a stand-in <select> holding only what the wiring reads.
 */

const PLACEHOLDER = '<option value="">Please select...</option>';

/** A stand-in Alpine that records each effect and runs it at once. */
function fakeAlpine(scope) {
	const effects = [];
	return {
		effects,
		$data: () => scope,
		effect(fn) {
			effects.push(fn);
			fn();
		}
	};
}

/** A stand-in <select> inside a form, with the attributes the view emits. */
function fakeSelect({ handle = 'legal_name', includeNickname, inForm = true, placeholder = true } = {}) {
	const dataset = {};
	if (handle !== null) dataset.personNameHandle = handle;
	if (includeNickname !== undefined) dataset.personNameIncludeNickname = includeNickname;

	return {
		dataset,
		innerHTML: '',
		closest: (selector) => (selector === 'form' && inForm ? { tagName: 'FORM' } : null),
		querySelector: (selector) =>
			selector === 'option[value=""]' && placeholder ? { outerHTML: PLACEHOLDER } : null
	};
}

/** A legal name with a first and last name only. */
function composite(overrides = {}) {
	return {
		first_name: { text: 'Ann', phonetic_text: '' },
		no_middle_name: true,
		middle_name: { text: null, phonetic_text: null },
		no_nickname: true,
		nickname: { text: null, phonetic_text: null },
		last_name: { text: 'Lee', phonetic_text: '' },
		no_generational_suffix: true,
		generational_suffix: null,
		display_format: null,
		...overrides
	};
}

afterEach(() => {
	vi.restoreAllMocks();
});

describe('initPersonNameDisplayFormat', () => {
	it('names the selector and bound flag the host registers with', () => {
		expect(PERSON_NAME_SELECTOR).toBe('select[data-person-name-handle]');
		expect(PERSON_NAME_BOUND_ATTR).toBe('personNameBound');
	});

	it('replaces the options with the formats the name allows, after the placeholder', () => {
		const scope = { legal_name: composite() };
		const select = fakeSelect();

		initPersonNameDisplayFormat(select, fakeAlpine(scope));

		const expected = buildDisplayFormatOptions(scope.legal_name, { includeNickname: true })
			.map((opt) => `<option value="${opt.key}">${opt.label}</option>`)
			.join('');
		expect(expected).not.toBe('');
		expect(select.innerHTML).toBe(PLACEHOLDER + expected);
	});

	it('marks the chosen format as selected', () => {
		const allowed = buildDisplayFormatOptions(composite(), { includeNickname: true });
		const key = allowed[allowed.length - 1].key;
		const scope = { legal_name: composite({ display_format: key }) };
		const select = fakeSelect();

		initPersonNameDisplayFormat(select, fakeAlpine(scope));

		expect(select.innerHTML).toContain(`<option value="${key}" selected>`);
		expect(scope.legal_name.display_format).toBe(key);
	});

	it('clears a chosen format the name no longer allows, and rebuilds when the name changes', () => {
		const scope = { legal_name: composite({ display_format: 'not_a_format@ns' }) };
		const alpine = fakeAlpine(scope);
		const select = fakeSelect();

		initPersonNameDisplayFormat(select, alpine);

		expect(scope.legal_name.display_format).toBeNull();
		expect(select.innerHTML).not.toContain(' selected');

		scope.legal_name.first_name.text = 'Beth';
		alpine.effects[0]();

		expect(select.innerHTML).toContain('Beth Lee');
	});

	it('escapes the name parts a visitor typed', () => {
		const scope = { legal_name: composite({ first_name: { text: '<b>"A&\'', phonetic_text: '' } }) };
		const select = fakeSelect();

		initPersonNameDisplayFormat(select, fakeAlpine(scope));

		expect(select.innerHTML).toContain('&lt;b&gt;&quot;A&amp;&#39;');
		expect(select.innerHTML).not.toContain('<b>');
	});

	it('offers nickname formats unless the view turns the nickname off', () => {
		const withNickname = composite({ no_nickname: false, nickname: { text: 'Annie', phonetic_text: '' } });

		const on = fakeSelect();
		initPersonNameDisplayFormat(on, fakeAlpine({ legal_name: structuredClone(withNickname) }));
		const off = fakeSelect({ includeNickname: 'false' });
		initPersonNameDisplayFormat(off, fakeAlpine({ legal_name: structuredClone(withNickname) }));

		expect(on.innerHTML).toContain('Annie');
		expect(off.innerHTML).not.toContain('Annie');
	});

	it('keeps no placeholder when the view rendered none', () => {
		const select = fakeSelect({ placeholder: false });

		initPersonNameDisplayFormat(select, fakeAlpine({ legal_name: composite() }));

		expect(select.innerHTML.startsWith('<option value="">')).toBe(false);
		expect(select.innerHTML).toContain('Ann Lee');
	});

	it('leaves the options alone until the scope holds the composite', () => {
		const select = fakeSelect();

		initPersonNameDisplayFormat(select, fakeAlpine({ legal_name: null }));

		expect(select.innerHTML).toBe('');
	});

	it.each([
		['outside a form', { inForm: false }, {}, 'is not inside a <form>'],
		['without a handle', { handle: null }, {}, 'missing data-person-name-handle'],
		['without an Alpine scope', {}, null, 'no Alpine scope on the form']
	])('warns and installs no effect for a <select> %s', (_case, selectOptions, scope, message) => {
		const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
		const alpine = fakeAlpine(scope);

		initPersonNameDisplayFormat(fakeSelect(selectOptions), alpine);

		expect(alpine.effects).toHaveLength(0);
		expect(warn).toHaveBeenCalledOnce();
		expect(warn.mock.calls[0][0]).toContain(message);
	});
});
