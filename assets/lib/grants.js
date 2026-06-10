/**
 * Compute the effective per-purpose grant map.
 *
 * @param {object}  args
 * @param {Array}   args.purposes [{ key, alwaysOn }] in display order.
 * @param {object}  args.policy   { defaultGranted: string[] }.
 * @param {?object} args.stored   Validated decision { grants } or null.
 * @param {boolean} args.gpc      GPC present.
 * @return {object} Purpose key => bool.
 */
export function computeGrants( { purposes, policy, stored, gpc } ) {
	const defaultGranted = ( policy && Array.isArray( policy.defaultGranted ) )
		? policy.defaultGranted
		: [];
	const grants = {};
	for ( const purpose of purposes || [] ) {
		const key = purpose.key;
		if ( purpose.alwaysOn ) {
			grants[ key ] = true;
		} else if ( gpc ) {
			grants[ key ] = false;
		} else if ( stored && stored.grants ) {
			grants[ key ] = Boolean( stored.grants[ key ] );
		} else {
			grants[ key ] = defaultGranted.indexOf( key ) !== -1;
		}
	}
	return grants;
}

/**
 * Whether a tag may fire.
 *
 * @param {object} tag    { purposes: string[] }.
 * @param {object} grants Purpose key => bool.
 * @return {boolean}
 */
export function isTagGranted( tag, grants ) {
	const purposes = tag && Array.isArray( tag.purposes ) ? tag.purposes : [];
	if ( purposes.length === 0 ) {
		return false;
	}
	for ( const key of purposes ) {
		if ( ! grants[ key ] ) {
			return false;
		}
	}
	return true;
}
