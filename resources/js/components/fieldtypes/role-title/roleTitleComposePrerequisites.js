/**
 * Compose checklist for the role_title CP field: evaluates rules shipped in
 * `meta.compose_checklist_by_configuration` from `config/role_title.php`.
 */

/**
 * Read a dotted path from a plain object (e.g. `scope.scope_joiner`).
 *
 * @param {object|undefined} root
 * @param {string} path
 * @returns {unknown}
 */
function getPath(root, path) {
	if (!root || typeof root !== 'object' || typeof path !== 'string') {
		return undefined;
	}
	const parts = path.split('.');
	let cur = root;
	for (const p of parts) {
		if (cur == null || typeof cur !== 'object') {
			return undefined;
		}
		cur = cur[p];
	}
	return cur;
}

/**
 * Ordered option keys for a preload slot from `meta.option_label_maps`.
 *
 * @param {object|undefined} meta
 * @param {string} slot
 * @returns {string[]}
 */
function optionKeysForPreloadSlot(meta, slot) {
	const raw = meta?.option_label_maps?.[slot];
	if (!Array.isArray(raw)) {
		return [];
	}
	return raw
		.map((row) => (row && typeof row.key === 'string' ? row.key : ''))
		.filter(Boolean);
}

/**
 * Outstanding checklist labels before save-time composition.
 *
 * @param {object|undefined} data  Current field value
 * @param {object|undefined} meta  Fieldtype preload meta
 * @param {{ showConfigurationPicker?: boolean }} options
 * @returns {{ label: string }[]}
 */
export function roleTitleComposeChecklistOutstanding(
	data,
	meta,
	options = {}
) {
	const labels = [];
	if (data?.mode === 'unstructured') {
		return labels;
	}
	const handles = meta?.configuration_handles_canonical;
	const canonical = Array.isArray(handles) ? handles : [];
	const cfg = typeof data?.configuration === 'string' ? data.configuration : '';

	// Flag missing configuration when the picker is visible
	if (
		options.showConfigurationPicker
		&& (cfg === '' || !canonical.includes(cfg))
	) {
		const l = meta?.compose_checklist_configuration_alert_label;
		if (typeof l === 'string' && l !== '') {
			labels.push({ label: l });
		}
	}

	// Treat missing values as having no further checklist rows
	if (!data || typeof data !== 'object') {
		return labels;
	}

	const rulesByCfg = meta?.compose_checklist_by_configuration;
	const rules =
		rulesByCfg && typeof rulesByCfg === 'object' ? rulesByCfg[cfg] : null;

	// Skip per-field rules when the active mode has no rule list
	if (!Array.isArray(rules)) {
		return labels;
	}

	const noneSentinel = meta?.optional_radio_none;

	for (const rule of rules) {
		if (!rule || typeof rule !== 'object') {
			continue;
		}
		const kind = rule.kind;
		const path = rule.path;
		const alertLabel =
			typeof rule.alert_label === 'string' ? rule.alert_label : String(path);

		// Optional radios: only `undefined` or empty string count as unset
		if (kind === 'optional_radio') {
			const v = getPath(data, path);
			if (v === undefined || v === '') {
				labels.push({ label: alertLabel });
			}
			continue;
		}

		// Required radios: null, empty, or optional-none sentinel are unset
		if (kind === 'required_option') {
			const v = getPath(data, path);
			if (v == null || v === '' || v === noneSentinel) {
				labels.push({ label: alertLabel });
			}
			continue;
		}

		// Required nested option when a sibling path is non-empty (e.g. joiner vs name)
		if (kind === 'required_when_sibling_nonempty') {
			const siblingPath =
				typeof rule.sibling_path === 'string' ? rule.sibling_path : '';
			const slot =
				typeof rule.option_preload_slot === 'string'
					? rule.option_preload_slot
					: '';
			const sibVal = getPath(data, siblingPath);
			const nameStr = typeof sibVal === 'string' ? sibVal : '';
			if (nameStr.trim() === '') {
				continue;
			}
			const j = getPath(data, path);
			const valid = optionKeysForPreloadSlot(meta, slot);
			if (typeof j !== 'string' || !valid.includes(j)) {
				labels.push({ label: alertLabel });
			}
		}
	}

	return labels;
}

/**
 * @param {{ label: string }[]} outstanding
 * @returns {boolean}
 */
export function roleTitleHasOutstandingChecklist(outstanding) {
	return Array.isArray(outstanding) && outstanding.length > 0;
}
