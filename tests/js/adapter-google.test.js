import { describe, it, expect, beforeEach } from 'vitest';
import { google, reset } from '../../assets/adapters/google.js';
import { resetGlobals } from './helpers.js';

const purposeSignals = {
	necessary: [ 'security_storage' ],
	analytics: [ 'analytics_storage' ],
	marketing: [ 'ad_storage', 'ad_user_data', 'ad_personalization' ],
};

function ctx( grants, calls ) {
	return {
		tag: { id: 'ga4', purposes: [ 'analytics' ] },
		adapterConfig: { measurementIds: [ 'G-ABC' ], purposeSignals },
		grants,
		granted: Boolean( grants.analytics ),
		gtag: ( ...a ) => calls.push( a ),
		win: window,
		doc: document,
	};
}

describe( 'adapters/google', () => {
	beforeEach( () => {
		resetGlobals();
		reset();
		document.querySelectorAll( 'script[src*="gtag/js"]' ).forEach( ( s ) => s.remove() );
	} );

	it( 'pushes a consent update with the mapped signals', () => {
		const calls = [];
		google.apply( ctx( { necessary: true, analytics: true, marketing: false }, calls ) );
		const update = calls.find( ( a ) => a[ 0 ] === 'consent' && a[ 1 ] === 'update' );
		expect( update[ 2 ].analytics_storage ).toBe( 'granted' );
		expect( update[ 2 ].ad_storage ).toBe( 'denied' );
	} );

	it( 'loads gtag.js once when a mapped purpose is granted', () => {
		const calls = [];
		google.apply( ctx( { necessary: true, analytics: true }, calls ) );
		google.apply( ctx( { necessary: true, analytics: true }, calls ) );
		const scripts = document.querySelectorAll( 'script[src*="gtag/js?id=G-ABC"]' );
		expect( scripts.length ).toBe( 1 );
		expect( scripts[ 0 ].async ).toBe( true );
	} );

	it( 'does not load gtag.js when all signals denied', () => {
		const calls = [];
		google.apply( ctx( { necessary: false, analytics: false, marketing: false }, calls ) );
		expect( document.querySelectorAll( 'script[src*="gtag/js"]' ).length ).toBe( 0 );
	} );

	it( 'does not re-push an unchanged consent state', () => {
		const calls = [];
		const grants = { necessary: true, analytics: true };
		google.apply( ctx( grants, calls ) );
		google.apply( ctx( grants, calls ) );
		expect( calls.filter( ( a ) => a[ 1 ] === 'update' ).length ).toBe( 1 );
	} );

	it( 'never uses document.write', () => {
		const calls = [];
		const original = document.write;
		let called = false;
		document.write = () => {
			called = true;
		};
		google.apply( ctx( { necessary: true, analytics: true }, calls ) );
		document.write = original;
		expect( called ).toBe( false );
	} );
} );
