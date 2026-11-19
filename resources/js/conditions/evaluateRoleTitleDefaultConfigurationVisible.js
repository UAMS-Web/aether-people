/**
 * Blueprint editor: show default title configuration when structured mode is
 * enabled and more than one configuration checkbox is selected.
 *
 * @param {Object} options
 * @param {*} options.target
 * @param {Object} [options.values]
 * @param {Object} [options.root]
 * @returns {boolean}
 */
export function evaluateRoleTitleDefaultConfigurationVisible({
	target,
	values,
	root
}) {
	const bag = values && typeof values === 'object' ? values : root;
	const structures = Array.isArray(target) ? target : bag?.enabled_title_structures;
	const configs = bag?.enabled_configurations;

	if (!Array.isArray(structures) || !structures.includes('structured')) {
		return false;
	}

	if (!Array.isArray(configs) || configs.length <= 1) {
		return false;
	}

	return true;
}
