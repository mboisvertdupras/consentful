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

export function anyAdSignalDenied( state ) {
	return AD_SIGNALS.some( ( signal ) => state[ signal ] === 'denied' );
}
