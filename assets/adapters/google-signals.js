/**
 * Google-owned signal-state mapping (§7) — translates effective grants into the
 * Google Consent Mode v2 signal payload. This is Google/CMv2 vocabulary, so it lives
 * with the Google-family adapters, NOT in the vendor-neutral lib/ (mirrors how PHP
 * confines the Purpose→Signal map to GoogleAdapter). Shared by the decider's
 * Google-justified default block, google.js, and gtm.js.
 */

/**
 * Build a { signal: 'granted'|'denied' } payload from grants + a purpose→signals map.
 *
 * Every signal listed under a purpose takes that purpose's grant. security_storage
 * rides on `necessary` (always granted), so it resolves to 'granted'.
 *
 * @param {object} grants         Purpose key => bool.
 * @param {object} purposeSignals Purpose key => signal-string[].
 * @return {object} Signal string => 'granted'|'denied'.
 */
export function signalState( grants, purposeSignals ) {
	const state = {};
	const map = purposeSignals && typeof purposeSignals === 'object' ? purposeSignals : {};
	for ( const purposeKey of Object.keys( map ) ) {
		const signals = Array.isArray( map[ purposeKey ] ) ? map[ purposeKey ] : [];
		const value = grants[ purposeKey ] ? 'granted' : 'denied';
		for ( const signal of signals ) {
			state[ signal ] = value;
		}
	}
	return state;
}

/** Signals that gate advertising; used to decide ads_data_redaction. */
const AD_SIGNALS = [ 'ad_storage', 'ad_user_data', 'ad_personalization' ];

/**
 * Whether any advertising signal is denied (⇒ enable ads_data_redaction).
 *
 * @param {object} state Signal => 'granted'|'denied'.
 * @return {boolean}
 */
export function anyAdSignalDenied( state ) {
	return AD_SIGNALS.some( ( signal ) => state[ signal ] === 'denied' );
}
