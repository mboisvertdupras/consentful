/**
 * Cookie read/write + the v1 consent schema. Mirrors PHP Consent
 * (to_cookie/from_cookie/is_valid); the cookie is the runtime source of truth.
 *
 * Shape: { v, p, j, g: { purposeKey: 0|1 }, t } — versions + jurisdiction +
 * per-purpose grants (0/1 ints) + epoch-millis timestamp.
 */

/**
 * Read a raw cookie value by name.
 *
 * @param {string}   name Cookie name.
 * @param {Document} doc  Document (defaults to global document).
 * @return {?string} Decoded value, or null when absent.
 */
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

/**
 * Write a cookie carrying a JSON payload.
 *
 * @param {string}   name             Cookie name.
 * @param {object}   payload          Serializable payload (the v1 consent object).
 * @param {object}   opts             Options.
 * @param {number}   opts.maxAgeMs    Lifetime in ms (sets expires).
 * @param {boolean} [opts.secure]     Force the Secure flag (else inferred from https).
 * @param {Document} doc              Document (defaults to global document).
 */
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

/**
 * Parse a raw cookie value into the decoded v1 payload (no validation).
 *
 * @param {?string} rawValue Decoded cookie string.
 * @return {?object} Decoded payload, or null on parse failure.
 */
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

/**
 * Serialize a consent decision to the compact v1 cookie shape (grants as 0/1).
 *
 * @param {object} consent              Decision.
 * @param {number} consent.schemaVersion
 * @param {number} consent.policyVersion
 * @param {string} consent.jurisdiction
 * @param {object} consent.grants       Purpose key => bool.
 * @param {number} consent.timestamp    Epoch millis.
 * @return {object} The { v, p, j, g, t } payload.
 */
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

/**
 * Validate a stored decision: versions must match, timestamp must be positive, and the
 * re-consent window must not have lapsed (mirrors PHP Consent::is_valid). Anything else
 * is treated as no decision.
 *
 * @param {?object} payload         Decoded v1 payload.
 * @param {object}  expect          Expectations.
 * @param {number}  expect.schemaVersion
 * @param {number}  expect.policyVersion
 * @param {number}  expect.maxAgeMs
 * @param {number} [expect.now]     Current epoch millis.
 * @return {?object} { grants, jurisdiction, schemaVersion, policyVersion, timestamp }, or null.
 */
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
