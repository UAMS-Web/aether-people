/**
 * Public-form reactivity for the `person_name` display-format <select>.
 *
 * The Control Panel component recomputes the display-format options as the
 * name parts change; this does the same on a public form, with the same
 * `buildDisplayFormatOptions` module, so the two surfaces offer the same set.
 *
 * The add-on's own Vite build loads only in the Control Panel, so the host's
 * front-end bundle registers this: it imports the selector, the bound-flag
 * name and `initPersonNameDisplayFormat`, and hands them to its own engine
 * lifecycle helper with its Alpine instance. Alpine is passed in rather than
 * imported, so the add-on never brings a second copy of it into the host's
 * bundle.
 *
 * Lifecycle:
 *
 *   1. The form-field view (`resources/field-views/forms/fields/person_name.antlers.html`)
 *      emits `data-person-name-handle` and `data-person-name-include-nickname`
 *      on the display-format <select> when the blueprint keeps
 *      `include_display_format` on (the default).
 *   2. Once Alpine has initialized the form's `x-data` scope, the host calls
 *      `initPersonNameDisplayFormat` for each matching <select>. It resolves the
 *      form's scope with `Alpine.$data(form)` and installs an `Alpine.effect()`
 *      that reads the composite's name parts, so a change to any of them
 *      rebuilds the <option> elements.
 *   3. When the selected key falls out of the recomputed set (a required name
 *      part was cleared, say), `coerceDisplayFormatKey` maps it to null and the
 *      effect writes that back to the form scope, which keeps the submitted
 *      value valid against `PersonNameDisplayFormatRule`.
 */

import {
	buildDisplayFormatOptions,
	coerceDisplayFormatKey,
} from '../components/fieldtypes/person-name/personNameDisplayFormats.js';

/** Selector for the display-format <select>s opted into reactive recomputation. */
export const PERSON_NAME_SELECTOR = 'select[data-person-name-handle]';

/** The `dataset` flag that marks a <select> as already wired. */
export const PERSON_NAME_BOUND_ATTR = 'personNameBound';

/**
 * Wire one display-format <select>: resolve the form's Alpine scope, then
 * install an effect that rebuilds the <option> list on every relevant
 * subfield change.
 *
 * @param {HTMLSelectElement} select The display-format <select>.
 * @param {{ $data: (el: Element) => any, effect: (fn: () => void) => unknown }} Alpine The host's Alpine instance.
 */
export function initPersonNameDisplayFormat(select, Alpine) {
	const form = select.closest('form');
	if (!form) {
		console.warn('[person-name-engine] <select> is not inside a <form>:', select);
		return;
	}

	const handle = select.dataset.personNameHandle;
	if (!handle) {
		console.warn('[person-name-engine] missing data-person-name-handle:', select);
		return;
	}

	// The kebab-case form of the blueprint's `include_nickname` config. It defaults
	// to true, as in the Control Panel, and only the string "false" turns it off.
	const includeNickname = select.dataset.personNameIncludeNickname !== 'false';

	const formScope = Alpine.$data(form);
	if (!formScope) {
		console.warn('[person-name-engine] no Alpine scope on the form:', form);
		return;
	}

	// Keep the placeholder <option value=""> so the rebuild preserves the
	// blueprint's "Please select..." choice. Read up front, because the effect
	// replaces the <select>'s markup on each run.
	const placeholderOption = select.querySelector('option[value=""]');
	const placeholderHtml = placeholderOption ? placeholderOption.outerHTML : '';

	Alpine.effect(() => {
		const composite = formScope[handle];
		if (composite == null || typeof composite !== 'object') return;

		// `buildDisplayFormatOptions` is pure and reads the shape the form's x-data
		// exposes (per PersonName::emptyValue). Reading the nested fields here is
		// what registers Alpine dependencies, so the effect re-runs when any of
		// them change.
		const options = buildDisplayFormatOptions(composite, { includeNickname });
		const allowedKeys = options.map((opt) => opt.key);
		const currentKey = composite.display_format ?? null;
		const coercedKey = coerceDisplayFormatKey(allowedKeys, currentKey);

		const optionsHtml = options
			.map((opt) => {
				const selected = opt.key === coercedKey ? ' selected' : '';
				return `<option value="${escapeHtml(opt.key)}"${selected}>${escapeHtml(opt.label)}</option>`;
			})
			.join('');

		select.innerHTML = placeholderHtml + optionsHtml;

		// When the edits invalidated the prior selection, write the coerced value
		// back so the form state and the rendered markup agree. Skip an unchanged
		// write, which would re-trigger the effect.
		if (currentKey !== coercedKey && composite.display_format !== coercedKey) {
			composite.display_format = coercedKey;
		}
	});
}

/**
 * Minimal HTML escaping for option values and labels. The labels are built from
 * the name parts a visitor typed, so escape them to keep stray characters from
 * breaking the <option> markup.
 *
 * @param {string} s
 * @returns {string}
 */
function escapeHtml(s) {
	return String(s)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');
}
