/**
 * The element id of one subfield of a has-subfields fieldtype.
 *
 * `fieldId` is the id Statamic gives the fieldtype (its `id` prop), which
 * carries the publish container and the field's full path, for example
 * `field_base_address`. Building on it gives two containers rendering the same
 * fieldtype different ids (UAMS-Web/uams-statamic#327). Without a `fieldId`, as when the component
 * renders outside a publish form, the parent handle stands in for it.
 *
 * The subfield handle follows a double underscore. Statamic's handles cannot
 * contain one, and it joins a field's path with single underscores, so a
 * subfield id never equals the id of another field: an `address` field
 * `postal` and a sibling field `postal_city` stay apart.
 *
 * The label (SubfieldContainer) and the input (HasSubfields) both call this, so
 * each `label[for]` names its own input.
 *
 * This is a copy of the host application's helper of the same name, which its
 * own has-subfields fieldtypes use. Keep the two identical.
 *
 * @param {string|null|undefined} fieldId The fieldtype's id from Statamic.
 * @param {string} parentHandle The fieldtype's handle.
 * @param {string} subfieldHandle The subfield's handle.
 * @returns {string}
 */
export function subfieldId(fieldId, parentHandle, subfieldHandle) {
	const base = fieldId ? String(fieldId) : `field_${parentHandle}`;

	return `${base}__${subfieldHandle}`.replace(/\./g, '_');
}
