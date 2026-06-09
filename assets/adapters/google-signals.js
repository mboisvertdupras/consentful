/**
 * Build a { signal: 'granted'|'denied' } payload from grants + a purpose→signals map.
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
