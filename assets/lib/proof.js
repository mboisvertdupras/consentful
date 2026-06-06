/**
 * Proof of consent — client side of the durable Consent record (ADR 0002). On each
 * decision the gate posts a pseudonymous record to the non-cached REST endpoint; the
 * server stamps its own time and hashes IP/UA. Fire-and-forget: every error is swallowed
 * so a proof failure never breaks the consent pipeline.
 */

/**
 * Generate a pseudonymous client consent id.
 *
 * @param {Window} win Window (for crypto.randomUUID when available).
 * @return {string} A random id.
 */
export function newConsentId( win ) {
	if ( win && win.crypto && typeof win.crypto.randomUUID === 'function' ) {
		return win.crypto.randomUUID();
	}
	return 'c-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2, 10 );
}

/**
 * POST a Consent record to the proof endpoint. Prefers navigator.sendBeacon (survives
 * unload); falls back to fetch keepalive. Returns false (sends nothing) when no endpoint.
 *
 * @param {string} endpoint Absolute endpoint URL.
 * @param {object} payload  The §2 record body.
 * @param {Window} win      Window (for navigator.sendBeacon / fetch).
 * @return {boolean} True when a send was attempted, false otherwise.
 */
export function sendConsentRecord( endpoint, payload, win ) {
	if ( ! endpoint ) {
		return false;
	}
	const body = JSON.stringify( payload );
	try {
		if ( win && win.navigator && typeof win.navigator.sendBeacon === 'function' ) {
			const blob = new Blob( [ body ], { type: 'application/json' } );
			if ( win.navigator.sendBeacon( endpoint, blob ) ) {
				return true;
			}
		}
		if ( win && typeof win.fetch === 'function' ) {
			win.fetch( endpoint, {
				method: 'POST',
				credentials: 'omit',
				keepalive: true,
				headers: { 'Content-Type': 'application/json' },
				body,
			} ).catch( () => {} );
			return true;
		}
	} catch {
		// Never let a proof send throw into the consent pipeline.
	}
	return false;
}
