export function readCookie( name, doc = document ) {
	const jar = typeof doc.cookie === 'string' ? doc.cookie : '';
	const prefix = name + '=';
	for ( const part of jar.split( ';' ) ) {
		const c = part.trim();
		if ( c.indexOf( prefix ) === 0 ) {
			return decodeURIComponent( c.slice( prefix.length ) );
		}
	}
	return null;
}

export function writeCookie( name, payload, { maxAgeMs, secure }, doc = document ) {
	const value = encodeURIComponent( JSON.stringify( payload ) );
	const expires = new Date( Date.now() + maxAgeMs ).toUTCString();
	const isSecure =
		secure === true ||
		( secure == null &&
			typeof location !== 'undefined' &&
			location.protocol === 'https:' );
	let cookie = name + '=' + value + '; path=/; SameSite=Lax; expires=' + expires;
	if ( isSecure ) {
		cookie += '; Secure';
	}
	doc.cookie = cookie;
}

export function parseConsent( rawValue ) {
	if ( typeof rawValue !== 'string' || rawValue === '' ) {
		return null;
	}
	try {
		const data = JSON.parse( rawValue );
		return data && typeof data === 'object' && ! Array.isArray( data ) ? data : null;
	} catch {
		return null;
	}
}

export function serializeConsent( {
	schemaVersion,
	policyVersion,
	jurisdiction,
	grants,
	timestamp,
} ) {
	const g = {};
	for ( const key of Object.keys( grants || {} ) ) {
		g[ key ] = grants[ key ] ? 1 : 0;
	}
	return {
		v: schemaVersion,
		p: policyVersion,
		j: jurisdiction,
		g,
		t: timestamp,
	};
}

export function validateConsent(
	payload,
	{ schemaVersion, policyVersion, maxAgeMs, now = Date.now() }
) {
	if ( ! payload || typeof payload !== 'object' ) {
		return null;
	}
	if ( payload.v !== schemaVersion || payload.p !== policyVersion ) {
		return null;
	}
	const t = payload.t;
	if ( typeof t !== 'number' || t <= 0 || now - t > maxAgeMs ) {
		return null;
	}
	const rawGrants = payload.g;
	if ( ! rawGrants || typeof rawGrants !== 'object' || Array.isArray( rawGrants ) ) {
		return null;
	}
	const grants = {};
	for ( const key of Object.keys( rawGrants ) ) {
		grants[ key ] = Boolean( rawGrants[ key ] );
	}
	return {
		grants,
		jurisdiction: typeof payload.j === 'string' ? payload.j : '',
		schemaVersion: payload.v,
		policyVersion: payload.p,
		timestamp: t,
	};
}
