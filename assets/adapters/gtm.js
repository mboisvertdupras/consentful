/**
 * GTM handler (Delegated — consent push). Injects no tag; instead pushes the Consent
 * Mode `consent update` plus a `consentful.consent` dataLayer event so a delegated tag
 * manager honors the visitor's choice. Idempotent per state.
 */

import { signalState } from './google-signals.js';
import { resolveGtag } from './google-gtag.js';

let lastStateJson = null;

/** Reset module state (test seam). */
export function reset() {
	lastStateJson = null;
}

export const gtm = {
	/**
	 * @param {object}   ctx
	 * @param {object}   ctx.adapterConfig { purposeSignals? }.
	 * @param {object}   ctx.grants        Purpose key => bool.
	 * @param {Window}   ctx.win
	 * @param {Function} [ctx.gtag]        Optional pre-resolved gtag (else resolved from win).
	 */
	apply( ctx ) {
		const { adapterConfig, grants, win } = ctx;
		const gtag = ctx.gtag || resolveGtag( win );
		const purposeSignals =
			adapterConfig && adapterConfig.purposeSignals ? adapterConfig.purposeSignals : {};
		const state = signalState( grants, purposeSignals );

		const stateJson = JSON.stringify( state );
		if ( stateJson === lastStateJson ) {
			return;
		}
		lastStateJson = stateJson;

		if ( Object.keys( state ).length > 0 ) {
			gtag( 'consent', 'update', state );
		}
		win.dataLayer = win.dataLayer || [];
		win.dataLayer.push( {
			event: 'consentful.consent',
			consentfulGrants: { ...grants },
		} );
	},
};
