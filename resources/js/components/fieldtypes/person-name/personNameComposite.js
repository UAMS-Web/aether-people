/**
 * Mirrors UAMSWeb\Aether\People\Support\PersonNameComposite::fromData() for the CP: joins
 * the legal `person_name` value with the refinement fields kept beside it
 * (UAMS-Web/uams-statamic-archive#3186), so the display-format options are built from the same name the
 * server validates against. Keep the two in step.
 */

export const PERSON_NAME = 'person_name';
export const NICKNAME = 'nickname';
export const PRONUNCIATION = 'name_pronunciation';
export const DISPLAY_FORMAT = 'name_display_format';

const PRONOUNCED_PARTS = ['first_name', 'middle_name', 'last_name'];

/**
 * @param {unknown} value
 * @returns {boolean}
 */
function isPlainObject(value) {
	return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * The combined name held by field values keyed by handle, or null when they
 * hold no `person_name`.
 *
 * @param {Record<string, unknown>|null|undefined} values Publish form values
 * @returns {Record<string, unknown>|null}
 */
export function composePersonName(values) {
	const v = isPlainObject(values) ? values : {};

	if (!isPlainObject(v[PERSON_NAME])) {
		return null;
	}

	const name = { ...v[PERSON_NAME] };

	// Give each legal part the pronunciation kept beside it
	const pronunciation = v[PRONUNCIATION];

	if (isPlainObject(pronunciation)) {
		for (const part of PRONOUNCED_PARTS) {
			if (!Object.prototype.hasOwnProperty.call(pronunciation, part)) {
				continue;
			}

			const phonetic = pronunciation[part];
			name[part] = {
				...(isPlainObject(name[part]) ? name[part] : {}),
				phonetic_text: typeof phonetic === 'string' ? phonetic : null
			};
		}
	}

	// Take the nickname from its own field, reading an empty one as none
	if (Object.prototype.hasOwnProperty.call(v, NICKNAME)) {
		const nickname = isPlainObject(v[NICKNAME]) ? v[NICKNAME] : {};
		const text = nickname.text;
		const phonetic = nickname.phonetic_text;
		const hasNickname = typeof text === 'string' && text.trim() !== '';

		name.no_nickname = !hasNickname;
		name.nickname = {
			text: hasNickname ? text : null,
			phonetic_text: hasNickname && typeof phonetic === 'string' ? phonetic : null
		};
	}

	// Take the display format from its own field
	if (Object.prototype.hasOwnProperty.call(v, DISPLAY_FORMAT)) {
		const format = v[DISPLAY_FORMAT];
		name.display_format = typeof format === 'string' && format.trim() !== '' ? format : null;
	}

	return name;
}
