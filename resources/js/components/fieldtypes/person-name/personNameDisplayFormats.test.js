import { describe, expect, it } from 'vitest';

import { buildDisplayFormatOptions } from './personNameDisplayFormats.js';

/**
 * Equal-rank label collisions in the Control Panel display-format builder
 * (UAMS-Web/uams-statamic-archive#3019).
 *
 * Two patterns of the same dedupe rank can render the same label, which is the
 * only input that could reach the `seq` tie-break removed in UAMS-Web/uams-statamic-archive#3019 (and on the
 * PHP side in UAMS-Web/uams-statamic-archive#3008). Candidates arrive in ascending `seq` order, so the row
 * pushed first survives either way. These pin that outcome, and give the
 * JavaScript twin of `UAMSWeb\Aether\People\Support\PersonNameDisplayFormats` an automated check.
 */
function person(overrides) {
	return {
		first_name: { text: 'Ann' },
		last_name: { text: 'Lee' },
		no_middle_name: true,
		no_nickname: true,
		no_generational_suffix: true,
		...overrides
	};
}

describe('buildDisplayFormatOptions equal-rank collisions', () => {
	it('keeps middle_only over full_first_only when the middle name repeats the first', () => {
		const options = buildDisplayFormatOptions(
			person({ middle_name: { text: 'Ann' }, no_middle_name: false })
		);

		expect(options).toEqual([
			{ key: 'full_first_full_middle@ns', label: 'Ann Ann Lee', group: 'without_suffix' },
			{ key: 'full_first_middle_initial@ns', label: 'Ann A. Lee', group: 'without_suffix' },
			{ key: 'first_initial_full_middle@ns', label: 'A. Ann Lee', group: 'without_suffix' },
			{ key: 'middle_only@ns', label: 'Ann Lee', group: 'without_suffix' },
			{ key: 'first_initial_middle_initial@ns', label: 'A. A. Lee', group: 'without_suffix' }
		]);
	});

	it('keeps full_first_only over nickname_only when the nickname repeats the first name', () => {
		const options = buildDisplayFormatOptions(
			person({ nickname: { text: 'Ann' }, no_nickname: false })
		);

		expect(options).toEqual([
			{ key: 'full_first_only@ns', label: 'Ann Lee', group: 'without_suffix' },
			{ key: 'full_first_only_nickname@ns', label: 'Ann "Ann" Lee', group: 'without_suffix' }
		]);
	});

	it('resolves the collision the same way in both suffix groups', () => {
		const options = buildDisplayFormatOptions(
			person({
				nickname: { text: 'Ann' },
				no_nickname: false,
				generational_suffix: 'Jr.',
				no_generational_suffix: false
			})
		);

		expect(options.map(({ key }) => key)).toEqual([
			'full_first_only@ws',
			'full_first_only_nickname@ws',
			'full_first_only@ns',
			'full_first_only_nickname@ns'
		]);
	});
});
