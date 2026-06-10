import { describe, it, expect } from 'vitest';
import { signalState, anyAdSignalDenied } from '../../assets/adapters/google-signals.js';

const purposeSignals = {
	necessary: [ 'security_storage' ],
	analytics: [ 'analytics_storage' ],
	marketing: [ 'ad_storage', 'ad_user_data', 'ad_personalization' ],
};

describe( 'google-signals signalState (§7)', () => {
	it( 'sets each signal to its purpose grant', () => {
		const state = signalState(
			{ necessary: true, analytics: true, marketing: false },
			purposeSignals
		);
		expect( state ).toEqual( {
			security_storage: 'granted',
			analytics_storage: 'granted',
			ad_storage: 'denied',
			ad_user_data: 'denied',
			ad_personalization: 'denied',
		} );
	} );

	it( 'security_storage is granted (rides on necessary)', () => {
		const state = signalState(
			{ necessary: true, analytics: false, marketing: false },
			purposeSignals
		);
		expect( state.security_storage ).toBe( 'granted' );
	} );

	it( 'tolerates a missing/garbage map', () => {
		expect( signalState( { necessary: true }, null ) ).toEqual( {} );
		expect( signalState( {}, {} ) ).toEqual( {} );
	} );
} );

describe( 'google-signals anyAdSignalDenied', () => {
	it( 'true when any ad signal denied', () => {
		expect( anyAdSignalDenied( { ad_storage: 'denied', analytics_storage: 'granted' } ) ).toBe(
			true
		);
	} );

	it( 'false when all ad signals granted', () => {
		expect(
			anyAdSignalDenied( {
				ad_storage: 'granted',
				ad_user_data: 'granted',
				ad_personalization: 'granted',
			} )
		).toBe( false );
	} );
} );
