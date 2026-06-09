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
