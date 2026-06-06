import { describe, it, expect, beforeEach } from 'vitest';
import { gtm, reset } from '../../assets/adapters/gtm.js';
import { resetGlobals } from './helpers.js';

const purposeSignals = {
	analytics: [ 'analytics_storage' ],
	marketing: [ 'ad_storage' ],
};

function ctx( grants, calls ) {
	return {
		tag: { id: 'gtm', purposes: [ 'analytics' ] },
		adapterConfig: { purposeSignals },
		grants,
		granted: Boolean( grants.analytics ),
		gtag: ( ...a ) => calls.push( a ),
		win: window,
		doc: document,
	};
}

describe( 'adapters/gtm', () => {
	beforeEach( () => {
		resetGlobals();
		reset();
	} );

	it( 'pushes a consent update and a consentful.consent event', () => {
		const calls = [];
		gtm.apply( ctx( { analytics: true, marketing: false }, calls ) );
		const update = calls.find( ( a ) => a[ 0 ] === 'consent' && a[ 1 ] === 'update' );
		expect( update[ 2 ].analytics_storage ).toBe( 'granted' );
		expect( update[ 2 ].ad_storage ).toBe( 'denied' );

		const event = window.dataLayer.find( ( e ) => e && e.event === 'consentful.consent' );
		expect( event ).toBeTruthy();
		expect( event.consentfulGrants.analytics ).toBe( true );
	} );

	it( 'injects no tag', () => {
		const calls = [];
		const before = document.querySelectorAll( 'script' ).length;
		gtm.apply( ctx( { analytics: true }, calls ) );
		expect( document.querySelectorAll( 'script' ).length ).toBe( before );
	} );

	it( 'is idempotent per unchanged state', () => {
		const calls = [];
		const grants = { analytics: true };
		gtm.apply( ctx( grants, calls ) );
		gtm.apply( ctx( grants, calls ) );
		expect( calls.filter( ( a ) => a[ 1 ] === 'update' ).length ).toBe( 1 );
		expect(
			window.dataLayer.filter( ( e ) => e && e.event === 'consentful.consent' ).length
		).toBe( 1 );
	} );
} );
