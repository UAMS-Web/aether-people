/**
 * Mirrors UAMSWeb\Aether\People\Support\PersonNameDisplayFormats for CP previews. Keep keys and
 * labels aligned with PHP.
 */

const PATTERN_DEDUPE_RANK = {
	full_first_full_middle: 1,
	full_first_full_middle_nickname: 1,
	full_first_middle_initial: 3,
	full_first_middle_initial_nickname: 3,
	first_initial_full_middle: 2,
	first_initial_full_middle_nickname: 2,
	middle_only: 1,
	middle_only_nickname: 1,
	first_initial_middle_initial: 4,
	first_initial_middle_initial_nickname: 4,
	full_first_only: 1,
	full_first_only_nickname: 1,
	nickname_only: 1
};

/**
 * @param {string} segment
 * @returns {string}
 */
export function initials(segment) {
	const t = (segment || '').trim();
	if (!t) return '';
	const parts = t.split(/[\s\-]+/u).filter(Boolean);
	return parts.map((part) => (part[0] ? part[0] + '.' : '')).filter(Boolean).join(' ');
}

/**
 * @param {...string|null|undefined} parts
 * @returns {string}
 */
export function joinParts(...parts) {
	const filtered = parts
		.map((p) => (p == null ? '' : String(p).trim()))
		.filter((p) => p !== '');
	return filtered.join(' ');
}

/**
 * @param {string} base
 * @param {string|null|undefined} suffix
 * @returns {string}
 */
export function appendSuffix(base, suffix) {
	const b = (base || '').trim();
	if (suffix == null || String(suffix).trim() === '') return b;
	const s = String(suffix).trim();
	if (!b) return s;
	return `${b} ${s}`;
}

/**
 * @param {string} patternId
 * @param {boolean} withSuffix
 * @returns {string}
 */
export function composeKey(patternId, withSuffix) {
	const flag = withSuffix ? 'ws' : 'ns';
	return `${patternId}@${flag}`;
}

/**
 * Whether all name parts are “resolved” enough to build display format previews.
 * Mirrors `PersonNameDisplayFormats::isReadyForDisplayFormatOptions()` in PHP.
 *
 * Requires: first and last text; middle text or “no middle”; nickname text or
 * “no nickname”; generational suffix chosen or “no generational suffix”.
 *
 * @param {Record<string, unknown>|null|undefined} normalized
 * @param {{ includeNickname?: boolean }} [options]
 * @returns {boolean}
 */
export function isReadyForDisplayFormatOptions(normalized, options = {}) {
	const includeNickname = options.includeNickname !== false;
	const n = normalized || {};
	const first = String(n.first_name?.text ?? '').trim();
	const middle = String(n.middle_name?.text ?? '').trim();
	const last = String(n.last_name?.text ?? '').trim();
	const nick = String(n.nickname?.text ?? '').trim();
	const noMiddle = !!n.no_middle_name;
	const noNick = !!n.no_nickname;
	const noGen = !!n.no_generational_suffix;
	const suffixRaw = n.generational_suffix;
	const suffixStr =
		typeof suffixRaw === 'string' ? suffixRaw.trim() : '';
	const suffixSet = suffixStr !== '';

	if (first === '') {
		return false;
	}
	if (last === '') {
		return false;
	}
	if (!noMiddle && middle === '') {
		return false;
	}
	if (includeNickname && !noNick && nick === '') {
		return false;
	}
	if (!noGen && !suffixSet) {
		return false;
	}

	return true;
}

/**
 * Which display-format prerequisites are still unsatisfied (for CP checklist UI).
 * Keys match `isReadyForDisplayFormatOptions` checks; each value is true if that
 * part still needs editor action.
 *
 * @param {Record<string, unknown>|null|undefined} normalized
 * @param {{ includeNickname?: boolean }} [options]
 * @returns {{ firstName: boolean, middle: boolean, nickname: boolean, lastName: boolean, suffix: boolean }}
 */
export function displayFormatUnresolvedPrerequisites(normalized, options = {}) {
	const includeNickname = options.includeNickname !== false;
	const n = normalized || {};
	const first = String(n.first_name?.text ?? '').trim();
	const middle = String(n.middle_name?.text ?? '').trim();
	const last = String(n.last_name?.text ?? '').trim();
	const nick = String(n.nickname?.text ?? '').trim();
	const noMiddle = !!n.no_middle_name;
	const noNick = !!n.no_nickname;
	const noGen = !!n.no_generational_suffix;
	const suffixRaw = n.generational_suffix;
	const suffixStr =
		typeof suffixRaw === 'string' ? suffixRaw.trim() : '';
	const suffixSet = suffixStr !== '';

	return {
		firstName: first === '',
		middle: !noMiddle && middle === '',
		nickname: includeNickname && !noNick && nick === '',
		lastName: last === '',
		suffix: !noGen && !suffixSet
	};
}

/**
 * @param {Record<string, unknown>|null|undefined} normalized
 * @param {{ includeNickname?: boolean }} [options]
 * @returns {{ key: string, label: string, group: string }[]}
 */
export function buildDisplayFormatOptions(normalized, options = {}) {
	const includeNickname = options.includeNickname !== false;
	const n = normalized || {};

	if (!isReadyForDisplayFormatOptions(n, options)) {
		return [];
	}

	const first = String(n.first_name?.text ?? '').trim();
	const middle = String(n.middle_name?.text ?? '').trim();
	const last = String(n.last_name?.text ?? '').trim();
	const nick = String(n.nickname?.text ?? '').trim();
	const noMiddle = !!n.no_middle_name;
	const noNick = !!n.no_nickname;
	const noGen = !!n.no_generational_suffix;
	const suffixRaw = n.generational_suffix;
	const suffix =
		typeof suffixRaw === 'string' && suffixRaw.trim() !== ''
			? suffixRaw.trim()
			: null;

	const hasMiddle = !noMiddle && middle !== '';
	const hasNick = includeNickname && !noNick && nick !== '';
	const hasSuffix = !noGen && suffix != null && suffix !== '';

	const fI = initials(first);
	const mI = initials(middle);
	const qNick = hasNick ? `"${nick}"` : null;

	/** @type {{ key: string, label: string, group: string, rank: number, seq: number }[]} */
	const candidates = [];
	let seq = 0;

	/**
	 * @param {boolean} withSuf
	 */
	function addGroup(withSuf) {
		const suffixPart = withSuf && suffix ? suffix : null;
		const group = withSuf ? 'with_suffix' : 'without_suffix';

		/**
		 * @param {string} patternId
		 * @param {string|null} label
		 */
		function push(patternId, label) {
			if (!label || label.trim() === '') return;
			const key = composeKey(patternId, withSuf);
			const rank = PATTERN_DEDUPE_RANK[patternId] ?? 1;
			candidates.push({
				key,
				label,
				group,
				rank,
				seq: seq++
			});
		}

		if (hasMiddle) {
			push(
				'full_first_full_middle',
				appendSuffix(joinParts(first, middle, last), suffixPart)
			);
			if (hasNick && qNick) {
				push(
					'full_first_full_middle_nickname',
					appendSuffix(joinParts(first, middle, qNick, last), suffixPart)
				);
			}
			push(
				'full_first_middle_initial',
				appendSuffix(joinParts(first, mI, last), suffixPart)
			);
			if (hasNick && qNick) {
				push(
					'full_first_middle_initial_nickname',
					appendSuffix(joinParts(first, mI, qNick, last), suffixPart)
				);
			}
			push(
				'first_initial_full_middle',
				appendSuffix(joinParts(fI, middle, last), suffixPart)
			);
			if (hasNick && qNick) {
				push(
					'first_initial_full_middle_nickname',
					appendSuffix(joinParts(fI, middle, qNick, last), suffixPart)
				);
			}
			push('middle_only', appendSuffix(joinParts(middle, last), suffixPart));
			if (hasNick && qNick) {
				push(
					'middle_only_nickname',
					appendSuffix(joinParts(middle, qNick, last), suffixPart)
				);
			}
			push(
				'first_initial_middle_initial',
				appendSuffix(joinParts(fI, mI, last), suffixPart)
			);
			if (hasNick && qNick) {
				push(
					'first_initial_middle_initial_nickname',
					appendSuffix(joinParts(fI, mI, qNick, last), suffixPart)
				);
			}
		}

		push('full_first_only', appendSuffix(joinParts(first, last), suffixPart));
		if (hasNick && qNick) {
			push(
				'full_first_only_nickname',
				appendSuffix(joinParts(first, qNick, last), suffixPart)
			);
		}
		if (hasNick) {
			push('nickname_only', appendSuffix(joinParts(nick, last), suffixPart));
		}
	}

	if (hasSuffix) {
		addGroup(true);
	}
	addGroup(false);

	// Candidates arrive in ascending seq order, so on an equal rank the row
	// already kept has the lower seq and stays: no seq tie-break is needed
	const byLabel = {};
	for (const c of candidates) {
		const prev = byLabel[c.label];
		if (!prev || c.rank > prev.rank) {
			byLabel[c.label] = c;
		}
	}

	const survivors = Object.values(byLabel).sort((a, b) => a.seq - b.seq);

	return survivors.map(({ key, label, group }) => ({ key, label, group }));
}

/**
 * @param {string[]} allowedKeys
 * @param {string|null|undefined} currentKey
 * @returns {string|null}
 */
export function coerceDisplayFormatKey(allowedKeys, currentKey) {
	if (!allowedKeys || allowedKeys.length === 0) return null;
	if (currentKey && allowedKeys.includes(currentKey)) return currentKey;
	return null;
}
