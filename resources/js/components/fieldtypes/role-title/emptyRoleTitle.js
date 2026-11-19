/**
 * Default role_title value for the CP from preload (`empty_value_shared`,
 * `configuration_handles_canonical`). No client-side fallback shape: PHP always
 * ships `empty_value_shared` via `RoleTitleValue::fieldtypePreloadMetaFromDefinition()`.
 */

/**
 * @param {string|null} [configuration]
 * @param {object|undefined} [meta]
 * @returns {object}
 */
export function emptyRoleTitle(configuration = null, meta = undefined) {
	const handles = meta?.configuration_handles_canonical;
	const allowed = Array.isArray(handles) ? new Set(handles) : new Set();
	const cfg =
		typeof configuration === 'string' && allowed.has(configuration)
			? configuration
			: null;

	const ev = meta?.empty_value_shared;
	const structureHandles =
		Array.isArray(meta?.enabled_title_structures) &&
		meta.enabled_title_structures.length > 0
			? meta.enabled_title_structures
			: ['structured'];
	const pickMode = (raw) => {
		if (typeof raw === 'string' && structureHandles.includes(raw)) {
			return raw;
		}
		const def = meta?.default_title_structure;
		if (typeof def === 'string' && structureHandles.includes(def)) {
			return def;
		}
		return structureHandles[0];
	};

	if (ev && typeof ev === 'object' && !Array.isArray(ev)) {
		const shared = JSON.parse(JSON.stringify(ev));
		const out = {
			configuration: cfg,
			...shared
		};
		out.mode = pickMode(out.mode);
		return out;
	}

	return { configuration: cfg, mode: pickMode(undefined) };
}
