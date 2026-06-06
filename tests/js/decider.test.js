import { describe, it, expect, beforeEach } from 'vitest';
import { init } from '../../assets/decider.js';
import { makeConfig, resetGlobals, setGpc, seedCookie } from './helpers.js';

/** Pull the args of the gtag('consent','default', ...) call out of dataLayer. */
function defaultCall() {
	return ( window.dataLayer || [] )
		.map( ( a ) => Array.from( a ) )
		.find( ( a ) => a[ 0 ] === 'consent' && a[ 1 ] === 'default' );
}

function calls( command, key ) {
	return ( window.dataLayer || [] )
		.map( ( a ) => Array.from( a ) )
		.filter( ( a ) => a[ 0 ] === command && ( key === undefined || a[ 1 ] === key ) );
}

describe( 'decider', () => {
	beforeEach( resetGlobals );

	it( 'opt-in fallback denies all non-essential in the consent default', () => {
		init( makeConfig(), { win: window, doc: document } );
		const args = defaultCall();
		expect( args ).toBeTruthy();
		const state = args[ 2 ];
		expect( state.security_storage ).toBe( 'granted' );
		expect( state.analytics_storage ).toBe( 'denied' );
		expect( state.ad_storage ).toBe( 'denied' );
		expect( state.wait_for_update ).toBe( 500 );
	} );

	it( 'sets ads_data_redaction (ad denied) and url_passthrough', () => {
		init( makeConfig(), { win: window, doc: document } );
		expect( calls( 'set', 'ads_data_redaction' ).length ).toBe( 1 );
		expect( calls( 'set', 'url_passthrough' ).length ).toBe( 1 );
	} );

	it( 'GPC denies even when a cookie grants', () => {
		setGpc( true );
		seedCookie( { v: 1, p: 1, j: '*', g: { analytics: 1, marketing: 1 }, t: Date.now() } );
		const result = init( makeConfig(), { win: window, doc: document } );
		expect( result.gpc ).toBe( true );
		expect( result.grants.analytics ).toBe( false );
		expect( result.grants.marketing ).toBe( false );
		expect( defaultCall()[ 2 ].analytics_storage ).toBe( 'denied' );
	} );

	it( 'reflects a valid stored decision', () => {
		seedCookie( { v: 1, p: 1, j: '*', g: { analytics: 1, marketing: 0 }, t: Date.now() } );
		const result = init( makeConfig(), { win: window, doc: document } );
		expect( result.hasDecision ).toBe( true );
		expect( result.grants.analytics ).toBe( true );
		expect( defaultCall()[ 2 ].analytics_storage ).toBe( 'granted' );
	} );

	it( 'has no decision when no cookie present', () => {
		const result = init( makeConfig(), { win: window, doc: document } );
		expect( result.hasDecision ).toBe( false );
	} );

	it( 'emits the consent default at most once (idempotent)', () => {
		init( makeConfig(), { win: window, doc: document } );
		init( makeConfig(), { win: window, doc: document } );
		expect( calls( 'consent', 'default' ).length ).toBe( 1 );
	} );

	it( 'installs the registerAdapter stub queue and _init', () => {
		const result = init( makeConfig(), { win: window, doc: document } );
		expect( typeof window.consentful.registerAdapter ).toBe( 'function' );
		const impl = { apply() {} };
		window.consentful.registerAdapter( 'custom', impl );
		expect( window.consentful._adapterQueue ).toEqual( [ [ 'custom', impl ] ] );
		expect( window.consentful._init ).toEqual( result );
	} );

	it( 'defines a gtag shim that pushes to dataLayer', () => {
		init( makeConfig(), { win: window, doc: document } );
		expect( typeof window.gtag ).toBe( 'function' );
		expect( Array.isArray( window.dataLayer ) ).toBe( true );
	} );
} );
