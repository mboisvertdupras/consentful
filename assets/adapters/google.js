/**
 * Google adapter handler (Direct, Consent Mode v2). On apply: push a `consent update`
 * when the signal state changed, and — once a mapped purpose is granted — load gtag.js
 * once per measurement id. Idempotent; never uses document.write.
 */

import { signalState } from './google-signals.js';
import { resolveGtag } from './google-gtag.js';

let lastStateJson = null;
const loaded = new Set();

/** Reset module state (test seam). */
export function reset() {
	lastStateJson = null;
	loaded.clear();
}

const loadGtag = ( id, gtag, doc ) => {
	if ( loaded.has( id ) ) {
		return;
	}
	loaded.add( id );
	const script = doc.createElement( 'script' );
	script.async = true;
	script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent( id );
	const first = doc.getElementsByTagName( 'script' )[ 0 ];
	if ( first && first.parentNode ) {
		first.parentNode.insertBefore( script, first );
	} else {
		( doc.head || doc.documentElement ).appendChild( script );
	}
	gtag( 'js', new Date() );
	gtag( 'config', id );
};

export const google = {
	/**
	 * @param {object}   ctx
	 * @param {object}   ctx.adapterConfig { measurementIds, purposeSignals }.
	 * @param {object}   ctx.grants        Purpose key => bool.
	 * @param {Window}   ctx.win
	 * @param {Document} ctx.doc
	 * @param {Function} [ctx.gtag]        Optional pre-resolved gtag (else resolved from win).
	 */
	apply( ctx ) {
		const { adapterConfig, grants, win, doc } = ctx;
		const gtag = ctx.gtag || resolveGtag( win );
		const purposeSignals =
			adapterConfig && adapterConfig.purposeSignals ? adapterConfig.purposeSignals : {};
		const state = signalState( grants, purposeSignals );

		const stateJson = JSON.stringify( state );
		if ( stateJson !== lastStateJson ) {
			lastStateJson = stateJson;
			gtag( 'consent', 'update', state );
		}

		const anyGranted = Object.keys( state ).some( ( signal ) => state[ signal ] === 'granted' );
		if ( ! anyGranted ) {
			return;
		}
		const ids = Array.isArray( adapterConfig && adapterConfig.measurementIds )
			? adapterConfig.measurementIds
			: [];
		for ( const id of ids ) {
			if ( id ) {
				loadGtag( String( id ), gtag, doc );
			}
		}
	},
};
