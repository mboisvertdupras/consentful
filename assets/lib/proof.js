export function newConsentId( win ) {
	if ( win && win.crypto && typeof win.crypto.randomUUID === 'function' ) {
		return win.crypto.randomUUID();
	}
	return 'c-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2, 10 );
}

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
	} catch {}
	return false;
}
