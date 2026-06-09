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
		adapterConfig: {
			products: { ga4: { measurementIds: [ 'G-ABC' ], containerIds: [] } },
			purposeSignals,
		},
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
		document.querySelectorAll( 'script[src*="googletagmanager.com"]' ).forEach( ( s ) => s.remove() );
	} );

	it( 'pushes a consent update with the mapped signals', () => {
		const calls = [];
		google.apply( ctx( { necessary: true, analytics: true, marketing: false }, calls ) );
		const update = calls.find( ( a ) => a[ 0 ] === 'consent' && a[ 1 ] === 'update' );
		expect( update[ 2 ].analytics_storage ).toBe( 'granted' );
		expect( update[ 2 ].ad_storage ).toBe( 'denied' );
	} );

	it( 'loads gtag.js once when the tag is granted', () => {
		const calls = [];
		google.apply( ctx( { necessary: true, analytics: true }, calls ) );
		google.apply( ctx( { necessary: true, analytics: true }, calls ) );
		const scripts = document.querySelectorAll( 'script[src*="gtag/js?id=G-ABC"]' );
		expect( scripts.length ).toBe( 1 );
		expect( scripts[ 0 ].async ).toBe( true );
	} );

	it( 'loads the GTM container once when the tag is granted', () => {
		const calls = [];
		const c = ctx( { necessary: true, analytics: true }, calls );
		c.tag = { id: 'gtm', purposes: [ 'analytics' ] };
		c.adapterConfig = {
			products: { gtm: { measurementIds: [], containerIds: [ 'GTM-XYZ' ] } },
			purposeSignals,
		};
		google.apply( c );
		google.apply( c );
		const containers = document.querySelectorAll( 'script[src*="gtm.js?id=GTM-XYZ"]' );
		expect( containers.length ).toBe( 1 );
		expect( containers[ 0 ].async ).toBe( true );
		expect( document.querySelectorAll( 'script[src*="gtag/js"]' ).length ).toBe( 0 );
	} );

	it( 'does not load gtag.js pre-consent but still pushes the consent update', () => {
		const calls = [];
		google.apply( ctx( { necessary: true, analytics: false, marketing: false }, calls ) );
		expect( document.querySelectorAll( 'script[src*="gtag/js"]' ).length ).toBe( 0 );
		const update = calls.find( ( a ) => a[ 0 ] === 'consent' && a[ 1 ] === 'update' );
		expect( update[ 2 ].security_storage ).toBe( 'granted' );
		expect( update[ 2 ].analytics_storage ).toBe( 'denied' );
		expect( update[ 2 ].ad_storage ).toBe( 'denied' );
	} );

	it( 'loads only the product keyed by the granted tag id', () => {
		const calls = [];
		const c = ctx( { necessary: true, analytics: true }, calls );
		c.adapterConfig.products = {
			ga4: { measurementIds: [ 'G-ABC' ], containerIds: [] },
			'google-ads': { measurementIds: [ 'AW-111' ], containerIds: [] },
		};
		google.apply( c );
		expect( document.querySelectorAll( 'script[src*="gtag/js?id=G-ABC"]' ).length ).toBe( 1 );
		expect( document.querySelectorAll( 'script[src*="gtag/js?id=AW-111"]' ).length ).toBe( 0 );
	} );

	it( 'gates each google tag independently and pushes one update for identical state', () => {
		const calls = [];
		const grants = { necessary: true, analytics: true, marketing: false };
		const products = {
			ga4: { measurementIds: [ 'G-ABC' ], containerIds: [] },
			'google-ads': { measurementIds: [ 'AW-111' ], containerIds: [] },
		};
		const a = ctx( grants, calls );
		a.adapterConfig.products = products;
		google.apply( a );
		const b = ctx( grants, calls );
		b.tag = { id: 'google-ads', purposes: [ 'marketing' ] };
		b.granted = false;
		b.adapterConfig.products = products;
		google.apply( b );
		expect( document.querySelectorAll( 'script[src*="gtag/js?id=G-ABC"]' ).length ).toBe( 1 );
		expect( document.querySelectorAll( 'script[src*="gtag/js?id=AW-111"]' ).length ).toBe( 0 );
		expect( calls.filter( ( c ) => c[ 1 ] === 'update' ).length ).toBe( 1 );
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
